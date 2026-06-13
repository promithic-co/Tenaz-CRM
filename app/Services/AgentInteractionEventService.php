<?php

namespace App\Services;

use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AgentInteractionEventService
{
    public function newInteractionId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $interactionId,
        string|int $tenantId,
        string $eventType,
        string $eventSource,
        array $payload = [],
        string $severity = 'info',
        ?int $leadId = null,
        ?int $agentId = null,
    ): AgentInteractionEvent {
        return AgentInteractionEvent::create([
            'interaction_id' => $interactionId,
            'tenant_id' => (string) $tenantId,
            'lead_id' => $leadId,
            'agent_id' => $agentId,
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'severity' => $severity,
            'payload_json' => $payload === [] ? null : $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordForLead(
        string $interactionId,
        Lead $lead,
        string $eventType,
        string $eventSource,
        array $payload = [],
        string $severity = 'info',
    ): AgentInteractionEvent {
        return $this->record(
            interactionId: $interactionId,
            tenantId: $lead->tenant_id,
            eventType: $eventType,
            eventSource: $eventSource,
            payload: $payload,
            severity: $severity,
            leadId: $lead->id,
            agentId: $lead->agent_id,
        );
    }

    /**
     * @return Collection<int, AgentInteractionEvent>
     */
    public function timeline(string $interactionId): Collection
    {
        return AgentInteractionEvent::query()
            ->where('interaction_id', $interactionId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, AgentInteractionEvent>
     */
    public function timelineForLead(Lead $lead): Collection
    {
        return AgentInteractionEvent::query()
            ->where('lead_id', $lead->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
