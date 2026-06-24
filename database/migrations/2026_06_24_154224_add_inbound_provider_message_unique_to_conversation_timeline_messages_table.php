<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * ATOM-2: durable inbound dedup guarantee.
 *
 * Inbound dedup previously relied solely on a Redis lock plus a non-transactional
 * SELECT before insert. If the lock degrades or expires under webhook-retry
 * pressure, two redeliveries of the same provider_message_id both pass the check
 * and double-insert the inbound row (and re-fire campaign/automation). This adds a
 * partial unique index on (tenant_id, provider_message_id) scoped to inbound rows
 * with a non-null provider id — mirroring leads_tenant_whatsapp_active_unique — so
 * the database itself rejects the duplicate. The persister catches the violation
 * and resolves to the existing row instead of failing the job.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private string $table = 'conversation_timeline_messages';

    private string $indexName = 'ctm_tenant_provider_inbound_unique';

    public function up(): void
    {
        if ($this->isPostgres()) {
            // CONCURRENTLY cannot run inside a transaction (hence $withinTransaction = false);
            // builds the index without locking out inbound writes on a large table.
            DB::statement(
                "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS {$this->indexName} ON {$this->table} (tenant_id, provider_message_id) WHERE direction = 'inbound' AND provider_message_id IS NOT NULL"
            );

            return;
        }

        // SQLite supports partial indexes natively; same predicate keeps the test
        // suite enforcing the exact production constraint.
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS {$this->indexName} ON {$this->table} (tenant_id, provider_message_id) WHERE direction = 'inbound' AND provider_message_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS {$this->indexName}");
        } catch (QueryException) {
            // best-effort
        }
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
