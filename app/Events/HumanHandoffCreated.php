<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HumanHandoffCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $ticketId,
        public readonly int $leadId,
        public readonly string $priority,
        public readonly ?string $slaAt,
        public readonly ?string $summaryExcerpt,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("atendimentos.{$this->tenantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'handoff.created';
    }
}
