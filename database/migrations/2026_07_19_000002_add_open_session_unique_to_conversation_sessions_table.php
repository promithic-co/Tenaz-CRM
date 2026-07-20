<?php

use App\Support\Database\BuildsIndexesConcurrently;
use Illuminate\Database\Migrations\Migration;

/**
 * Enforce at most one open ConversationSession per lead.
 *
 * A partial unique index on lead_id scoped to open, non-trashed rows — mirroring
 * leads_tenant_whatsapp_active_unique. The lifecycle service also serialises opens
 * behind a cache lock, but the index is the durable guarantee against a concurrent
 * webhook redelivery racing two opens for the same lead. Postgres/SQLite support
 * partial indexes natively; CONCURRENTLY (Postgres) requires the non-transactional
 * migration.
 */
return new class extends Migration
{
    use BuildsIndexesConcurrently;

    public $withinTransaction = false;

    private string $indexName = 'conversation_sessions_open_unique';

    public function up(): void
    {
        $this->createIndexConcurrently(
            'conversation_sessions',
            $this->indexName,
            ['lead_id'],
            unique: true,
            where: "status = 'open' AND deleted_at IS NULL",
        );
    }

    public function down(): void
    {
        $this->dropIndexConcurrently($this->indexName);
    }
};
