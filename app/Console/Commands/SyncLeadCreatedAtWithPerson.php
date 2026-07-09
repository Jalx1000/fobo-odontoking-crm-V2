<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea leads.created_at con persons.created_at para los pedidos de un mes.
 *
 * El tablero cuenta todo por created_at; este comando hace que cada pedido tenga
 * exactamente la misma fecha/hora de creación que su persona asociada, para que
 * el módulo Pedidos y el módulo Contactos cuadren día por día.
 *
 * Uso:
 *   php artisan leads:sync-created-at                 # dry-run del mes actual
 *   php artisan leads:sync-created-at --month=2026-07 # dry-run de julio 2026
 *   php artisan leads:sync-created-at --month=2026-07 --apply   # ejecuta
 *
 * Seguro por defecto: sin --apply solo muestra qué cambiaría. Con --apply
 * actualiza SOLO la columna created_at (preserva updated_at) dentro de una
 * transacción, y deja un respaldo JSON en storage/app para poder revertir.
 */
class SyncLeadCreatedAtWithPerson extends Command
{
    protected $signature = 'leads:sync-created-at
        {--month= : Mes objetivo en formato YYYY-MM (por defecto, el mes actual)}
        {--apply : Ejecuta los cambios (sin esta bandera solo hace dry-run)}';

    protected $description = 'Iguala leads.created_at a persons.created_at para los pedidos del mes indicado';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Formato de --month inválido: '{$month}'. Usa YYYY-MM, p. ej. 2026-07.");

            return self::FAILURE;
        }

        $start = "{$month}-01 00:00:00";
        $end = date('Y-m-t 23:59:59', strtotime($start));

        $hasSoftDeletes = Schema::hasColumn('leads', 'deleted_at');

        // Filas que difieren: el pedido cae en el mes y su persona tiene otra fecha.
        $rows = DB::table('leads')
            ->join('persons', 'leads.person_id', '=', 'persons.id')
            ->whereBetween('leads.created_at', [$start, $end])
            ->whereColumn('leads.created_at', '!=', 'persons.created_at')
            ->when($hasSoftDeletes, fn ($q) => $q->whereNull('leads.deleted_at'))
            ->select(
                'leads.id as lead_id',
                'leads.created_at as lead_created_at',
                'persons.id as person_id',
                'persons.created_at as person_created_at'
            )
            ->orderBy('leads.id')
            ->get();

        $this->info("Mes objetivo: {$month}  ({$start} → {$end})");
        $this->info('Pedidos que difieren de su persona: '.$rows->count());

        if ($rows->isEmpty()) {
            $this->info('Nada que actualizar. Todo ya está alineado.');

            return self::SUCCESS;
        }

        $this->table(
            ['lead_id', 'person_id', 'de (lead)', 'a (person)'],
            $rows->take(50)->map(fn ($r) => [
                $r->lead_id, $r->person_id, $r->lead_created_at, $r->person_created_at,
            ])->all()
        );

        if ($rows->count() > 50) {
            $this->line('… y '.($rows->count() - 50).' más.');
        }

        if (! $this->option('apply')) {
            $this->warn('DRY-RUN: no se cambió nada. Vuelve a ejecutar con --apply para aplicar.');

            return self::SUCCESS;
        }

        // Respaldo por si acaso (además del que ya tengas).
        $backupPath = storage_path('app/leads_created_at_backup_'.now()->format('Ymd_His').'.json');
        file_put_contents($backupPath, $rows->toJson(JSON_PRETTY_PRINT));
        $this->info("Respaldo escrito en: {$backupPath}");

        $updated = 0;

        DB::transaction(function () use ($rows, &$updated) {
            foreach ($rows as $r) {
                // DB::table (no Eloquent) para NO tocar updated_at.
                $updated += DB::table('leads')
                    ->where('id', $r->lead_id)
                    ->update(['created_at' => $r->person_created_at]);
            }
        });

        $this->info("✔ Pedidos actualizados: {$updated}");

        return self::SUCCESS;
    }
}
