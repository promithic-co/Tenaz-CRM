<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_timeline_messages', function (Blueprint $table) {
            // NULL = row not yet mirrored into agent_conversation_messages (agent has
            // never seen it). Non-null = synced; the agent has this context in memory.
            $table->timestamp('synced_to_agent_at')->nullable()->after('provider_message_id');
            $table->index(['lead_id', 'synced_to_agent_at'], 'timeline_lead_synced_idx');
        });

        // Backfill: every existing row is assumed already mirrored (or unrecoverable),
        // so mark synced_to_agent_at = created_at. The synchronizer will only pick up
        // new rows written after this migration.
        DB::table('conversation_timeline_messages')
            ->whereNull('synced_to_agent_at')
            ->update(['synced_to_agent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('conversation_timeline_messages', function (Blueprint $table) {
            $table->dropIndex('timeline_lead_synced_idx');
            $table->dropColumn('synced_to_agent_at');
        });
    }
};
