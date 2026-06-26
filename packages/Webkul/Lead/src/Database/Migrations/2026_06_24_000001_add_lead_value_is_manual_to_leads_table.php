<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // When true, lead_value was set manually by a privileged user
            // (Supervisor/Admin) and must NOT be auto-recalculated from products.
            $table->boolean('lead_value_is_manual')->default(false)->after('lead_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_value_is_manual');
        });
    }
};
