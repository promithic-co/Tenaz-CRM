<?php

namespace App\Console\Commands;

use App\Models\WhatsappOutboxMessage;
use App\Services\ConversationTimelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileOutboxCommand extends Command
{
    protected $signature = 'credflow:reconcile-outbox';

    protected $description = 'Reconcile stranded in_doubt conversation-outbox messages to failed after a timeout';

    /**
     * Conversation-path twin of MonitorCampaignsCommand::reconcileInDoubtMessages (CAMP-08).
     *
     * A 1:1 send that ends ambiguous is parked as in_doubt: the provider POST may or may not have
     * reached Meta, so it must never be blindly re-sent. It resolves passively when a delivery
     * webhook echoes its opaque key (idempotency_key). The Meta Cloud API offers no
     * lookup-by-client-reference, so a lost webhook would strand the row in in_doubt forever — and
     * its timeline bubble pinned in 'sending', with no terminal signal to the operator.
     *
     * After a generous timeout the row is flipped to failed. provider_attempted_at is preserved, so
     * the in-doubt send guard still blocks any re-send: reconciliation is duplicate-safe by
     * construction. A late webhook arriving after the flip can still resolve nothing (syncOutbox only
     * adopts an in_doubt row), an accepted loss beyond the 24h horizon. Per-row (not bulk) so each
     * timeline message can flip to failed and broadcast for a live UI update.
     */
    public function handle(ConversationTimelineService $timeline): int
    {
        $timeoutSeconds = (int) config('credflow.jobs.outbox_in_doubt_timeout_seconds', 86400);

        if ($timeoutSeconds <= 0) {
            return self::SUCCESS;
        }

        $reconciled = 0;

        WhatsappOutboxMessage::query()
            ->where('status', 'in_doubt')
            ->where('updated_at', '<', now()->subSeconds($timeoutSeconds))
            ->chunkById(100, function ($rows) use ($timeline, &$reconciled): void {
                foreach ($rows as $outbox) {
                    $outbox->markFailed('No delivery confirmation within the reconciliation window; provider send unconfirmed.');

                    if ($outbox->timelineMessage) {
                        $outbox->timelineMessage->update(['status' => 'failed']);
                        $timeline->broadcast($outbox->timelineMessage->fresh());
                    }

                    $reconciled++;
                }
            });

        if ($reconciled > 0) {
            Log::warning('ReconcileOutbox: reconciled stranded in_doubt outbox messages to failed', [
                'count' => $reconciled,
                'timeout_seconds' => $timeoutSeconds,
            ]);
        }

        return self::SUCCESS;
    }
}
