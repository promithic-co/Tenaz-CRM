<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HumanHandoffClaimed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $ticketId,
        public readonly int $leadId,
        public readonly ?int $assignedUserId,
        public readonly ?string $assignedUserName,
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
        return 'handoff.claimed';
    }
}
