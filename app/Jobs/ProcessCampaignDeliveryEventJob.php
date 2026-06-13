<?php

namespace App\Jobs;

use App\Models\CampaignMessage;
use App\Models\WhatsappOutboxMessage;
use App\Services\ConversationTimelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        $this->syncOutbox();

        $message = CampaignMessage::where('provider_message_id', $this->providerMessageId)->first();

        if (! $message) {
            Log::debug('ProcessCampaignDeliveryEventJob: message not found', [
                'provider_message_id' => $this->providerMessageId,
                'event_type' => $this->eventType,
            ]);

            return;
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

        $campaign = $message->campaign;

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

        if (! $outbox || ! $outbox->timelineMessage) {
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

    private function handleDelivered(CampaignMessage $message, ?\App\Models\Campaign $campaign): void
    {
        $message->markDelivered();

        if ($campaign) {
            $campaign->incrementCounter('total_delivered');
        }
    }

    private function handleRead(CampaignMessage $message, ?\App\Models\Campaign $campaign): void
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

    private function handleFailed(CampaignMessage $message, ?\App\Models\Campaign $campaign): void
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
