<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HumanHandoffResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $ticketId,
        public readonly int $leadId,
        public readonly ?string $resolutionReason,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("atendimentos.{$this->tenantId}"),
            new PrivateChannel("conversation.{$this->leadId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'handoff.resolved';
    }
}
