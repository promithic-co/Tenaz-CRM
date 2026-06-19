<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\WhatsappOutboxMessage;
use App\Services\ConversationTimelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCampaignDeliveryEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $providerMessageId,
        public readonly string $eventType,
        public readonly array $errors = [],
        public readonly ?string $opaqueId = null,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        $this->syncOutbox();

        $messageId = $this->resolveMessageId();

        if (! $messageId) {
            Log::debug('ProcessCampaignDeliveryEventJob: message not found', [
                'provider_message_id' => $this->providerMessageId,
                'opaque_id' => $this->opaqueId,
                'event_type' => $this->eventType,
            ]);

            return;
        }

        // Atomicity: load + gate + mutate under a row lock so concurrent duplicate
        // webhooks (Meta retries / parallel workers) cannot both pass canTransitionTo
        // and double-count a counter. The broadcast/IO stays outside this transaction.
        DB::transaction(function () use ($messageId): void {
            $message = CampaignMessage::query()->whereKey($messageId)->lockForUpdate()->first();

            if (! $message) {
                return;
            }

            $campaign = $message->campaign;

            // An in-doubt row resolves the moment Meta acknowledges it: any status proves
            // the send reached Meta. Adopt the wamid and count the (previously uncounted) send.
            if ($message->status === 'in_doubt') {
                $this->resolveInDoubt($message, $campaign);
                $message->refresh();
            }

            $newStatus = $this->mapEventToStatus($this->eventType);

            if (! $newStatus) {
                return;
            }

            if (! $message->canTransitionTo($newStatus)) {
                Log::debug('ProcessCampaignDeliveryEventJob: skipping backwards transition', [
                    'message_id' => $message->id,
                    'current_status' => $message->status,
                    'new_status' => $newStatus,
                ]);

                return;
            }

            match ($newStatus) {
                'delivered' => $this->handleDelivered($message, $campaign),
                'read' => $this->handleRead($message, $campaign),
                'failed' => $this->handleFailed($message, $campaign),
                default => null,
            };

            Log::info('ProcessCampaignDeliveryEventJob: processed', [
                'message_id' => $message->id,
                'new_status' => $newStatus,
                'campaign_id' => $campaign?->id,
            ]);
        });
    }

    /**
     * Find the target CampaignMessage id. Prefer the wamid; fall back to the opaque id
     * for in-doubt rows whose send was ambiguous and therefore never stored a wamid.
     */
    private function resolveMessageId(): ?int
    {
        $id = CampaignMessage::query()
            ->where('provider_message_id', $this->providerMessageId)
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        if ($this->opaqueId !== null && $this->opaqueId !== '' && ctype_digit($this->opaqueId)) {
            $candidate = CampaignMessage::query()->whereKey((int) $this->opaqueId)->first(['id', 'status']);

            if ($candidate && $candidate->status === 'in_doubt') {
                return (int) $candidate->id;
            }
        }

        return null;
    }

    /**
     * Resolve an in-doubt message: Meta acknowledged it, so it WAS sent. Adopt the wamid
     * and count the send that the ambiguous-send path intentionally did not count.
     */
    private function resolveInDoubt(CampaignMessage $message, ?Campaign $campaign): void
    {
        $message->update(['provider_message_id' => $this->providerMessageId]);
        $message->markSent($this->providerMessageId);

        if ($campaign) {
            $campaign->incrementCounter('total_sent');
        }

        Log::info('ProcessCampaignDeliveryEventJob: resolved in_doubt to sent', [
            'message_id' => $message->id,
            'provider_message_id' => $this->providerMessageId,
        ]);
    }

    private function syncOutbox(): void
    {
        $newStatus = $this->mapEventToStatus($this->eventType);
        if (! $newStatus) {
            return;
        }

        $outbox = WhatsappOutboxMessage::query()
            ->where('provider_message_id', $this->providerMessageId)
            ->first();

        // Fall back to the opaque key (idempotency_key) for an in-doubt outbox whose
        // ambiguous send never stored a wamid.
        if (! $outbox && $this->opaqueId !== null && $this->opaqueId !== '') {
            $outbox = WhatsappOutboxMessage::query()
                ->where('idempotency_key', $this->opaqueId)
                ->where('status', 'in_doubt')
                ->first();
        }

        if (! $outbox) {
            return;
        }

        // Any status proves Meta accepted the message: resolve an in-doubt outbox to sent
        // and adopt the wamid so future events correlate by provider_message_id.
        if ($outbox->status === 'in_doubt') {
            $outbox->markSent($this->providerMessageId);
        }

        // Advance the durable outbox row to a terminal state, not just the timeline.
        if ($newStatus === 'failed') {
            $outbox->markFailed('Delivery failed via webhook event');
        } elseif ($newStatus === 'delivered' || $newStatus === 'read') {
            $outbox->update(['status' => 'delivered']);
        }

        if (! $outbox->timelineMessage) {
            return;
        }

        $status = $newStatus === 'failed' ? 'failed' : $newStatus;
        $outbox->timelineMessage->update(['status' => $status]);
        app(ConversationTimelineService::class)->broadcast($outbox->timelineMessage->fresh());
    }

    private function mapEventToStatus(string $eventType): ?string
    {
        return match (strtolower($eventType)) {
            'sent' => null, // already marked sent when dispatched
            'delivered' => 'delivered',
            'read' => 'read',
            'failed', 'undelivered' => 'failed',
            default => null,
        };
    }

    private function handleDelivered(CampaignMessage $message, ?Campaign $campaign): void
    {
        $message->markDelivered();

        if ($campaign) {
            $campaign->incrementCounter('total_delivered');
        }
    }

    private function handleRead(CampaignMessage $message, ?Campaign $campaign): void
    {
        // Ensure delivered is set first
        if ($message->status !== 'read' && $message->status !== 'delivered') {
            $message->markDelivered();

            if ($campaign) {
                $campaign->incrementCounter('total_delivered');
            }
        }

        $message->markRead();

        if ($campaign) {
            $campaign->incrementCounter('total_read');
        }
    }

    private function handleFailed(CampaignMessage $message, ?Campaign $campaign): void
    {
        if ($message->canTransitionTo('failed')) {
            $error = is_array($this->errors[0] ?? null) ? $this->errors[0] : [];
            $code = (string) ($error['code'] ?? 'DELIVERY_FAILED');
            $subcode = isset($error['error_subcode']) ? (string) $error['error_subcode'] : null;
            $messageText = (string) ($error['details'] ?? $error['title'] ?? 'Message delivery failed via webhook event');

            $message->markFailed($code, $messageText);
            $message->update(['error_subcode' => $subcode]);

            if ($campaign) {
                $campaign->incrementCounter('total_failed');
            }
        }
    }
}
