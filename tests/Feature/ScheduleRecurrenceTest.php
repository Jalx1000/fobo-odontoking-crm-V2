<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Doctor\Models\Doctor;
use Webkul\Doctor\Models\Specialty;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->adminUser = User::find(1);

    $specialty = Specialty::first() ?: Specialty::create(['name' => 'General', 'slug' => 'general']);

    $this->doctor = Doctor::create([
        'name'      => 'Doctor Recurrencia '.uniqid(),
        'is_active' => true,
    ]);
});

it('crea turnos recurrentes para días seleccionados dentro del rango', function () {
    $start = Carbon::now()->startOfWeek()->addWeek();
    $end = $start->copy()->addDays(13);

    $response = actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'recurrence' => true,
            'doctor_id'  => $this->doctor->id,
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'days'       => [1, 3], // lunes y miércoles
            'start_time' => '08:00',
            'end_time'   => '12:00',
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['message']);

    // 2 semanas × 2 días = 4 turnos
    expect($response->json('message'))->toContain('4');
});

it('rechaza recurrencia sin días seleccionados', function () {
    $start = Carbon::now()->addDay();

    $response = actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'recurrence' => true,
            'doctor_id'  => $this->doctor->id,
            'start_date' => $start->toDateString(),
            'end_date'   => $start->copy()->addDays(7)->toDateString(),
            'days'       => [],
            'start_time' => '08:00',
            'end_time'   => '12:00',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['days']);
});

it('rechaza recurrencia con end_date anterior a start_date', function () {
    $start = Carbon::now()->addDays(5);

    $response = actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'recurrence' => true,
            'doctor_id'  => $this->doctor->id,
            'start_date' => $start->toDateString(),
            'end_date'   => $start->copy()->subDays(2)->toDateString(),
            'days'       => [1],
            'start_time' => '08:00',
            'end_time'   => '12:00',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['end_date']);
});

it('omite días que ya tienen turno solapado sin fallar', function () {
    $monday = Carbon::now()->startOfWeek()->addWeek();

    // Crear un turno previo el lunes
    actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'doctor_id'  => $this->doctor->id,
            'date'       => $monday->toDateString(),
            'start_time' => '09:00',
            'end_time'   => '11:00',
        ])
        ->assertStatus(201);

    // Recurrencia que incluye ese mismo lunes con horario solapado
    $response = actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'recurrence' => true,
            'doctor_id'  => $this->doctor->id,
            'start_date' => $monday->toDateString(),
            'end_date'   => $monday->copy()->addDays(6)->toDateString(),
            'days'       => [1], // solo lunes
            'start_time' => '08:00',
            'end_time'   => '12:00',
        ]);

    // Debe responder 201 pero indicar 0 creados (conflicto omitido)
    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => '0 turnos creados correctamente.']);
});

it('rechaza turno individual con end_time anterior a start_time', function () {
    $response = actingAs($this->adminUser)
        ->postJson(route('admin.schedules.store'), [
            'doctor_id'  => $this->doctor->id,
            'date'       => Carbon::now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time'   => '10:00',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['end_time']);
});
