<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * On PostgreSQL the index is built CONCURRENTLY, which cannot run inside a
     * transaction, so migration wrapping must be disabled for this driver.
     */
    public $withinTransaction = false;

    private string $table = 'agent_interaction_events';

    private string $indexName = 'agent_interaction_events_lead_time_idx';

    public function up(): void
    {
        if ($this->isPostgres()) {
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$this->indexName} ON {$this->table} (lead_id, created_at)");

            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            $table->index(['lead_id', 'created_at'], $this->indexName);
        });
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$this->indexName}");

            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            $table->dropIndex($this->indexName);
        });
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
