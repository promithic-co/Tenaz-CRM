<?php

namespace App\Jobs;

use App\Ai\DTOs\MediaContext;
use App\Events\ConversationUpdated;
use App\Models\Agent;
use App\Models\ConversationTimelineMessage;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionEventService;
use App\Services\AgentService;
use App\Services\ConversationAutomationService;
use App\Services\ConversationTimelineService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\IncomingConversationPersister;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Services\WhatsappOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingWhatsAppMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public int $maxExceptions = 2;

    /**
     * Exponential backoff delays (in seconds) between retries.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function retryUntil(): ?\DateTimeInterface
    {
        $windowSeconds = (int) config('credflow.jobs.incoming_message_retry_window_seconds', 1800);

        return $windowSeconds > 0 ? now()->addSeconds($windowSeconds) : null;
    }

    public function __construct(
        public readonly string $phone,
        public readonly string $name,
        public readonly string $tenantId,
        public readonly ?int $agentId,
        public readonly string $instanceName,
        public readonly string $aggregatedMessage,
        public readonly ?array $mediaContext = null,
        public readonly ?string $interactionId = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?array $mediaPayload = null,
        public readonly ?array $referral = null,
    ) {
        $this->onQueue('messages');
    }

    /**
     * Prevent concurrent processing of messages for the same phone+tenant.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("incoming_msg_{$this->tenantId}_{$this->phone}"))
                ->releaseAfter(15)
                ->expireAfter(120),
        ];
    }

    /**
     * Execute the job.
     *
     * Two-phase pipeline:
     *   1. Persist CRM state (lead + timeline + audit) — never blocked by AI.
     *   2. Run automation (campaign already detected inside persister, agent
     *      reply, outbox enqueue) — failure here cannot remove the inbound.
     */
    public function handle(
        AgentService $agent,
        WhatsappOutboxService $outbox,
        ConversationAutomationService $automation,
        IncomingConversationPersister $persister,
        ?ConversationTimelineService $timeline = null,
    ): void {
        $timeline ??= app(ConversationTimelineService::class);

        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $this->interactionId ?? $interactionEvents->newInteractionId();

        // Defer media download to the queue worker (was previously synchronous in the webhook).
        $resolvedMediaContext = $this->mediaContext;
        if ($resolvedMediaContext === null && $this->mediaPayload !== null) {
            try {
                $provider = app(WhatsAppProviderFactory::class)->makeProviderFromInstanceName($this->instanceName);
                $downloaded = $provider?->downloadMedia(new Request, $this->mediaPayload);
                $resolvedMediaContext = $downloaded?->toArray();
            } catch (\Throwable $e) {
                Log::warning('whatsapp_job.deferred_media_download_failed', [
                    'interaction_id' => $interactionId,
                    'phone' => $this->phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $persisted = $persister->persist(
            tenantId: $this->tenantId,
            agentId: $this->agentId,
            phone: $this->phone,
            name: $this->name,
            instanceName: $this->instanceName,
            aggregatedMessage: $this->aggregatedMessage,
            mediaContext: $resolvedMediaContext,
            interactionId: $interactionId,
            providerMessageId: $this->providerMessageId,
            referral: $this->referral,
        );

        if ($persisted === null) {
            // Lock contention — release back to queue for a fresh worker.
            $this->release(5);

            return;
        }

        $lead = $persisted['lead'];
        $inboundTimeline = $persisted['timelineMessage'];
        $mode = $persisted['mode'];
        $duplicate = $persisted['duplicate'];

        if ($duplicate) {
            // Already persisted on a prior delivery; do not double-broadcast,
            // double-trigger automation, or duplicate the agent turn.
            return;
        }

        $this->safeBroadcast($timeline, $inboundTimeline, $interactionId);

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'broadcast_sent',
            eventSource: 'process_incoming_whatsapp_message_job',
            payload: ['channel' => 'frontend', 'message_role' => 'user'],
        );

        ConversationUpdated::dispatch($lead->id, (string) $this->tenantId, (string) $lead->status);

        if ($lead->agent_id === null) {
            Log::info('whatsapp_job.automation_skipped_no_agent', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'instance_name' => $this->instanceName,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'automation_skipped_no_agent',
                eventSource: 'process_incoming_whatsapp_message_job',
                payload: ['instance_name' => $this->instanceName],
            );

            app(DashboardMetricsService::class)->dispatchUpdate($this->tenantId);

            return;
        }

        if (! $automation->shouldAutoRespond($lead, $this->instanceName)) {
            $reason = $lead->isAiPaused() ? 'ai_paused' : "ai_mode_{$mode}";

            Log::info('whatsapp_job.agent_skipped_by_automation_mode', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'mode' => $mode,
                'reason' => $reason,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'agent_skipped',
                eventSource: 'process_incoming_whatsapp_message_job',
                payload: ['reason' => $reason, 'mode' => $mode],
            );

            app(DashboardMetricsService::class)->dispatchUpdate($this->tenantId);

            return;
        }

        if ($lead->agent_id) {
            $leadAgent = Agent::withoutGlobalScopes()->find($lead->agent_id);
            if ($leadAgent && ! $leadAgent->is_active) {
                Log::info('whatsapp_job.agent_inactive', [
                    'interaction_id' => $interactionId,
                    'lead_id' => $lead->id,
                    'agent_id' => $lead->agent_id,
                    'phone' => $this->phone,
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $lead,
                    eventType: 'agent_skipped',
                    eventSource: 'process_incoming_whatsapp_message_job',
                    payload: ['reason' => 'agent_inactive'],
                );

                app(DashboardMetricsService::class)->dispatchUpdate($this->tenantId);

                return;
            }
        }

        // Processar com ARIA passando o contexto de mídia (reidratar array → MediaContext)
        $mediaContext = $resolvedMediaContext ? MediaContext::fromArray($resolvedMediaContext) : null;
        $response = $agent->process($lead, $this->aggregatedMessage, $mediaContext, $interactionId);
        $automation->syncAfterAgentTurn($lead, $mode);

        // Enviar resposta ao WhatsApp
        if ($response) {
            $instance = $lead->whatsapp_instance_id
                ? WhatsappInstance::withoutGlobalScopes()
                    ->where('tenant_id', $lead->tenant_id)
                    ->whereKey($lead->whatsapp_instance_id)
                    ->first()
                : null;

            if ($instance === null) {
                Log::warning('whatsapp_job.outbound_skipped_no_instance', [
                    'interaction_id' => $interactionId,
                    'lead_id' => $lead->id,
                    'instance_name' => $this->instanceName,
                ]);

                return;
            }

            $outboxMessages = $outbox->queueSplitTextForLead(
                lead: $lead,
                instance: $instance,
                phone: $this->phone,
                text: $response,
                source: 'agent',
                senderType: 'agent',
                interactionId: $interactionId,
            );

            foreach ($outboxMessages as $outboxMessage) {
                if ($outboxMessage->timelineMessage) {
                    $this->safeBroadcast($timeline, $outboxMessage->timelineMessage, $interactionId);
                }
            }

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'outbound_queued',
                eventSource: 'process_incoming_whatsapp_message_job',
                payload: [
                    'instance_name' => $instance->name,
                    'phone' => $this->phone,
                    'response_length' => strlen($response),
                ],
            );
        }

        app(DashboardMetricsService::class)->dispatchUpdate($this->tenantId);
    }

    /**
     * Realtime broadcast must never abort CRM persistence — log and continue
     * if the broker is down. Polling/refresh covers the UI.
     */
    private function safeBroadcast(
        ConversationTimelineService $timeline,
        ConversationTimelineMessage $message,
        string $interactionId,
    ): void {
        try {
            $timeline->broadcast($message);
        } catch (\Throwable $e) {
            Log::warning('whatsapp_job.broadcast_failed', [
                'interaction_id' => $interactionId,
                'timeline_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
