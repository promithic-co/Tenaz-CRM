<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{sent:int,delivered:int,failed:int,read:int,pending:int}  $progress
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly array $progress,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("campaigns.{$this->campaignId}")];
    }

    public function broadcastAs(): string
    {
        return 'campaign.progress.updated';
    }
}
