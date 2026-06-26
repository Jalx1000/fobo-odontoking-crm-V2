<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza citas desde la carpeta Dropbox de ShareMeData hacia el CRM.
 *
 * Recorre los archivos de los últimos N días, los compara contra
 * `smd_synced_events` (idempotente vía hash del payload) y crea, actualiza o
 * cancela las citas correspondientes mediante IncomingAppointmentService.
 *
 * Es la fuente única de la lógica de sync: la usan tanto el comando
 * `smd:sync-dropbox` como el Job disparado desde el calendario.
 */
class SmdAppointmentSyncService
{
    public function __construct(
        protected DropboxService $dropbox,
        protected IncomingAppointmentService $incoming,
        protected SmdStatusMapper $mapper,
    ) {}

    /**
     * @param  int  $days  Cantidad de días hacia atrás a revisar (incluye la fecha base).
     * @param  callable|null  $onLog  Callback opcional para emitir progreso (usado por el comando).
     * @param  Carbon|null  $baseDate  Fecha base; por defecto hoy. La revisión va hacia atrás desde aquí.
     * @return array{creados:int, actualizados:int, cancelados:int, sin_cambios:int, errores:int}
     */
    public function run(int $days = 1, ?callable $onLog = null, ?Carbon $baseDate = null): array
    {
        $days = max(1, $days);

        $summary = [
            'creados'     => 0,
            'actualizados' => 0,
            'cancelados'  => 0,
            'sin_cambios' => 0,
            'errores'     => 0,
        ];

        $baseDate ??= now();

        for ($i = 0; $i < $days; $i++) {
            $date  = $baseDate->copy()->subDays($i)->toDateString();
            $files = $this->dropbox->listFilesForDate($date);

            if ($onLog) {
                $onLog("{$date} — " . count($files) . ' archivos');
            }

            foreach ($files as $file) {
                $this->processFile($file, $summary, $onLog);
            }
        }

        Log::info('[SmdAppointmentSync] Sincronización completada', $summary + ['days' => $days]);

        return $summary;
    }

    /**
     * Procesa un archivo individual de Dropbox, actualizando el resumen por referencia.
     */
    protected function processFile(array $file, array &$summary, ?callable $onLog): void
    {
        try {
            $json = $this->dropbox->downloadJson($file['path_lower']);

            if (! $json || ! isset($json['_id'])) {
                Log::warning('[SmdAppointmentSync] JSON sin _id o inválido', [
                    'file' => $file['path_lower'] ?? '?',
                ]);
                $summary['errores']++;

                return;
            }

            $externalId   = $json['_id'];
            $status       = $json['status']   ?? '';
            $shouldCancel = ($json['archived'] ?? false) || $this->mapper->isCancelled($status);

            $existing = DB::table('smd_synced_events')
                ->where('external_id', $externalId)
                ->first();

            if ($shouldCancel) {
                if ($existing) {
                    $this->incoming->cancelDropbox($externalId, $existing);
                    DB::table('smd_synced_events')
                        ->where('external_id', $externalId)
                        ->update(['status' => $status, 'archived_at' => now(), 'updated_at' => now()]);
                }
                $summary['cancelados']++;

                return;
            }

            if ($existing) {
                if (md5($existing->raw_payload ?? '') === md5(json_encode($json))) {
                    $summary['sin_cambios']++;

                    return;
                }

                $this->incoming->updateDropbox($json, $existing);
                DB::table('smd_synced_events')
                    ->where('external_id', $externalId)
                    ->update(['raw_payload' => json_encode($json), 'status' => $status, 'updated_at' => now()]);
                $summary['actualizados']++;

                return;
            }

            $result = $this->incoming->processDropbox($json);
            DB::table('smd_synced_events')->insert([
                'external_id'  => $externalId,
                'source_file'  => $file['path_lower'],
                'activity_id'  => $result['activity_id'] ?? null,
                'lead_id'      => $result['lead_id']      ?? null,
                'raw_payload'  => json_encode($json),
                'status'       => $status,
                'processed_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $summary['creados']++;
        } catch (\Throwable $e) {
            Log::error('[SmdAppointmentSync] Error procesando archivo', [
                'file'  => $file['path_lower'] ?? '?',
                'error' => $e->getMessage(),
            ]);

            if ($onLog) {
                $onLog('  ✗ ' . ($file['path_lower'] ?? '?') . ': ' . $e->getMessage());
            }

            $summary['errores']++;
        }
    }
}
