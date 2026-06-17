<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Exceptions\AppointmentException;
use Webkul\Admin\Services\AppointmentService;
use Webkul\Admin\Services\IncomingAppointmentService;
use Webkul\Admin\Services\ShareMeDataService;
use Webkul\Contact\Models\Person;
use Webkul\Doctor\Models\Doctor;
use Webkul\Lead\Models\Lead;

uses(DatabaseTransactions::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Construye la estructura de slots de 15 min que espera AppointmentService
 * para considerar el rango [$from, $to) completamente disponible en SMD.
 */
function buildSmdSlots(Carbon $from, Carbon $to): array
{
    $intervals = [];
    $current   = $from->copy();

    while ($current->lessThan($to)) {
        $intervals[] = [
            'start' => $current->toIso8601String(),
            'end'   => $current->copy()->addMinutes(15)->toIso8601String(),
        ];
        $current->addMinutes(15);
    }

    return [
        [$from->toDateString() => $intervals],
    ];
}

function makeAvailableDoctor(): Doctor
{
    return Doctor::create([
        'name'      => 'Doctor Conflicto '.uniqid(),
        'email'     => 'doctor'.uniqid().'@example.com',
        'unique_id' => 'smd-doc-'.uniqid(),
        'is_active' => true,
    ]);
}

function makeShift(int $doctorId, Carbon $from, Carbon $to): void
{
    DB::table('doctor_shifts')->insert([
        'doctor_id'  => $doctorId,
        'date'       => $from->toDateString(),
        'start_time' => '08:00',
        'end_time'   => '18:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makePatient(): Person
{
    $phone = '7'.rand(1000000, 9999999);

    return Person::create([
        'name'            => 'Paciente Nuevo '.uniqid(),
        'contact_numbers' => [['value' => $phone, 'label' => 'work']],
        'smd_patient_id'  => 'smd-pat-'.uniqid(),
        'entity_type'     => 'persons',
    ]);
}

/**
 * Inserta una Activity existente para $doctorId en el rango dado, opcionalmente
 * vinculada a uno o más Leads (cada uno con su propio status).
 */
function makeExistingActivity(int $doctorId, Carbon $from, Carbon $to, array $leadStatuses = []): int
{
    $activityId = DB::table('activities')->insertGetId([
        'type'          => 'meeting',
        'title'         => 'Cita Previa '.uniqid(),
        'schedule_from' => $from->format('Y-m-d H:i:s'),
        'schedule_to'   => $to->format('Y-m-d H:i:s'),
        'user_id'       => 1,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    DB::table('doctor_activities')->insert([
        'doctor_id'   => $doctorId,
        'activity_id' => $activityId,
    ]);

    foreach ($leadStatuses as $status) {
        $lead = Lead::create([
            'title'       => 'Lead Previo '.uniqid(),
            'description' => 'Test',
            'entity_type' => 'leads',
        ]);

        DB::table('leads')->where('id', $lead->id)->update(['status' => $status]);

        DB::table('lead_activities')->insert([
            'lead_id'     => $lead->id,
            'activity_id' => $activityId,
        ]);
    }

    return $activityId;
}

/**
 * Mock de ShareMeDataService: por defecto simula disponibilidad completa y
 * createEvent exitoso. $slots = null simula "sin disponibilidad".
 */
function bindShareMeDataMock(?array $slots, bool $expectAvailabilityCheck = true): \Mockery\MockInterface
{
    $mock = \Mockery::mock(ShareMeDataService::class);

    if ($expectAvailabilityCheck) {
        $mock->shouldReceive('checkAvailability')->andReturn($slots ?? []);
        $mock->shouldReceive('getLastResponse')->andReturn([
            'status' => 200,
            'body'   => ['message' => 'Sin disponibilidad devuelta por SMD'],
        ]);
    } else {
        $mock->shouldNotReceive('checkAvailability');
    }

    if ($slots !== null) {
        $mock->shouldReceive('createEvent')->andReturn([
            'success' => true,
            'data'    => ['_id' => 'evt-'.uniqid()],
        ]);
    } else {
        $mock->shouldNotReceive('createEvent');
    }

    app()->bind(ShareMeDataService::class, fn () => $mock);

    return $mock;
}

beforeEach(function () {
    $this->from = Carbon::tomorrow()->setTime(9, 0);
    $this->to   = $this->from->copy()->addMinutes(30);
});

// ---------------------------------------------------------------------------
// 1. Lead cerrado no bloquea — y SMD permite → la cita nueva se crea
// ---------------------------------------------------------------------------

it('permite crear una cita nueva cuando la unica cita previa pertenece a un lead cerrado y SMD confirma disponibilidad', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);
    makeExistingActivity($doctor->id, $this->from, $this->to, [0]); // lead cerrado

    bindShareMeDataMock(buildSmdSlots($this->from, $this->to));

    $person  = makePatient();
    $service = app(AppointmentService::class);

    $result = $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
        'reason'        => 'Cita nueva tras cancelacion',
    ]);

    expect($result['activity_id'])->not->toBeNull();
    expect($result['lead_id'])->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 2. Lead abierto sigue bloqueando (sin regresion) — y no debe llegar a SMD
// ---------------------------------------------------------------------------

it('sigue bloqueando cuando la cita previa pertenece a un lead abierto, sin consultar SMD', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);
    makeExistingActivity($doctor->id, $this->from, $this->to, [null]); // lead abierto

    bindShareMeDataMock(null, expectAvailabilityCheck: false);

    $person  = makePatient();
    $service = app(AppointmentService::class);

    expect(fn () => $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
    ]))->toThrow(AppointmentException::class, 'ya tiene una cita programada en este horario en el sistema local');
});

// ---------------------------------------------------------------------------
// 3. Activity sin lead vinculado sigue bloqueando
// ---------------------------------------------------------------------------

it('sigue bloqueando cuando la activity previa no tiene ningun lead vinculado', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);
    makeExistingActivity($doctor->id, $this->from, $this->to, []); // sin leads

    bindShareMeDataMock(null, expectAvailabilityCheck: false);

    $person  = makePatient();
    $service = app(AppointmentService::class);

    expect(fn () => $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
    ]))->toThrow(AppointmentException::class);
});

// ---------------------------------------------------------------------------
// 4. Multiples leads en la misma activity, uno abierto y otro cerrado
// ---------------------------------------------------------------------------

it('sigue bloqueando si al menos uno de los leads vinculados a la activity esta abierto', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);
    makeExistingActivity($doctor->id, $this->from, $this->to, [0, null]); // uno cerrado, uno abierto

    bindShareMeDataMock(null, expectAvailabilityCheck: false);

    $person  = makePatient();
    $service = app(AppointmentService::class);

    expect(fn () => $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
    ]))->toThrow(AppointmentException::class);
});

// ---------------------------------------------------------------------------
// 5. Lead cerrado pero SMD dice que NO hay disponibilidad → falla en el paso correcto
// ---------------------------------------------------------------------------

it('libera el slot localmente pero sigue rechazando si SMD no tiene disponibilidad real', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);
    makeExistingActivity($doctor->id, $this->from, $this->to, [0]); // lead cerrado

    bindShareMeDataMock(null); // SMD sin slots disponibles

    $person  = makePatient();
    $service = app(AppointmentService::class);

    expect(fn () => $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
    ]))->toThrow(AppointmentException::class, 'no tiene disponibilidad en SHAREMEDATA');
});

// ---------------------------------------------------------------------------
// 6. Caso historico — cancelacion real (cancelDropbox) round-trip
// ---------------------------------------------------------------------------

it('permite agendar tras una cancelacion real via cancelDropbox', function () {
    $doctor = makeAvailableDoctor();
    makeShift($doctor->id, $this->from, $this->to);

    $activityId = DB::table('activities')->insertGetId([
        'type'          => 'meeting',
        'title'         => 'Cita Original',
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
        'user_id'       => 1,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    DB::table('doctor_activities')->insert([
        'doctor_id'   => $doctor->id,
        'activity_id' => $activityId,
    ]);

    $lead = Lead::create([
        'title'       => 'Lead Original '.uniqid(),
        'description' => 'Test',
        'entity_type' => 'leads',
    ]);

    DB::table('lead_activities')->insert([
        'lead_id'     => $lead->id,
        'activity_id' => $activityId,
    ]);

    $cancelledStageId = DB::table('lead_pipeline_stages')->value('id') ?? 1;
    config(['smd.stage_map.cancelled' => $cancelledStageId]);

    // Cancelación real desde SMD, igual que en producción
    $incoming = app(IncomingAppointmentService::class);
    $incoming->cancelDropbox('ext-'.uniqid(), (object) [
        'lead_id'     => $lead->id,
        'activity_id' => $activityId,
    ]);

    expect(Lead::find($lead->id)->status)->toBe(0);

    bindShareMeDataMock(buildSmdSlots($this->from, $this->to));

    $person  = makePatient();
    $service = app(AppointmentService::class);

    $result = $service->process([
        'doctor_id'     => $doctor->id,
        'person'        => ['id' => $person->id],
        'schedule_from' => $this->from->format('Y-m-d H:i:s'),
        'schedule_to'   => $this->to->format('Y-m-d H:i:s'),
    ]);

    expect($result['activity_id'])->not->toBeNull();
    expect($result['activity_id'])->not->toBe($activityId);
});
