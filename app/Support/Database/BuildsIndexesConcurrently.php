<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Builds and drops indexes without locking out writes on PostgreSQL (GROW-5).
 *
 * A plain CREATE INDEX takes a write lock for the duration of the build; on a large
 * table (campaign_messages, leads, agent_*_messages) a deploy migration would block
 * inbound writes the entire time. PostgreSQL's CREATE INDEX CONCURRENTLY avoids that
 * lock but cannot run inside a transaction — so every migration using this trait MUST
 * also declare `public $withinTransaction = false;` (PHP forbids a trait from overriding
 * the parent Migration's typed property, so it cannot be supplied here).
 *
 * SQLite (test/local) has neither CONCURRENTLY nor the locking concern, so it gets the
 * plain statement with the same DDL shape — including partial-index WHERE predicates,
 * which SQLite supports natively — so the test suite enforces the exact prod constraint.
 */
trait BuildsIndexesConcurrently
{
    /**
     * @param  list<string>  $columns
     */
    protected function createIndexConcurrently(string $table, string $name, array $columns, bool $unique = false, ?string $where = null): void
    {
        $uniqueClause = $unique ? 'UNIQUE ' : '';
        $concurrentlyClause = $this->isPostgresIndexDriver() ? 'CONCURRENTLY ' : '';
        $columnList = implode(', ', $columns);
        $predicate = $where !== null ? " WHERE {$where}" : '';

        DB::statement("CREATE {$uniqueClause}INDEX {$concurrentlyClause}IF NOT EXISTS {$name} ON {$table} ({$columnList}){$predicate}");
    }

    protected function dropIndexConcurrently(string $name): void
    {
        $concurrentlyClause = $this->isPostgresIndexDriver() ? 'CONCURRENTLY ' : '';

        DB::statement("DROP INDEX {$concurrentlyClause}IF EXISTS {$name}");
    }

    private function isPostgresIndexDriver(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
