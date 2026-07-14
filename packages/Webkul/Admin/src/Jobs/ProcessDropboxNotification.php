<?php

namespace Webkul\Admin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Services\DropboxService;
use Webkul\Admin\Services\SmdAppointmentSyncService;

/**
 * Procesa las notificaciones del webhook de Dropbox drenando el cursor.
 *
 * Es único: Dropbox notifica cada cambio y una sola corrida drena todos los
 * pendientes vía `has_more`, así que un segundo job simultáneo solo duplicaría
 * trabajo sobre el mismo cursor.
 */
class ProcessDropboxNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** Si el job muere sin liberar el lock, se libera solo pasado este tiempo. */
    public int $uniqueFor = 300;

    public function handle(DropboxService $dropbox, SmdAppointmentSyncService $sync): void
    {
        $cursor = DB::table('settings')
            ->where('key', 'dropbox_cursor')
            ->value('value');

        if (! $cursor) {
            Log::warning('[DropboxWebhook] No hay cursor guardado. Ejecuta: php artisan smd:dropbox-init-cursor');

            return;
        }

        $summary = [
            'creados'      => 0,
            'actualizados' => 0,
            'cancelados'   => 0,
            'sin_cambios'  => 0,
            'obsoletos'    => 0,
            'errores'      => 0,
        ];

        do {
            $result = $dropbox->listChanges($cursor);
            $entries = $result['entries'];
            $cursor = $result['cursor'];
            $hasMore = $result['has_more'];

            // Los archivos llegan en el orden en que Dropbox registró los cambios;
            // ordenarlos por nombre (`event-YYYY-MM-DD-HHMM-<id>`) los deja en orden
            // cronológico dentro del lote. El descarte por `updated_at` en
            // processPayload() cubre el resto.
            usort($entries, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

            foreach ($entries as $file) {
                try {
                    $json = $dropbox->downloadJson($file['path_lower']);

                    if (! $json || ! isset($json['_id'])) {
                        Log::warning('[DropboxWebhook] JSON sin _id o invalido', [
                            'file' => $file['path_lower'] ?? '?',
                        ]);
                        $summary['errores']++;

                        continue;
                    }

                    $summary[$sync->processPayload($json, $file['path_lower'])]++;
                } catch (\Throwable $e) {
                    Log::error('[DropboxWebhook] Error procesando archivo', [
                        'file'  => $file['path_lower'] ?? '?',
                        'error' => $e->getMessage(),
                    ]);
                    $summary['errores']++;
                }
            }

            DB::table('settings')->updateOrInsert(
                ['key' => 'dropbox_cursor'],
                ['value' => $cursor, 'updated_at' => now()]
            );

        } while ($hasMore);

        Log::info('[DropboxWebhook] Procesamiento completado', $summary);
    }
}
