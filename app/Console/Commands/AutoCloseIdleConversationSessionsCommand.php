<?php

namespace App\Console\Commands;

use App\Models\ConversationSession;
use App\Services\ConversationSessionLifecycleService;
use Illuminate\Console\Command;

/**
 * Closes open ConversationSessions that have been idle past the inactivity window.
 *
 * A session left open forever would keep a lead counted as an active atendimento and
 * block the "one open session per lead" invariant from ever letting a fresh cycle
 * start cleanly. Idle is measured from last_message_at (falling back to opened_at).
 * Idempotent: already-closed sessions are never touched.
 */
class AutoCloseIdleConversationSessionsCommand extends Command
{
    protected $signature = 'sessions:auto-close {--dry-run : Report how many sessions would close without writing}';

    protected $description = 'Auto-close conversation sessions idle past the inactivity window';

    public function handle(ConversationSessionLifecycleService $sessions): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays(ConversationSessionLifecycleService::AUTO_CLOSE_INACTIVITY_DAYS);

        $query = ConversationSession::withoutGlobalScopes()
            ->where('status', ConversationSession::STATUS_OPEN)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_message_at', '<', $cutoff)
                    ->orWhere(function ($qq) use ($cutoff): void {
                        $qq->whereNull('last_message_at')->where('opened_at', '<', $cutoff);
                    });
            });

        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info('No idle sessions to close.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').sprintf('%d idle session(s) past the %d-day window.', $total, ConversationSessionLifecycleService::AUTO_CLOSE_INACTIVITY_DAYS));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $closed = 0;

        $query->chunkById(200, function ($rows) use ($sessions, &$closed): void {
            foreach ($rows as $session) {
                $sessions->close($session, ConversationSession::OUTCOME_ABANDONED);
                $closed++;
            }
        });

        $this->info(sprintf('Done. Sessions closed: %d.', $closed));

        return self::SUCCESS;
    }
}
