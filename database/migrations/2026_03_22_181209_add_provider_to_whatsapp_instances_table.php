<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('provider', 20)->default('meta_cloud')->after('api_key');
        });

        DB::table('whatsapp_instances')
            ->whereNull('provider')
            ->orWhere('provider', '')
            ->update(['provider' => 'meta_cloud']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
