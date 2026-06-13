<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite index `leads_smart_list_idx (tenant_id, status, last_interaction_at)`
 * to support efficient smart list filter queries (Phase 51-10).
 *
 * Without this index, resolver queries on tenants with 10k+ leads do full table scans.
 * The existing separate `tenant_id` (from unique constraint) and `status` indexes are
 * insufficient for multi-column filter predicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'status', 'last_interaction_at'],
                'leads_smart_list_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_smart_list_idx');
        });
    }
};
