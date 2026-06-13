<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agent_configs', 'tenant_id')) {
            Schema::table('agent_configs', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->after('agent_id');
            });
        }

        // Backfill: copy tenant_id from the parent agent (SQLite-compatible subquery)
        DB::statement('
            UPDATE agent_configs
            SET tenant_id = (
                SELECT tenant_id FROM agents WHERE agents.id = agent_configs.agent_id
            )
            WHERE tenant_id IS NULL
        ');

        Schema::table('agent_configs', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
