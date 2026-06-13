<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill taggables.source so every existing pivot row has an explicit
 * TaggableSource value. Previously the column was a free-form string default
 * 'manual'; nulls or empty values may exist from older rows or from inserts
 * that bypassed the default. Idempotent.
 *
 * No schema change: column remains string(32). A future hardening phase can
 * add a CHECK constraint (Postgres) once production traffic confirms the
 * enum coverage is exhaustive.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('taggables')
            ->where(function ($q): void {
                $q->whereNull('source')->orWhere('source', '');
            })
            ->update(['source' => 'manual']);
    }

    public function down(): void
    {
        // No-op: we never want to convert valid sources back to NULL.
    }
};
