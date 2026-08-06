<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mirror of last_inbound_at, so "is anyone waiting on us?" is a column comparison.
 *
 * The inbox tab badges count conversations whose last message came from the customer.
 * Deriving that per row means a correlated subquery against the timeline for every lead
 * in every one of the five tab counters on every page load; denormalising it costs one
 * nullable timestamp and matches how last_inbound_at already works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('last_outbound_at')->nullable()->after('last_inbound_at');
        });

        // Backfilled from the timeline rather than left null: without this every existing
        // conversation reads as waiting on a reply, and the badges launch wrong.
        DB::table('leads')->update([
            'last_outbound_at' => DB::raw(
                '(select max(created_at) from conversation_timeline_messages'
                ." where conversation_timeline_messages.lead_id = leads.id and direction = 'outbound')"
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('last_outbound_at');
        });
    }
};
