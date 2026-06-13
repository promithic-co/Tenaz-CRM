<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 46 - CRM-first conversation architecture.
     *
     * Drops the leads.agent_id NOT NULL constraint (Postgres/MySQL — SQLite is
     * already nullable) and replaces the agent-scoped unique key with a
     * tenant+phone uniqueness rule that ignores soft-deleted rows. A lead
     * represents one active conversation per WhatsApp number per tenant — agent
     * binding can change later (or never happen for CRM-only operation).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        $this->dropLegacyLeadUniques();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN agent_id DROP NOT NULL');
            DB::statement(
                'CREATE UNIQUE INDEX leads_tenant_whatsapp_active_unique ON leads (tenant_id, whatsapp) WHERE deleted_at IS NULL'
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL has no partial unique indexes — fall back to including
            // deleted_at in the key. Soft-deleted rows get a non-null deleted_at,
            // so they no longer collide with a freshly created active row.
            Schema::table('leads', function (Blueprint $table): void {
                $table->foreignId('agent_id')->nullable()->change();
                $table->unique(['tenant_id', 'whatsapp', 'deleted_at'], 'leads_tenant_whatsapp_active_unique');
            });
        } else {
            // SQLite: agent_id already nullable in this codebase. SQLite supports
            // partial indexes natively.
            DB::statement(
                'CREATE UNIQUE INDEX leads_tenant_whatsapp_active_unique ON leads (tenant_id, whatsapp) WHERE deleted_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS leads_tenant_whatsapp_active_unique');
            } else {
                Schema::table('leads', function (Blueprint $table): void {
                    $table->dropUnique('leads_tenant_whatsapp_active_unique');
                });
            }
        } catch (QueryException) {
            // best-effort
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN agent_id SET NOT NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('leads', function (Blueprint $table): void {
                $table->foreignId('agent_id')->nullable(false)->change();
            });
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'agent_id', 'whatsapp'], 'leads_tenant_agent_whatsapp_unique');
        });
    }

    private function dropLegacyLeadUniques(): void
    {
        foreach (['leads_tenant_agent_whatsapp_unique', 'leads_tenant_id_whatsapp_unique'] as $name) {
            try {
                Schema::table('leads', function (Blueprint $table) use ($name): void {
                    $table->dropUnique($name);
                });
            } catch (QueryException) {
                // index may not exist on this environment
            }
        }
    }
};
