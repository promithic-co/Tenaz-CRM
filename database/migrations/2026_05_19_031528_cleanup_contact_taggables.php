<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * D1 — tags live on Lead only.
     *
     * Removes any taggables pivot rows that were attached to Contact models.
     * Production currently has zero such rows (Phase 47 just shipped), but
     * staging / local DBs and any test seeders may have planted them.
     *
     * Down is a no-op — we do not synthesize contact taggables.
     */
    public function up(): void
    {
        DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Contact')
            ->delete();
    }

    public function down(): void
    {
        // Not recoverable — historical contact tag links are not retained.
    }
};
