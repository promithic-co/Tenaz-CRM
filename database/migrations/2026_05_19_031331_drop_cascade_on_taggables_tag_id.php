<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D7 — preserve pivot history when a Tag is soft-deleted.
     *
     * Phase 47 created `taggables.tag_id` with `cascadeOnDelete`, which means
     * a hard delete (or a future cleanup job) would also wipe every pivot
     * row that referenced the tag — destroying the IA audit trail that we
     * rely on for retreat / objection-analysis flows.
     *
     * Swap the FK to `restrictOnDelete`:
     * - Soft-delete on Tag is unaffected (it does not touch the FK).
     * - Force-delete a tag that still has pivots now raises a
     *   ForeignKeyViolation, surfacing operator intent.
     *
     * SQLite does not support altering foreign keys in place; the test suite
     * runs on SQLite via RefreshDatabase so the original FK is recreated
     * each run. Skipping the no-op there avoids spurious migration errors.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('taggables', function (Blueprint $table): void {
            try {
                $table->dropForeign(['tag_id']);
            } catch (QueryException) {
                // FK was already dropped (rerun safety).
            }
            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('taggables', function (Blueprint $table): void {
            try {
                $table->dropForeign(['tag_id']);
            } catch (QueryException) {
                // best-effort
            }
            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->cascadeOnDelete();
        });
    }
};
