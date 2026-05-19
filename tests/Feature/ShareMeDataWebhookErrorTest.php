<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\postJson;

uses(DatabaseTransactions::class);

it('rechaza webhook con token inválido cuando el secret está configurado', function () {
    Config::set('services.sharemedata.webhook_secret', 'mi-secret-seguro');

    postJson(route('admin.webhook.sharemedata'), [
        'physician' => ['_id' => 'doc1'],
        'patient'   => ['phone' => '70000001'],
    ], ['X-Webhook-Secret' => 'token-incorrecto'])
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthorized']);
});

it('permite webhook sin secret cuando no hay configuración de token', function () {
    Config::set('services.sharemedata.webhook_secret', null);

    // Sin token pero con payload incompleto (falta slot) → debe fallar en lógica, no en auth
    $response = postJson(route('admin.webhook.sharemedata'), [
        'physician' => ['_id' => 'doc_inexistente_'.uniqid()],
        'patient'   => ['phone' => '70000001', 'name' => 'Paciente', 'lastName' => 'Test'],
        'specialty' => 'General',
        'summary'   => 'Test sin slot',
        // 'slot' faltante → debe producir error 500 por Carbon::parse(null)
    ]);

    // No debe ser 401 (auth pasó)
    expect($response->status())->not->toBe(401);
});

it('retorna 422 cuando el payload no tiene physician._id', function () {
    Config::set('services.sharemedata.webhook_secret', null);

    postJson(route('admin.webhook.sharemedata'), [
        'patient' => ['phone' => '70000001'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['physician._id']);
});

it('retorna 422 cuando el payload no tiene patient.phone', function () {
    Config::set('services.sharemedata.webhook_secret', null);

    postJson(route('admin.webhook.sharemedata'), [
        'physician' => ['_id' => 'doc1'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['patient.phone']);
});

it('acepta webhook con token correcto y crea entidades', function () {
    Config::set('services.sharemedata.webhook_secret', 'secreto-valido');

    $uniqueId = 'doc-test-'.uniqid();

    $payload = [
        'physician' => [
            '_id'      => $uniqueId,
            'name'     => 'Doctor',
            'lastName' => 'Webhook',
            'email'    => 'webhook.'.uniqid().'@test.com',
        ],
        'patient' => [
            'phone'    => '7'.rand(1000000, 9999999),
            'name'     => 'Paciente',
            'lastName' => 'Webhook',
        ],
        'specialty' => 'General',
        'summary'   => 'Cita de prueba webhook',
        'slot'      => [
            'start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'end'   => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
        ],
        '_id' => 'ext-'.uniqid(),
    ];

    postJson(route('admin.webhook.sharemedata'), $payload, ['X-Webhook-Secret' => 'secreto-valido'])
        ->assertStatus(201)
        ->assertJsonStructure(['success', 'lead_id', 'activity_id']);
});
