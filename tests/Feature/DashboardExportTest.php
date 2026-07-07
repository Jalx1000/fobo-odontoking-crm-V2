<?php

/**
 * Dashboard (Tablero) export. Requires a database connection with the default
 * pipelines/stages seeded, since the summary sheet reads live reporting.
 */
it('downloads the dashboard export as xlsx for the active date range', function () {
    test()->actingAs(getDefaultAdmin());

    $response = test()->get(route('admin.dashboard.export', [
        'format' => 'xlsx',
        'start'  => now()->subMonth()->toDateString(),
        'end'    => now()->toDateString(),
    ]));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('falls back to a single-sheet csv export', function () {
    test()->actingAs(getDefaultAdmin());

    $response = test()->get(route('admin.dashboard.export', ['format' => 'csv']));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.csv');
});

it('defaults an invalid format to xlsx', function () {
    test()->actingAs(getDefaultAdmin());

    $response = test()->get(route('admin.dashboard.export', ['format' => 'exe']));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});
