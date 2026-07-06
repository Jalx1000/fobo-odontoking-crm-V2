<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->datetime('first_stage_change_at')->nullable()->after('confirmed_at');
        });

        /**
         * Backfill: la 1ª transición real de cada lead que ya salió de Prospecto.
         * confirmed_at viene antes que closed_at en el flujo, así que COALESCE da la
         * primera. Leads aún en Prospecto quedan NULL -> usan NOW() en el reporting.
         */
        DB::statement('UPDATE '.DB::getTablePrefix().'leads
            SET first_stage_change_at = COALESCE(confirmed_at, closed_at)
            WHERE first_stage_change_at IS NULL
              AND (confirmed_at IS NOT NULL OR closed_at IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('first_stage_change_at');
        });
    }
};
