<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
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
        $defaultAgentId = DB::table('agents')->orderBy('id')->value('id');
        if ($defaultAgentId) {
            DB::table('whatsapp_instances')->whereNull('agent_id')->update(['agent_id' => $defaultAgentId]);
            DB::table('leads')->whereNull('agent_id')->update(['agent_id' => $defaultAgentId]);
        }

        $this->dropLeadsUniqueConstraint();

        Schema::table('leads', function (Blueprint $table) {
            $table->unique(['tenant_id', 'agent_id', 'whatsapp'], 'leads_tenant_agent_whatsapp_unique');
        });

        // whatsapp_instances: no unique on agent_id (one agent can have multiple instances)

        if ($this->isSqlite()) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
        });

        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->isSqlite()) {
            Schema::table('whatsapp_instances', function (Blueprint $table) {
                $table->foreignId('agent_id')->nullable()->change();
            });

            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('agent_id')->nullable()->change();
            });
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_tenant_agent_whatsapp_unique');
            $table->unique(['tenant_id', 'whatsapp']);
        });
    }

    private function dropLeadsUniqueConstraint(): void
    {
        try {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'whatsapp']);
            });
        } catch (QueryException) {
            // Old environments may have a different generated index name.
            // We continue and rely on the new unique key to enforce correctness.
        }
    }

    private function isSqlite(): bool
    {
        /** @var Connection $connection */
        $connection = DB::connection();

        return $connection->getDriverName() === 'sqlite';
    }
};
