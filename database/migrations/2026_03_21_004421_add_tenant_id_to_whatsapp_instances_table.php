<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('user_id')->index();
        });

        // Backfill: tenant_id = user_id (current 1:1 mapping)
        DB::statement('UPDATE whatsapp_instances SET tenant_id = CAST(user_id AS CHAR)');
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
