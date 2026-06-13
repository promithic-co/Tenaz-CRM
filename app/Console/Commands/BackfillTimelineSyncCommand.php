<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent backfill: mark existing conversation_timeline_messages rows as
 * `synced_to_agent_at = created_at` so ConversationContextSynchronizer doesn't
 * try to re-mirror history that's already (or no longer recoverable) in
 * agent_conversation_messages.
 *
 * The Phase 45 migration runs this automatically, but the command stays available
 * for re-runs, manual fixes, and per-lead targeting.
 */
class BackfillTimelineSyncCommand extends Command
{
    protected $signature = 'aria:backfill-timeline-sync
        {--lead-id= : Only backfill the specified lead}
        {--dry-run : Report counts without writing}';

    protected $description = 'Mark existing timeline rows as already-synced into agent memory.';

    public function handle(): int
    {
        $query = DB::table('conversation_timeline_messages')->whereNull('synced_to_agent_at');

        if ($leadId = $this->option('lead-id')) {
            $query->where('lead_id', (int) $leadId);
        }

        $count = (clone $query)->count();
        $this->info("Pending timeline rows to mark synced: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no changes written.');

            return self::SUCCESS;
        }

        $affected = $query->update(['synced_to_agent_at' => DB::raw('created_at')]);
        $this->info("Marked {$affected} rows as synced.");

        return self::SUCCESS;
    }
}
