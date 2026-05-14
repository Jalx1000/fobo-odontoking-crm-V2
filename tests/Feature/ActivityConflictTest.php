<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Doctor\Models\Doctor;
use Webkul\Doctor\Models\Specialty;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->adminUser = User::find(1);
    $this->repo = app(ActivityRepository::class);

    $specialty = Specialty::first() ?: Specialty::create(['name' => 'General', 'slug' => 'general']);
    $this->doctor = Doctor::create(['name' => 'Doctor Conflicto '.uniqid(), 'is_active' => true]);
});

it('detecta solapamiento exacto de horarios para el mismo doctor', function () {
    $from = Carbon::now()->addDay()->setTime(9, 0);
    $to = $from->copy()->addHour();

    // Primera actividad
    $this->repo->create([
        'type'          => 'meeting',
        'title'         => 'Cita Base',
        'schedule_from' => $from->format('Y-m-d H:i:s'),
        'schedule_to'   => $to->format('Y-m-d H:i:s'),
        'user_id'       => $this->adminUser->id,
        'participants'  => ['doctors' => [$this->doctor->id]],
    ]);

    $overlaps = $this->repo->isDurationOverlapping(
        $from->format('Y-m-d H:i:s'),
        $to->format('Y-m-d H:i:s'),
        ['doctors' => [$this->doctor->id]],
        null
    );

    expect($overlaps)->toBeTrue();
});

it('no detecta conflicto cuando las citas no se solapan', function () {
    $from = Carbon::now()->addDay()->setTime(9, 0);
    $to = $from->copy()->addHour();

    $this->repo->create([
        'type'          => 'meeting',
        'title'         => 'Cita Sin Conflicto',
        'schedule_from' => $from->format('Y-m-d H:i:s'),
        'schedule_to'   => $to->format('Y-m-d H:i:s'),
        'user_id'       => $this->adminUser->id,
        'participants'  => ['doctors' => [$this->doctor->id]],
    ]);

    // Cita posterior sin solapamiento
    $from2 = $to->copy()->addMinutes(30);
    $to2 = $from2->copy()->addHour();

    $overlaps = $this->repo->isDurationOverlapping(
        $from2->format('Y-m-d H:i:s'),
        $to2->format('Y-m-d H:i:s'),
        ['doctors' => [$this->doctor->id]],
        null
    );

    expect($overlaps)->toBeFalse();
});

it('detecta solapamiento parcial (inicio dentro de cita existente)', function () {
    $from = Carbon::now()->addDay()->setTime(10, 0);
    $to = $from->copy()->addHours(2); // 10:00 - 12:00

    $this->repo->create([
        'type'          => 'meeting',
        'title'         => 'Cita Existente',
        'schedule_from' => $from->format('Y-m-d H:i:s'),
        'schedule_to'   => $to->format('Y-m-d H:i:s'),
        'user_id'       => $this->adminUser->id,
        'participants'  => ['doctors' => [$this->doctor->id]],
    ]);

    // Nueva cita que empieza en 11:00 (dentro de la existente)
    $newFrom = $from->copy()->addHour();
    $newTo = $newFrom->copy()->addHours(2);

    $overlaps = $this->repo->isDurationOverlapping(
        $newFrom->format('Y-m-d H:i:s'),
        $newTo->format('Y-m-d H:i:s'),
        ['doctors' => [$this->doctor->id]],
        null
    );

    expect($overlaps)->toBeTrue();
});

it('excluye la actividad actual al verificar conflictos en edición', function () {
    $from = Carbon::now()->addDay()->setTime(9, 0);
    $to = $from->copy()->addHour();

    $activity = $this->repo->create([
        'type'          => 'meeting',
        'title'         => 'Cita Editable',
        'schedule_from' => $from->format('Y-m-d H:i:s'),
        'schedule_to'   => $to->format('Y-m-d H:i:s'),
        'user_id'       => $this->adminUser->id,
        'participants'  => ['doctors' => [$this->doctor->id]],
    ]);

    // Verificar con el mismo ID excluido no debe reportar conflicto
    $overlaps = $this->repo->isDurationOverlapping(
        $from->format('Y-m-d H:i:s'),
        $to->format('Y-m-d H:i:s'),
        ['doctors' => [$this->doctor->id]],
        $activity->id
    );

    expect($overlaps)->toBeFalse();
});
