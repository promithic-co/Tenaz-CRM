<?php

namespace App\Jobs;

use App\Services\AgentInteractionEventService;
use App\Services\DebounceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Drains the per-phone debounce buffer after the debounce window has elapsed
 * and hands the aggregated text off to ProcessIncomingWhatsAppMessageJob.
 *
 * This replaces the previous blocking sleep() inside the Meta webhook request:
 * the HTTP worker now returns immediately and the wait happens on the queue.
 */
class AggregateDebouncedMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public readonly string $phone,
        public readonly string $name,
        public readonly string $tenantId,
        public readonly ?int $agentId,
        public readonly string $instanceName,
        public readonly string $provider,
        public readonly ?string $interactionId = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?array $referral = null,
    ) {
        $this->onQueue('messages');
    }

    public function handle(DebounceService $debounce, AgentInteractionEventService $interactionEvents): void
    {
        $aggregated = $debounce->drain($this->phone);

        if ($aggregated === null || $aggregated === '') {
            return;
        }

        Log::info('meta.incoming', [
            'interaction_id' => $this->interactionId,
            'phone' => $this->phone,
            'instance' => $this->instanceName,
            'msg_len' => strlen($aggregated),
            'has_media' => false,
        ]);

        $interactionEvents->record(
            interactionId: $this->interactionId,
            tenantId: $this->tenantId,
            eventType: 'webhook_received',
            eventSource: 'meta_webhook_controller',
            payload: [
                'channel' => 'whatsapp',
                'provider' => $this->provider,
                'instance_name' => $this->instanceName,
                'provider_message_id' => $this->providerMessageId,
                'phone' => $this->phone,
                'has_media' => false,
                'message_length' => strlen($aggregated),
            ],
            agentId: $this->agentId,
        );

        ProcessIncomingWhatsAppMessageJob::dispatch(
            $this->phone,
            $this->name,
            $this->tenantId,
            $this->agentId,
            $this->instanceName,
            $aggregated,
            null,
            $this->interactionId,
            $this->providerMessageId,
            null,
            $this->referral,
        );
    }
}
