<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tells every operator's inbox sidebar that one conversation moved, so the row
 * reorders and its preview catches up without a manual refresh.
 *
 * Broadcast now rather than queued: it rides alongside NewConversationMessage, which
 * is already immediate, and a sidebar that lags the thread by a Horizon hop is the
 * exact "it doesn't update by itself" this event exists to answer. The payload is
 * deliberately thin — the client answers it with an Inertia partial reload, so
 * nothing here has to be authoritative.
 */
class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $leadId,
        public readonly string $tenantId,
        public readonly string $status,
    ) {}

    /**
     * Dispatch without letting a dead broker take the caller down with it.
     *
     * Broadcasting synchronously means the Reverb call happens inside whatever
     * request or job dispatched it — an operator's send would 500 after the message
     * was already persisted and queued, turning a cosmetic realtime failure into a
     * lost reply. Every caller sits downstream of durable writes, so swallowing is
     * always the right trade: the sidebar catches up on the next visit.
     */
    public static function announce(int $leadId, string $tenantId, string $status): void
    {
        try {
            self::dispatch($leadId, $tenantId, $status);
        } catch (\Throwable $e) {
            Log::warning('conversation_updated.broadcast_failed', [
                'lead_id' => $leadId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversations.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }
}
