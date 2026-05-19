<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Doctor\Repositories\DoctorRepository;
use Webkul\Doctor\Repositories\SpecialtyRepository;
use Webkul\Lead\Repositories\LeadRepository;

class IncomingAppointmentService
{
    public function __construct(
        protected DoctorRepository    $doctorRepository,
        protected SpecialtyRepository $specialtyRepository,
        protected PersonRepository    $personRepository,
        protected LeadRepository      $leadRepository,
        protected ActivityRepository  $activityRepository,
    ) {}

    /**
     * Procesa payload en formato Webhook (physician/patient/slot).
     */
    public function processWebhook(array $data): array
    {
        $normalized = [
            'doctor_external_id' => $data['physician']['_id'],
            'doctor_name'        => trim(($data['physician']['name'] ?? '').' '.($data['physician']['lastName'] ?? '')),
            'doctor_email'       => $data['physician']['email'] ?? null,
            'patient_name'       => ($data['patient']['name'] ?? 'Paciente').' '.($data['patient']['lastName'] ?? ''),
            'patient_phone'      => $data['patient']['phone'],
            'schedule_from'      => $data['slot']['start'],
            'schedule_to'        => $data['slot']['end'],
            'specialty'          => $data['specialty'] ?? 'General',
            'summary'            => $data['summary'] ?? '',
            'external_id'        => $data['_id'] ?? null,
        ];

        return $this->process($normalized);
    }

    /**
     * Procesa payload en formato Dropbox (owner/attendances/startDate/endDate).
     */
    public function processDropbox(array $data): array
    {
        $patient = $data['attendances'][0]['patient'] ?? [];

        $normalized = [
            'doctor_external_id' => $data['owner']['_id'] ?? null,
            'doctor_name'        => trim(($data['owner']['name'] ?? '').' '.($data['owner']['lastName'] ?? '')),
            'doctor_email'       => $data['owner']['email'] ?? null,
            'patient_name'       => trim(($patient['name'] ?? 'Paciente').' '.($patient['lastName'] ?? '')),
            'patient_phone'      => $patient['phone'] ?? '0',
            'schedule_from'      => $data['startDate'],
            'schedule_to'        => $data['endDate'],
            'specialty'          => 'General',
            'summary'            => $data['summary'] ?? '',
            'external_id'        => $data['_id'] ?? null,
        ];

        return $this->process($normalized);
    }

    private function process(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $doctor    = $this->resolveDoctor($data);
            $specialty = $this->specialtyRepository->fetchOrCreateByName($data['specialty']);

            if (! $doctor->specialties->contains($specialty->id)) {
                $doctor->specialties()->attach($specialty->id);
            }

            $person = $this->resolvePerson($data['patient_phone'], $data['patient_name']);
            $lead   = $this->createLead($person, $data);

            $activity = $this->activityRepository->create([
                'type'          => 'meeting',
                'title'         => $data['summary'] ?: ($person->name.' - '.$data['specialty']),
                'comment'       => $data['summary'] ?? '',
                'schedule_from' => Carbon::parse($data['schedule_from'])->format('Y-m-d H:i:s'),
                'schedule_to'   => Carbon::parse($data['schedule_to'])->format('Y-m-d H:i:s'),
                'user_id'       => 1,
                'participants'  => ['doctors' => [$doctor->id], 'persons' => [$person->id]],
                'additional'    => json_encode([
                    'lead_id'     => $lead->id,
                    'external_id' => $data['external_id'],
                    'source'      => 'ShareMeData',
                ]),
            ]);

            $activity->leads()->sync([$lead->id]);

            Log::info('[IncomingAppointment] Cita procesada', [
                'lead_id'     => $lead->id,
                'activity_id' => $activity->id,
                'external_id' => $data['external_id'],
            ]);

            return ['lead_id' => $lead->id, 'activity_id' => $activity->id];
        });
    }

    private function resolveDoctor(array $data): object
    {
        $doctor = $data['doctor_external_id']
            ? $this->doctorRepository->findOneByField('unique_id', $data['doctor_external_id'])
            : null;

        if (! $doctor) {
            $doctor = $this->doctorRepository->findOneByField('name', $data['doctor_name'])
                ?? $this->doctorRepository->create([
                    'name'      => $data['doctor_name'] ?: 'Doctor Externo',
                    'unique_id' => $data['doctor_external_id'],
                    'is_active' => true,
                ]);

            if ($data['doctor_external_id'] && ! $doctor->unique_id) {
                $this->doctorRepository->update(['unique_id' => $data['doctor_external_id']], $doctor->id);
                $doctor = $this->doctorRepository->find($doctor->id);
            }
        }

        if ($data['doctor_email'] && $doctor->email !== $data['doctor_email']) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('doctors', 'email')) {
                $this->doctorRepository->update(['email' => $data['doctor_email']], $doctor->id);
                $doctor = $this->doctorRepository->find($doctor->id);
            }
        }

        return $doctor;
    }

    private function resolvePerson(string $phone, string $name): object
    {
        $cleanPhone = ltrim(preg_replace('/[^0-9]/', '', $phone), '0');
        $kommoEmail = $cleanPhone.'@s.kommo-whatsapp.net';

        $person = $this->personRepository->whereJsonContains('emails', [['value' => $kommoEmail]])->first()
            ?? $this->personRepository->whereJsonContains('contact_numbers', [['value' => $phone]])->first();

        if ($person && ! ($person instanceof \Webkul\Contact\Contracts\Person)) {
            $person = $this->personRepository->find($person->id);
        }

        return $person ?? $this->personRepository->create([
            'name'            => $name ?: 'Paciente Externo',
            'emails'          => [['value' => $kommoEmail, 'label' => 'work']],
            'contact_numbers' => [['value' => $phone, 'label' => 'work']],
            'entity_type'     => 'persons',
        ]);
    }

    private function createLead(object $person, array $data): object
    {
        $leadData = [
            'title'       => ($person->name ?? 'Paciente').' - '.$data['specialty'],
            'description' => $data['summary'] ?: 'Cita sincronizada desde ShareMeData',
            'entity_type' => 'leads',
            'person'      => ['id' => $person->id],
        ];

        return $this->leadRepository->create($leadData);
    }
}
