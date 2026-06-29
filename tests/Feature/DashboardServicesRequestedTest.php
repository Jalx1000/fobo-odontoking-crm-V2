<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * El nuevo card "Servicios solicitados" muestra las métricas de TODOS los
 * servicios pedidos sin importar la fecha. El endpoint debe responder 200 con
 * una lista (statistics) y un total agregado.
 */
it('el endpoint services-requested responde 200 con statistics y total', function () {
    $response = $this->actingAs(User::find(1), 'user')
        ->getJson(route('admin.dashboard.stats', ['type' => 'services-requested']))
        ->assertStatus(200);

    $payload = $response->json('statistics');

    expect($payload)->toHaveKeys(['statistics', 'total']);
    expect($payload['statistics'])->toBeArray();
    expect($payload['total'])->toBeInt();

    // El total debe ser la suma de las cantidades de cada servicio listado.
    $sum = array_sum(array_column($payload['statistics'], 'total_qty_ordered'));
    expect($payload['total'])->toBe($sum);

    // Cada servicio debe exponer las claves que consume la torta (segmentos y
    // leyenda): id, name, total_qty_ordered y leads_count.
    foreach ($payload['statistics'] as $stat) {
        expect($stat)->toHaveKeys(['id', 'name', 'total_qty_ordered', 'leads_count']);
    }
});

/**
 * Es "all-time": el rango de fechas NO debe alterar el resultado. Un rango de un
 * solo día y uno de cinco años deben producir exactamente lo mismo.
 */
it('services-requested ignora el filtro de fechas', function () {
    $narrow = $this->actingAs(User::find(1), 'user')
        ->getJson(route('admin.dashboard.stats', [
            'type'  => 'services-requested',
            'start' => now()->format('Y-m-d'),
            'end'   => now()->format('Y-m-d'),
        ]))->json('statistics');

    $wide = $this->actingAs(User::find(1), 'user')
        ->getJson(route('admin.dashboard.stats', [
            'type'  => 'services-requested',
            'start' => now()->subYears(5)->format('Y-m-d'),
            'end'   => now()->format('Y-m-d'),
        ]))->json('statistics');

    expect($narrow)->toEqual($wide);
});

/**
 * El card over-all debe exponer total_leads (fuente del nuevo indicador
 * "Total de Citas"), separado de total_persons ("Total de Pacientes").
 */
it('over-all expone total_leads y total_persons por separado', function () {
    $stats = $this->actingAs(User::find(1), 'user')
        ->getJson(route('admin.dashboard.stats', ['type' => 'over-all']))
        ->assertStatus(200)
        ->json('statistics');

    expect($stats)->toHaveKeys(['total_leads', 'total_persons']);
    expect($stats['total_leads'])->toHaveKey('current');
    expect($stats['total_persons'])->toHaveKey('current');
});
