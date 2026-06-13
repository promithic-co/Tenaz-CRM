<?php

namespace App\Services;

use App\Jobs\ProcessWhatsappOutboxMessageJob;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WhatsappOutboxService
{
    public function __construct(private readonly AgentInteractionEventService $events) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(
        string|int $tenantId,
        array $payload,
        string $provider,
        string $idempotencyKey,
        ?Lead $lead = null,
        ?ConversationTimelineMessage $timelineMessage = null,
        ?string $interactionId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        mixed $delay = null,
    ): WhatsappOutboxMessage {
        $outbox = WhatsappOutboxMessage::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tenant_id' => (string) $tenantId,
                'lead_id' => $lead?->id,
                'channel' => 'whatsapp',
                'provider' => $provider,
                'payload_json' => $payload,
                'status' => 'queued',
                'scheduled_at' => $delay ? now()->addSeconds((int) $delay) : now(),
                'timeline_message_id' => $timelineMessage?->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'interaction_id' => $interactionId,
            ],
        );

        if ($outbox->wasRecentlyCreated) {
            if ($lead) {
                $this->events->recordForLead(
                    interactionId: $interactionId ?? $this->events->newInteractionId(),
                    lead: $lead,
                    eventType: 'outbound_queued',
                    eventSource: 'whatsapp_outbox_service',
                    payload: [
                        'outbox_id' => $outbox->id,
                        'idempotency_key' => $idempotencyKey,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ],
                );
            }

            if (! app()->runningUnitTests()) {
                $job = ProcessWhatsappOutboxMessageJob::dispatch($outbox->id);
                if ($delay) {
                    $job->delay(now()->addSeconds((int) $delay));
                }
            }
        }

        return $outbox;
    }

    public function queueTextForLead(
        Lead $lead,
        string|WhatsappInstance $instance,
        string $phone,
        string $text,
        string $source,
        string $senderType,
        ?string $interactionId = null,
        ?string $idempotencyKey = null,
        ?int $delaySeconds = null,
    ): WhatsappOutboxMessage {
        $instancePayload = $this->instancePayload($instance, $lead);
        $timeline = app(ConversationTimelineService::class)->record(
            lead: $lead,
            direction: 'outbound',
            senderType: $senderType,
            body: $text,
            status: 'queued',
            source: $source,
            interactionId: $interactionId,
        );

        return $this->queue(
            tenantId: $lead->tenant_id,
            payload: [
                ...$instancePayload,
                'type' => 'text',
                'phone' => $phone,
                'text' => $text,
            ],
            provider: (string) ($instancePayload['provider'] ?? 'meta_cloud'),
            idempotencyKey: $idempotencyKey ?? $this->defaultKey($lead, $source, $text),
            lead: $lead,
            timelineMessage: $timeline,
            interactionId: $interactionId,
            sourceType: 'lead',
            sourceId: $lead->id,
            delay: $delaySeconds,
        );
    }

    /**
     * @return list<WhatsappOutboxMessage>
     */
    public function queueSplitTextForLead(
        Lead $lead,
        string|WhatsappInstance $instance,
        string $phone,
        string $text,
        string $source,
        string $senderType,
        ?string $interactionId = null,
    ): array {
        $parts = array_values(array_filter(
            array_map('trim', explode("\n\n", $text)),
            fn (string $part): bool => $part !== '',
        ));

        return array_map(
            fn (string $part, int $index): WhatsappOutboxMessage => $this->queueTextForLead(
                lead: $lead,
                instance: $instance,
                phone: $phone,
                text: $part,
                source: $source,
                senderType: $senderType,
                interactionId: $interactionId,
                idempotencyKey: $this->defaultKey($lead, $source, $part, $index),
                delaySeconds: $index * 2,
            ),
            $parts,
            array_keys($parts),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function instancePayload(string|WhatsappInstance $instance, Lead $lead): array
    {
        if ($instance instanceof WhatsappInstance) {
            return [
                'instance_id' => $instance->id,
                'instance_name' => $instance->name,
                'provider' => $instance->provider?->value ?? 'meta_cloud',
            ];
        }

        $instanceModel = WhatsappInstance::withoutGlobalScopes()
            ->where('tenant_id', $lead->tenant_id)
            ->where('name', $instance)
            ->first();

        if ($instanceModel === null) {
            throw new \RuntimeException('WhatsApp instance not found for this lead tenant.');
        }

        return [
            'instance_id' => $instanceModel->id,
            'instance_name' => $instance,
            'provider' => $instanceModel->provider?->value ?? 'meta_cloud',
        ];
    }

    private function defaultKey(Lead $lead, string $source, string $body, int $part = 0): string
    {
        return implode(':', [
            'lead',
            $lead->id,
            $source,
            $part,
            Str::of(hash('sha256', implode('|', [
                $lead->id,
                $source,
                Arr::get($lead->getAttributes(), 'updated_at', ''),
                $body,
            ])))->substr(0, 32),
        ]);
    }
}
