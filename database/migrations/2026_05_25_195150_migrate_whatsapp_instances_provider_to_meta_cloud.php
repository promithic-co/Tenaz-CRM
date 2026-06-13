<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate all whatsapp_instances.provider from 'evolution' to 'meta_cloud'
     * and change the column default. Evolution API support has been removed.
     */
    public function up(): void
    {
        // Re-point any existing evolution instances to meta_cloud.
        DB::table('whatsapp_instances')
            ->where('provider', 'evolution')
            ->update(['provider' => 'meta_cloud']);

        // Change column default so new rows land on meta_cloud.
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('provider', 20)->default('meta_cloud')->change();
        });

        // Migrate evolution_preset templates to meta_hsm — EvolutionPreset kind removed.
        DB::table('whatsapp_templates')
            ->where('kind', 'evolution_preset')
            ->update(['kind' => 'meta_hsm']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('provider', 20)->default('evolution')->change();
        });
    }
};
