<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Doctor\Repositories\DoctorRepository;
use Webkul\Doctor\Repositories\SpecialtyRepository;
use Webkul\Admin\Services\ShareMeDataService;

class ShareMeDataWebhookController extends Controller
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository,
        protected PersonRepository $personRepository,
        protected DoctorRepository $doctorRepository,
        protected SpecialtyRepository $specialtyRepository,
        protected ShareMeDataService $shareMeDataService
    ) {}

    /**
     * Recibir citas desde ShareMeData (Webhook)
     */
    public function receive(Request $request)
    {
        Log::info('ShareMeData Webhook: Datos recibidos', $request->all());

        $data = $request->all();

        // Validaciones básicas del payload de SMD
        if (!isset($data['physician']['_id']) || !isset($data['patient']['phone'])) {
            return response()->json(['message' => 'Datos incompletos'], 400);
        }

        try {
            return DB::transaction(function () use ($data) {
                // 1. Identificar o Crear Doctor
                $doctorExternalId = $data['physician']['_id'];
                $doctor = $this->doctorRepository->findOneByField('unique_id', $doctorExternalId);

                if (!$doctor) {
                    // Si no existe, lo creamos con los datos que vengan
                    $doctor = $this->doctorRepository->create([
                        'name'      => $data['physician']['name'] ?? 'Doctor Externo',
                        'unique_id' => $doctorExternalId,
                        'is_active' => true,
                    ]);
                    Log::info("Webhook SMD: Doctor creado automáticamente", ['id' => $doctor->id]);
                }

                // 2. Gestionar Especialidad (Automatización del Punto 3)
                $specialtyName = $data['specialty'] ?? 'General';
                $specialty = $this->specialtyRepository->fetchOrCreateByName($specialtyName);
                if (!$doctor->specialties->contains($specialty->id)) {
                    $doctor->specialties()->attach($specialty->id);
                }

                // 3. Identificar o Crear Paciente (Evitar duplicados por teléfono)
                $phone = $data['patient']['phone'];
                $person = $this->personRepository->findOneByField('contact_numbers', $phone);
                
                if (!$person) {
                    // Búsqueda profunda en JSON
                    $person = DB::table('persons')
                        ->where('contact_numbers', 'LIKE', "%{$phone}%")
                        ->first();
                }

                if (!$person) {
                    $person = $this->personRepository->create([
                        'name'            => ($data['patient']['name'] ?? 'Paciente') . ' ' . ($data['patient']['lastName'] ?? 'Externo'),
                        'contact_numbers' => [['value' => $phone, 'label' => 'work']],
                    ]);
                    $personId = $person->id;
                } else {
                    $personId = $person->id;
                }

                // 4. Crear Lead
                $leadTitle = ($data['patient']['name'] ?? 'Cita') . " - " . ($specialtyName);
                $lead = $this->leadRepository->create([
                    'title'       => $leadTitle,
                    'description' => $data['summary'] ?? 'Cita sincronizada desde ShareMeData',
                    'entity_type' => 'leads',
                    'person'      => ['id' => $personId],
                    'doctor_id'   => $doctor->id,
                ]);

                // 5. Crear Actividad (Cita)
                $scheduleFrom = Carbon::parse($data['slot']['start']);
                $scheduleTo = Carbon::parse($data['slot']['end']);

                $activity = $this->activityRepository->create([
                    'type'          => 'meeting',
                    'title'         => $leadTitle,
                    'comment'       => $data['summary'] ?? 'Sincronizado vía Webhook',
                    'schedule_from' => $scheduleFrom->format('Y-m-d H:i:s'),
                    'schedule_to'   => $scheduleTo->format('Y-m-d H:i:s'),
                    'user_id'       => 1, // Usuario admin por defecto o sistema
                    'participants'  => [
                        'doctors' => [$doctor->id],
                        'persons' => [$personId],
                    ],
                    'additional'    => json_encode([
                        'lead_id'     => $lead->id,
                        'external_id' => $data['_id'] ?? null, // ID del evento en SMD
                        'source'      => 'ShareMeData'
                    ]),
                ]);

                $activity->leads()->sync([$lead->id]);

                Log::info("Webhook SMD: Cita procesada correctamente", [
                    'lead_id'     => $lead->id,
                    'activity_id' => $activity->id
                ]);

                return response()->json([
                    'success'     => true,
                    'lead_id'     => $lead->id,
                    'activity_id' => $activity->id
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("ShareMeData Webhook: Error procesando cita", [
                'error' => $e->getMessage(),
                'data'  => $data
            ]);
            return response()->json(['message' => 'Error interno'], 500);
        }
    }
}
