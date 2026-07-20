<?php

namespace App\Console\Commands;

use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wraps each existing lead's history in a single ConversationSession.
 *
 * For every lead without a session yet, creates session #1 spanning the current
 * timeline: opened at the first message (or the lead's creation), last_message_at at
 * the newest message. Leads in a terminal status get a closed session with an
 * inferred outcome; all others stay open. Then stamps that lead's timeline rows with
 * the new session_id in chunks.
 *
 * Idempotent and re-runnable: leads that already own a session are skipped, and the
 * timeline update only touches rows still missing a session_id.
 */
class BackfillConversationSessionsCommand extends Command
{
    protected $signature = 'sessions:backfill {--dry-run : Report how many leads would get a session without writing}';

    protected $description = 'Create one ConversationSession per existing lead and stamp its timeline history';

    private const TERMINAL_OUTCOMES = [
        'convertido' => ConversationSession::OUTCOME_CONVERTED,
        'desqualificado' => ConversationSession::OUTCOME_LOST,
        'optou_sair' => ConversationSession::OUTCOME_LOST,
        'sem_credito' => ConversationSession::OUTCOME_LOST,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Lead::query()
            ->withoutGlobalScope('tenant')
            ->whereDoesntHave('sessions');

        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info('No leads need backfilling — every lead already has a session.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').sprintf('%d lead(s) without a session.', $total));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $created = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($leads) use (&$created, $bar): void {
            foreach ($leads as $lead) {
                $this->backfillLead($lead);
                $created++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Done. Sessions created: %d.', $created));

        return self::SUCCESS;
    }

    private function backfillLead(Lead $lead): void
    {
        $isTerminal = array_key_exists((string) $lead->status, self::TERMINAL_OUTCOMES);

        $firstAt = ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->min('created_at');
        $lastAt = ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->max('created_at');

        $openedAt = $firstAt ?? $lead->created_at ?? now();

        $session = ConversationSession::create([
            'tenant_id' => (string) $lead->tenant_id,
            'lead_id' => $lead->id,
            'number' => 1,
            'status' => $isTerminal ? ConversationSession::STATUS_CLOSED : ConversationSession::STATUS_OPEN,
            'open_reason' => ConversationSession::OPEN_REASON_FIRST_CONTACT,
            'outcome' => $isTerminal ? self::TERMINAL_OUTCOMES[(string) $lead->status] : null,
            'opened_at' => $openedAt,
            'closed_at' => $isTerminal ? ($lead->updated_at ?? now()) : null,
            'last_message_at' => $lastAt ?? $openedAt,
        ]);

        DB::table('conversation_timeline_messages')
            ->where('lead_id', $lead->id)
            ->whereNull('session_id')
            ->update(['session_id' => $session->id]);
    }
}
