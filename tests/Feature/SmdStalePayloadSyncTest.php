<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Admin\Services\SmdAppointmentSyncService;

/**
 * Desde julio/2026 SMD escribe un archivo por cada cambio de la cita, en la carpeta
 * del día en curso, y NO borra los anteriores. La misma cita reaparece entonces en
 * varias carpetas con distintas versiones.
 *
 * Estos tests cubren que la versión más nueva siempre gane, sin importar el orden
 * en que Dropbox devuelva los archivos ni el rango de días que se recorra.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    Cache::flush();

    $stageId = DB::table('lead_pipeline_stages')->value('id') ?? 1;

    config([
        'smd.dropbox.access_token'        => 'fake-access-token',
        'smd.dropbox.refresh_token'       => null,
        'smd.dropbox.app_key'             => null,
        'smd.dropbox.app_secret'          => null,
        'smd.dropbox.appointments_folder' => '/smd-events',
        'smd.stage_map.confirmed'         => $stageId,
        'smd.stage_map.completed'         => $stageId,
        'smd.stage_map.cancelled'         => $stageId,
        'smd.stage_map.no_show'           => $stageId,
    ]);
});

/**
 * Payload con la forma real de SMD (ver planning/10-actualizacion-eventstatus-dropbox).
 * `$updatedAt` a null simula los archivos anteriores al cambio, que no traían el campo.
 */
function smdEvent(string $id, array $overrides = []): array
{
    return array_merge([
        '_id'         => $id,
        'eventId'     => '1783952187466',
        'archived'    => false,
        'status'      => '',
        'startDate'   => '2026-07-21T13:00:00.000Z',
        'endDate'     => '2026-07-21T14:00:00.000Z',
        'type'        => 'event',
        'summary'     => 'Creado 21 de Jul',
        'description' => '',
        'created_at'  => '2026-07-13T14:16:50.325Z',
        'updated_at'  => '2026-07-13T14:16:50.325Z',
        '__v'         => 0,
        'attendances' => [[
            '_id'      => 'pat-'.$id,
            'name'     => 'Juan',
            'lastName' => 'Perez',
            'fullName' => 'Juan Perez',
            'type'     => 'patient',
            'phone'    => '7'.rand(1000000, 9999999),
        ]],
        'owner' => [
            '_id'      => 'doc-'.$id,
            'name'     => 'Dr. Sync',
            'lastName' => 'Test',
            'phone'    => '',
        ],
    ], $overrides);
}

function fileEntry(string $date, string $hhmm, string $id): array
{
    $name = "event-{$date}-{$hhmm}-{$id}.json";

    return ['.tag' => 'file', 'name' => $name, 'path_lower' => "/smd-events/{$date}/{$name}"];
}

/**
 * Simula Dropbox: `$folders` mapea fecha => entries, `$payloads` mapea path => json.
 */
function fakeDropbox(array $folders, array $payloads): void
{
    // Http::fake() acumula stubs en vez de reemplazarlos, y el Factory está
    // bindeado como singleton: sin descartarlo, una segunda llamada en el mismo
    // test no tendría efecto porque ganaría el stub anterior.
    app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();

    Http::fake(function ($request) use ($folders, $payloads) {
        $url = $request->url();

        if (str_contains($url, 'oauth2/token')) {
            return Http::response(['access_token' => 'tok'], 200);
        }

        if (str_contains($url, 'files/list_folder')) {
            $path = $request->data()['path'] ?? '';
            $date = basename($path);
            $entries = $folders[$date] ?? [];

            return Http::response(['entries' => $entries, 'cursor' => 'c', 'has_more' => false], 200);
        }

        if (str_contains($url, 'files/download')) {
            $arg = json_decode($request->header('Dropbox-API-Arg')[0] ?? '{}', true);

            return Http::response(json_encode($payloads[$arg['path'] ?? ''] ?? []), 200);
        }

        return Http::response([], 200);
    });
}

function activityFor(string $externalId): ?object
{
    $row = DB::table('smd_synced_events')->where('external_id', $externalId)->first();

    return $row ? DB::table('activities')->find($row->activity_id) : null;
}

// ---------------------------------------------------------------------------

it('la modificación de hoy gana sobre el archivo de creación de ayer (regresión BUG-1)', function () {
    $id = 'ev-'.uniqid();

    $created = smdEvent($id);
    $updated = smdEvent($id, [
        'summary'     => 'Modificado 17 de Jul',
        'startDate'   => '2026-07-17T13:00:00.000Z',
        'updated_at'  => '2026-07-14T01:44:50.870Z',
        '__v'         => 4,
        'eventStatus' => 'UPDATED',
    ]);

    $fileCreated = fileEntry('2026-07-13', '1416', $id);
    $fileUpdated = fileEntry('2026-07-14', '0144', $id);

    fakeDropbox(
        ['2026-07-13' => [$fileCreated], '2026-07-14' => [$fileUpdated]],
        [$fileCreated['path_lower'] => $created, $fileUpdated['path_lower'] => $updated],
    );

    $service = app(SmdAppointmentSyncService::class);

    // Día 13: solo existe el archivo de creación.
    $service->run(1, null, \Carbon\Carbon::parse('2026-07-13'));
    expect(activityFor($id)->title)->toBe('Creado 21 de Jul');

    // Día 14: aparece la modificación. El cron recorre hoy + ayer.
    $summary = $service->run(2, null, \Carbon\Carbon::parse('2026-07-14'));

    // El archivo viejo del 13 sigue en Dropbox y no debe revertir la modificación.
    // Se procesa antes que el nuevo (orden cronológico) y como es idéntico a lo ya
    // aplicado cuenta como sin_cambios; el descarte por updated_at queda de red de
    // seguridad para cuando el orden no ayuda (ver test de orden inverso).
    expect(activityFor($id)->title)->toBe('Modificado 17 de Jul')
        ->and(activityFor($id)->schedule_from)->toContain('2026-07-17')
        ->and($summary['actualizados'])->toBe(1)
        ->and($summary['sin_cambios'])->toBe(1);
});

it('el archivo viejo no revierte la cita aunque el cron corra muchas veces', function () {
    $id = 'ev-'.uniqid();

    $created = smdEvent($id);
    $updated = smdEvent($id, [
        'summary'    => 'Modificado 17 de Jul',
        'startDate'  => '2026-07-17T13:00:00.000Z',
        'updated_at' => '2026-07-14T01:44:50.870Z',
    ]);

    $fileCreated = fileEntry('2026-07-13', '1416', $id);
    $fileUpdated = fileEntry('2026-07-14', '0144', $id);

    fakeDropbox(
        ['2026-07-13' => [$fileCreated], '2026-07-14' => [$fileUpdated]],
        [$fileCreated['path_lower'] => $created, $fileUpdated['path_lower'] => $updated],
    );

    $service = app(SmdAppointmentSyncService::class);

    foreach (range(1, 3) as $_) {
        $service->run(2, null, \Carbon\Carbon::parse('2026-07-14'));
    }

    expect(activityFor($id)->title)->toBe('Modificado 17 de Jul');
});

it('gana la versión más nueva aunque Dropbox devuelva los archivos en orden inverso', function () {
    $id = 'ev-'.uniqid();

    // Nombres deliberadamente NO ordenables cronológicamente: aquí solo puede
    // salvarnos el descarte por updated_at, no el ordenamiento por nombre.
    $viejo = ['.tag' => 'file', 'name' => 'zzz.json', 'path_lower' => '/smd-events/2026-07-14/zzz.json'];
    $nuevo = ['.tag' => 'file', 'name' => 'aaa.json', 'path_lower' => '/smd-events/2026-07-14/aaa.json'];

    fakeDropbox(
        ['2026-07-14' => [$nuevo, $viejo]],
        [
            $nuevo['path_lower'] => smdEvent($id, [
                'summary'    => 'Version nueva',
                'updated_at' => '2026-07-14T11:27:26.448Z',
                '__v'        => 5,
            ]),
            $viejo['path_lower'] => smdEvent($id, [
                'summary'    => 'Version vieja',
                'updated_at' => '2026-07-13T14:16:50.325Z',
                '__v'        => 0,
            ]),
        ],
    );

    $summary = app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    expect(activityFor($id)->title)->toBe('Version nueva')
        ->and($summary['obsoletos'])->toBe(1);
});

it('procesa los archivos legacy que no traen updated_at ni eventStatus', function () {
    $id = 'ev-'.uniqid();

    $legacy = smdEvent($id, ['summary' => 'Cita legacy']);
    unset($legacy['updated_at']);

    $file = fileEntry('2026-07-14', '1416', $id);

    fakeDropbox(['2026-07-14' => [$file]], [$file['path_lower'] => $legacy]);

    $summary = app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    expect($summary['creados'])->toBe(1)
        ->and($summary['errores'])->toBe(0)
        ->and(activityFor($id)->title)->toBe('Cita legacy');

    // Sin updated_at no hay referencia que guardar, pero la cita se sincroniza igual.
    $row = DB::table('smd_synced_events')->where('external_id', $id)->first();
    expect($row->source_updated_at)->toBeNull();
});

it('un archivo legacy sin updated_at no borra la referencia ya guardada', function () {
    $id = 'ev-'.uniqid();

    $nuevo = smdEvent($id, ['summary' => 'Version nueva', 'updated_at' => '2026-07-14T11:27:26.448Z']);
    $legacy = smdEvent($id, ['summary' => 'Legacy sin fecha']);
    unset($legacy['updated_at']);

    $fileNuevo = fileEntry('2026-07-14', '1127', $id);
    $fileLegacy = fileEntry('2026-07-14', '1128', $id);

    fakeDropbox(
        ['2026-07-14' => [$fileNuevo, $fileLegacy]],
        [$fileNuevo['path_lower'] => $nuevo, $fileLegacy['path_lower'] => $legacy],
    );

    app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    $row = DB::table('smd_synced_events')->where('external_id', $id)->first();

    expect($row->source_updated_at)->not->toBeNull();
});

it('una cancelación que además edita el título aplica ambas cosas', function () {
    $id = 'ev-'.uniqid();

    // 1. La cita se crea normal.
    $creado = smdEvent($id, ['summary' => 'test', 'updated_at' => '2026-07-14T11:57:59.765Z']);
    $fileCreado = fileEntry('2026-07-14', '1157', $id);

    fakeDropbox(['2026-07-14' => [$fileCreado]], [$fileCreado['path_lower'] => $creado]);
    app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    $row = DB::table('smd_synced_events')->where('external_id', $id)->first();
    expect(activityFor($id)->title)->toBe('test');

    // 2. En SMD se cancela la cita y se le edita el título en el mismo movimiento.
    //    SMD manda un solo archivo con status=CANCELED y el summary nuevo.
    $cancelado = smdEvent($id, [
        'summary'     => 'test editado',
        'status'      => 'CANCELED',
        'updated_at'  => '2026-07-14T13:10:02.827Z',
        'eventStatus' => 'UPDATED',
        '__v'         => 2,
    ]);
    $fileCancelado = fileEntry('2026-07-14', '1310', $id);

    fakeDropbox(
        ['2026-07-14' => [$fileCreado, $fileCancelado]],
        [$fileCreado['path_lower'] => $creado, $fileCancelado['path_lower'] => $cancelado],
    );

    $summary = app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    expect($summary['cancelados'])->toBe(1);

    // El lead queda cerrado...
    $lead = DB::table('leads')->find($row->lead_id);
    expect((int) $lead->lead_pipeline_stage_id)->toBe((int) config('smd.stage_map.cancelled'))
        ->and((int) $lead->status)->toBe(0);

    // ...y la edición del título NO se pierde.
    expect(activityFor($id)->title)->toBe('test editado');

    // El payload guardado refleja la última versión, no la anterior.
    $row = DB::table('smd_synced_events')->where('external_id', $id)->first();
    expect(json_decode($row->raw_payload, true)['__v'])->toBe(2)
        ->and($row->status)->toBe('CANCELED')
        ->and($row->archived_at)->not->toBeNull();
});

it('una cita ya cancelada no se vuelve a cancelar en cada corrida del cron', function () {
    $id = 'ev-'.uniqid();

    $creado = smdEvent($id, ['summary' => 'test', 'updated_at' => '2026-07-14T11:57:59.765Z']);
    $fileCreado = fileEntry('2026-07-14', '1157', $id);

    fakeDropbox(['2026-07-14' => [$fileCreado]], [$fileCreado['path_lower'] => $creado]);
    app(SmdAppointmentSyncService::class)->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    $cancelado = smdEvent($id, [
        'summary'    => 'test',
        'status'     => 'CANCELED',
        'updated_at' => '2026-07-14T13:10:02.827Z',
    ]);
    $fileCancelado = fileEntry('2026-07-14', '1310', $id);

    fakeDropbox(
        ['2026-07-14' => [$fileCreado, $fileCancelado]],
        [$fileCreado['path_lower'] => $creado, $fileCancelado['path_lower'] => $cancelado],
    );

    $service = app(SmdAppointmentSyncService::class);

    // Primera corrida: cancela de verdad.
    expect($service->run(1, null, \Carbon\Carbon::parse('2026-07-14'))['cancelados'])->toBe(1);

    $archivedAt = DB::table('smd_synced_events')->where('external_id', $id)->value('archived_at');

    // Segunda corrida, sin cambios en Dropbox: no debe volver a cancelar
    // ni reescribir archived_at.
    $summary = $service->run(1, null, \Carbon\Carbon::parse('2026-07-14'));

    // El archivo de cancelación queda idéntico a lo guardado (sin_cambios) y el de
    // creación queda por detrás en updated_at (obsoletos). Ninguno vuelve a cancelar.
    expect($summary['cancelados'])->toBe(0)
        ->and($summary['sin_cambios'])->toBe(1)
        ->and($summary['obsoletos'])->toBe(1)
        ->and(DB::table('smd_synced_events')->where('external_id', $id)->value('archived_at'))->toBe($archivedAt);
});
