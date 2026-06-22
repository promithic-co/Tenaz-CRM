<?php

namespace App\Services;

use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentInteractionEventService
{
    /**
     * In-memory events awaiting a bulk flush (SCALE-7).
     *
     * @var list<array<string, mixed>>
     */
    private array $buffer = [];

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
     * Buffer an event for a deferred bulk insert (SCALE-7). Same shape as record(), but the
     * row is collected in memory and written by a single flush() at the end of the turn
     * instead of issuing one INSERT per call on the latency-critical AI worker. created_at is
     * stamped now so the timeline keeps real per-event timestamps and ordering.
     *
     * @param  array<string, mixed>  $payload
     */
    public function buffer(
        string $interactionId,
        string|int $tenantId,
        string $eventType,
        string $eventSource,
        array $payload = [],
        string $severity = 'info',
        ?int $leadId = null,
        ?int $agentId = null,
    ): void {
        $this->buffer[] = [
            'interaction_id' => $interactionId,
            'tenant_id' => (string) $tenantId,
            'lead_id' => $leadId,
            'agent_id' => $agentId,
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'severity' => $severity,
            'payload_json' => $payload === [] ? null : json_encode($payload),
            'created_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function bufferForLead(
        string $interactionId,
        Lead $lead,
        string $eventType,
        string $eventSource,
        array $payload = [],
        string $severity = 'info',
    ): void {
        $this->buffer(
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
     * Bulk-insert and clear all buffered events. Fail-open: a write error is logged but never
     * masks the caller's own outcome (these are observability rows). Returns rows written.
     */
    public function flush(): int
    {
        if ($this->buffer === []) {
            return 0;
        }

        $rows = $this->buffer;
        $this->buffer = [];

        try {
            AgentInteractionEvent::insert($rows);
        } catch (\Throwable $e) {
            Log::warning('AgentInteractionEventService.flush_failed', [
                'count' => count($rows),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        return count($rows);
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
