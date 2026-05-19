<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('sincroniza paciente existente en SMD y guarda smd_patient_id', function () {
    Http::fake([
        '*/patients*' => Http::response(['data' => [['_id' => 'smd-test-123']]], 200),
    ]);

    $person = Person::create([
        'name'            => 'Test Sync '.uniqid(),
        'contact_numbers' => [['value' => '77700001', 'label' => 'work']],
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $this->actingAs($user, 'user')
        ->postJson(route('admin.contacts.persons.sync_smd', $person->id))
        ->assertStatus(200)
        ->assertJsonFragment(['smd_patient_id' => 'smd-test-123']);

    expect($person->fresh()->smd_patient_id)->toBe('smd-test-123');
});

it('retorna 422 si el paciente no tiene teléfono', function () {
    $person = Person::create(['name' => 'Sin Tel '.uniqid()]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $this->actingAs($user, 'user')
        ->postJson(route('admin.contacts.persons.sync_smd', $person->id))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'El paciente no tiene número de teléfono registrado.']);
});

it('retorna 422 si el paciente tiene teléfono 0', function () {
    $person = Person::create([
        'name'            => 'Tel Cero '.uniqid(),
        'contact_numbers' => [['value' => '0', 'label' => 'work']],
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $this->actingAs($user, 'user')
        ->postJson(route('admin.contacts.persons.sync_smd', $person->id))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'El paciente no tiene número de teléfono registrado.']);
});

it('sincroniza automáticamente al crear paciente con teléfono via evento', function () {
    Http::fake([
        '*/patients*' => Http::response(['data' => [['_id' => 'smd-auto-456']]], 200),
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $response = $this->actingAs($user, 'user')
        ->postJson(route('admin.contacts.persons.store'), [
            'name'            => 'Auto Sync '.uniqid(),
            'contact_numbers' => [['value' => '77788800', 'label' => 'work']],
            'entity_type'     => 'persons',
        ]);

    // 200 o redirect = creado correctamente
    expect($response->status())->toBeIn([200, 302]);

    // Buscar el paciente recién creado
    $person = \Webkul\Contact\Models\Person::where('name', 'like', 'Auto Sync%')
        ->orderBy('id', 'desc')
        ->first();

    expect($person)->not->toBeNull();
    expect($person->smd_patient_id)->toBe('smd-auto-456');
});

it('actualiza paciente en SMD al editar si ya tiene smd_patient_id', function () {
    Http::fake([
        '*/patients/smd-existing-id' => Http::response(['_id' => 'smd-existing-id', 'name' => 'Nuevo'], 200),
        '*/patients*'                => Http::response(['data' => []], 200),
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $person = Person::create([
        'name'            => 'Paciente Update '.uniqid(),
        'contact_numbers' => [['value' => '77755500', 'label' => 'work']],
        'smd_patient_id'  => 'smd-existing-id',
    ]);

    // PUT sin cabecera AJAX → 302 redirect, pero el listener igual se ejecuta
    $this->actingAs($user, 'user')
        ->put(route('admin.contacts.persons.update', $person->id), [
            'name'            => 'Paciente Actualizado',
            'contact_numbers' => [['value' => '77755500', 'label' => 'work']],
            'entity_type'     => 'persons',
        ]);

    Http::assertSent(fn ($req) => str_contains($req->url(), 'patients/smd-existing-id'));
});

it('vincula paciente en SMD al editar si no tenía smd_patient_id pero ahora tiene teléfono', function () {
    Http::fake([
        '*/patients*' => Http::response(['data' => [['_id' => 'smd-linked-789']]], 200),
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $person = Person::create(['name' => 'Sin SMD '.uniqid()]);

    $this->actingAs($user, 'user')
        ->put(route('admin.contacts.persons.update', $person->id), [
            'name'            => 'Con Telefono Ahora',
            'contact_numbers' => [['value' => '77766600', 'label' => 'work']],
            'entity_type'     => 'persons',
        ]);

    expect($person->fresh()->smd_patient_id)->toBe('smd-linked-789');
});

it('retorna 422 si SMD no devuelve ID tras crear paciente', function () {
    Http::fake([
        '*/patients*' => Http::response(['error' => 'Internal error'], 500),
    ]);

    $person = Person::create([
        'name'            => 'Fallo SMD '.uniqid(),
        'contact_numbers' => [['value' => '77799999', 'label' => 'work']],
    ]);

    $user = User::find(1);
    if (! $user) {
        $this->markTestSkipped('No hay usuario con ID 1.');
    }

    $this->actingAs($user, 'user')
        ->postJson(route('admin.contacts.persons.sync_smd', $person->id))
        ->assertStatus(422);
});
