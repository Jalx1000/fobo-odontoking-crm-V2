<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Exceptions\AppointmentException;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Doctor\Repositories\DoctorRepository;
use Webkul\Doctor\Repositories\SpecialtyRepository;
use Webkul\Lead\Repositories\LeadRepository;

class AppointmentService
{
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected LeadRepository     $leadRepository,
        protected PersonRepository   $personRepository,
        protected ShareMeDataService $shareMeDataService,
        protected DoctorRepository   $doctorRepository,
        protected SpecialtyRepository $specialtyRepository,
    ) {}

    /**
     * Valida y crea una cita médica de forma unificada.
     *
     * Orden garantizado:
     *  1. Validar turno local (doctor_shifts)
     *  2. Validar conflicto local (doctor_activities)
     *  3. Descubrir unique_id del doctor en SMD si falta
     *  4. Verificar disponibilidad en ShareMeData
     *  5. Crear evento en ShareMeData PRIMERO
     *  6. Crear registros locales (Lead + Activity) en transacción
     *
     * @param  array  $data {
     *   doctor_id, person.id, schedule_from, schedule_to,
     *   title?, reason?, product_id?, lead_id?
     * }
     * @return array { lead_id, activity_id, message }
     * @throws AppointmentException
     */
    public function process(array $data): array
    {
        $scheduleFrom = Carbon::parse($data['schedule_from']);
        $scheduleTo   = Carbon::parse($data['schedule_to']);
        $doctorId     = $data['doctor_id'];
        $personData   = $data['person'];
        $productId      = $data['product_id'] ?? null;
        $existingLeadId = $data['lead_id'] ?? null;

        // Si no viene product_id pero sí lead_id, intentar tomarlo del primer producto del lead
        if (! $productId && $existingLeadId) {
            $leadProduct = DB::table('lead_products')
                ->where('lead_id', $existingLeadId)
                ->orderBy('id')
                ->value('product_id');
            $productId = $leadProduct ?: null;
        }

        $doctor = DB::table('doctors')->where('id', $doctorId)->first();

        if (! $doctor) {
            throw new AppointmentException("Doctor con ID {$doctorId} no encontrado.");
        }

        $doctorExternalId = $doctor->unique_id;
        $doctorEmail      = $doctor->email;

        // ── 1. Validar turno/jornada laboral ─────────────────────────────────
        $hasValidShift = DB::table('doctor_shifts')
            ->where('doctor_id', $doctorId)
            ->where('date', $scheduleFrom->toDateString())
            ->where('start_time', '<=', $scheduleFrom->format('H:i'))
            ->where('end_time', '>=', $scheduleTo->format('H:i'))
            ->exists();

        if (! $hasValidShift) {
            throw new AppointmentException(
                "El horario seleccionado está fuera de la jornada laboral del doctor para este día. " .
                "Por favor, revisa los turnos disponibles."
            );
        }

        // ── 2. Validar conflicto local ────────────────────────────────────────
        $localConflict = DB::table('activities')
            ->join('doctor_activities', 'activities.id', '=', 'doctor_activities.activity_id')
            ->where('doctor_activities.doctor_id', $doctorId)
            ->where(function ($query) use ($scheduleFrom, $scheduleTo) {
                $query->where(function ($q) use ($scheduleFrom, $scheduleTo) {
                    $q->where('schedule_from', '>=', $scheduleFrom)
                      ->where('schedule_from', '<', $scheduleTo);
                })->orWhere(function ($q) use ($scheduleFrom, $scheduleTo) {
                    $q->where('schedule_to', '>', $scheduleFrom)
                      ->where('schedule_to', '<=', $scheduleTo);
                })->orWhere(function ($q) use ($scheduleFrom, $scheduleTo) {
                    $q->where('schedule_from', '<=', $scheduleFrom)
                      ->where('schedule_to', '>=', $scheduleTo);
                });
            })
            ->exists();

        if ($localConflict) {
            throw new AppointmentException(
                "El doctor ya tiene una cita programada en este horario en el sistema local."
            );
        }

        // ── 3. Auto-descubrir unique_id en SMD si falta ──────────────────────
        if (empty($doctorExternalId) || empty($doctorEmail)) {
            $doctorModel          = $this->doctorRepository->with('specialties')->find($doctorId);
            $discoverySpecialties = $doctorModel->specialties->pluck('name')->toArray();

            if (empty($discoverySpecialties)) {
                $discoverySpecialties = $this->specialtyRepository->all()->pluck('name')->toArray();
            }

            if (empty($discoverySpecialties)) {
                $discoverySpecialties = ['General'];
            }

            $found = false;
            foreach ($discoverySpecialties as $spec) {
                $this->shareMeDataService->checkAvailability(
                    null, $spec, 'Santa Cruz',
                    $scheduleFrom->format('Y-m-d H:i:s'),
                    $scheduleTo->format('Y-m-d H:i:s')
                );
                $raw = $this->shareMeDataService->getLastResponse();

                if ($raw && isset($raw['body']) && is_array($raw['body'])) {
                    foreach ($raw['body'] as $item) {
                        $smdName = trim(strtolower(
                            ($item['physician']['name']     ?? '') . ' ' .
                            ($item['physician']['lastName'] ?? '')
                        ));
                        if ($smdName === trim(strtolower($doctor->name))) {
                            $doctorExternalId = $item['physician']['_id']    ?? null;
                            $doctorEmail      = $item['physician']['email']  ?? null;
                            if ($doctorExternalId) {
                                $this->doctorRepository->update(
                                    ['unique_id' => $doctorExternalId, 'email' => $doctorEmail],
                                    $doctor->id
                                );
                                $found = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (! $found) {
                throw new AppointmentException(
                    "No se pudo vincular al doctor {$doctor->name} con ShareMeData. " .
                    "Verifica que el nombre coincida exactamente."
                );
            }
        }

        // ── 4. Verificar disponibilidad en ShareMeData ───────────────────────
        $doctorModel      = $this->doctorRepository->with('specialties')->find($doctorId);
        $doctorSpecialties = $doctorModel->specialties->pluck('name')->toArray();

        if (empty($doctorSpecialties)) {
            $doctorSpecialties = $this->specialtyRepository->all()->pluck('name')->toArray();
        }

        if (empty($doctorSpecialties)) {
            $doctorSpecialties = ['General'];
        }

        $isAvailableExternally = false;
        $smdErrors             = [];

        foreach ($doctorSpecialties as $specialty) {
            $slots = $this->shareMeDataService->checkAvailability(
                $doctorExternalId, $specialty, 'Santa Cruz',
                $scheduleFrom->format('Y-m-d H:i:s'),
                $scheduleTo->format('Y-m-d H:i:s')
            );
            $lastResponse = $this->shareMeDataService->getLastResponse();

            if (! empty($slots)) {
                $requiredIntervals = [];
                $current = $scheduleFrom->copy();
                while ($current->lessThan($scheduleTo)) {
                    $requiredIntervals[] = [
                        'start' => $current->timestamp,
                        'end'   => $current->copy()->addMinutes(15)->timestamp,
                    ];
                    $current->addMinutes(15);
                }

                $foundCount = 0;
                foreach ($requiredIntervals as $required) {
                    foreach ($slots as $daySlots) {
                        foreach ($daySlots as $date => $intervals) {
                            foreach ($intervals as $interval) {
                                if (
                                    Carbon::parse($interval['start'])->timestamp === $required['start'] &&
                                    Carbon::parse($interval['end'])->timestamp   === $required['end']
                                ) {
                                    $foundCount++;
                                    continue 3;
                                }
                            }
                        }
                    }
                }

                if ($foundCount === count($requiredIntervals)) {
                    $isAvailableExternally = true;
                    break;
                }

                $smdErrors[$specialty] = "Solo se encontraron {$foundCount} de " . count($requiredIntervals) . " bloques de 15m libres.";
            } else {
                $smdErrors[$specialty] = $lastResponse['body']['message'] ?? 'Sin disponibilidad devuelta por SMD';
            }
        }

        if (! $isAvailableExternally) {
            throw new AppointmentException(
                "El doctor no tiene disponibilidad en SHAREMEDATA para el horario solicitado.",
                ['smd_errors' => $smdErrors, 'doctor_external_id' => $doctorExternalId]
            );
        }

        // ── 5. Crear evento en ShareMeData PRIMERO ───────────────────────────
        $person = $this->personRepository->find($personData['id'] ?? null);

        if (! $person) {
            throw new AppointmentException("Paciente con ID {$personData['id']} no encontrado.");
        }

        $personPhone = '77788990';
        if (! empty($person->contact_numbers)) {
            $contactNumbers = is_array($person->contact_numbers)
                ? $person->contact_numbers
                : json_decode($person->contact_numbers, true);
            if (is_array($contactNumbers) && ! empty($contactNumbers[0]['value'])) {
                $personPhone = (string) $contactNumbers[0]['value'];
            }
        }

        $nameParts = explode(' ', trim($person->name ?? 'Paciente'));
        $firstName = $nameParts[0] ?: 'Paciente';
        $lastName  = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Externo';

        $product    = $productId ? DB::table('products')->where('id', $productId)->first() : null;
        $eventTitle = ($person->name ?? 'Paciente') . ' - ' . ($product->name ?? 'Consulta');
        $title      = $data['title'] ?? $eventTitle;

        $smdPayload = [
            'summary'   => 'CONSULTA: ' . $title,
            'physician' => ['_id' => $doctorExternalId, 'email' => $doctorEmail ?: ''],
            'patient'   => [
                'name'     => (string) $firstName,
                'lastName' => (string) $lastName,
                'phone'    => (string) $personPhone,
                'personID' => '',
                'birthday' => '',
            ],
            'slot' => [
                'start' => $scheduleFrom->format('Y-m-d\TH:i:s-04:00'),
                'end'   => $scheduleTo->format('Y-m-d\TH:i:s-04:00'),
            ],
        ];

        $smdResult = $this->shareMeDataService->createEvent($smdPayload);

        if (! ($smdResult['success'] ?? false)) {
            throw new AppointmentException(
                "Error al registrar la cita en ShareMeData. La cita no fue creada.",
                ['smd_response' => $smdResult]
            );
        }

        // ── 6. Crear registros locales en transacción ────────────────────────
        try {
            return DB::transaction(function () use (
                $data, $scheduleFrom, $scheduleTo,
                $doctor, $doctorExternalId, $doctorEmail,
                $person, $productId, $product, $title,
                $existingLeadId, $smdResult
            ) {
                if ($existingLeadId) {
                    $lead = $this->leadRepository->find($existingLeadId);
                    if (! $lead) {
                        throw new AppointmentException("Lead con ID {$existingLeadId} no encontrado.");
                    }
                } else {
                    $leadData = [
                        'title'       => $title,
                        'description' => $data['reason'] ?? '',
                        'entity_type' => 'leads',
                        'person'      => ['id' => $person->id],
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('leads', 'doctor_id')) {
                        $leadData['doctor_id'] = $doctor->id;
                    }
                    $lead = $this->leadRepository->create($leadData);
                }

                $currentUserId = auth()->guard('user')->id() ?? auth()->id() ?? 1;

                $activity = $this->activityRepository->create([
                    'type'          => 'meeting',
                    'title'         => $title,
                    'comment'       => $data['reason'] ?? '',
                    'schedule_from' => $scheduleFrom->format('Y-m-d H:i:s'),
                    'schedule_to'   => $scheduleTo->format('Y-m-d H:i:s'),
                    'user_id'       => $currentUserId,
                    'participants'  => ['doctors' => [$doctor->id], 'persons' => [$person->id]],
                ]);

                $activity->leads()->sync([$lead->id]);

                if ($productId) {
                    $activity->products()->sync([$productId]);
                }

                Log::info('Cita creada', [
                    'activity_id' => $activity->id,
                    'lead_id'     => $lead->id,
                    'smd_event'   => $smdResult['data'] ?? null,
                ]);

                return [
                    'lead_id'     => $lead->id,
                    'activity_id' => $activity->id,
                    'message'     => 'Cita creada y sincronizada correctamente.',
                ];
            });
        } catch (AppointmentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creando cita localmente tras registro en SMD: ' . $e->getMessage(), [
                'smd_event' => $smdResult['data'] ?? null,
            ]);
            throw new AppointmentException(
                "La cita fue registrada en ShareMeData pero ocurrió un error al guardarla localmente. " .
                "Contacta al administrador.",
                ['internal_error' => $e->getMessage()]
            );
        }
    }
}
