<?php

namespace App\Services;

use App\Events\AtendimentoCountersUpdated;
use App\Events\HumanHandoffCreated;
use App\Models\Lead;
use App\Models\ServiceTicket;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HumanHandoffTransferService
{
    public function __construct(
        private readonly AgentInteractionEventService $events,
        private readonly PauseService $pause,
    ) {}

    /**
     * Transfer a lead from AI to the human atendimento queue.
     *
     * Creates or reuses the active escalation ticket, writes human_pending state
     * atomically, and records a handoff_created audit event outside the transaction
     * so event failure cannot undo CRM state.
     *
     * @param  array{reason?: string, summary?: string, chosen_product?: string|null, total_value?: string|null, metadata?: array<string,mixed>}  $data
     */
    public function transferFromAi(Lead $lead, array $data = []): ServiceTicket
    {
        $ticket = DB::transaction(function () use ($lead, $data): ServiceTicket {
            $lockedLead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $ticket = ServiceTicket::query()
                ->activeEscalation($lockedLead->id)
                ->latest()
                ->lockForUpdate()
                ->first();

            $priority = ServiceTicket::inferPriorityFromReason((string) Arr::get($data, 'reason', ''));

            $payload = array_filter([
                'reason' => Arr::get($data, 'reason'),
                'summary' => Arr::get($data, 'summary'),
                'chosen_product' => Arr::get($data, 'chosen_product'),
                'total_value' => Arr::get($data, 'total_value'),
                'metadata' => Arr::get($data, 'metadata'),
            ], fn ($v): bool => $v !== null);

            if ($ticket !== null) {
                if (! empty($payload)) {
                    $ticket->fill($payload)->save();
                }
            } else {
                $ticket = ServiceTicket::create(array_merge([
                    'tenant_id' => (string) $lockedLead->tenant_id,
                    'lead_id' => $lockedLead->id,
                    'type' => ServiceTicket::TYPE_ESCALATION,
                    'status' => ServiceTicket::STATUS_OPEN,
                    'priority' => $priority,
                    'sla_due_at' => ServiceTicket::slaForPriority($priority),
                ], $payload));
            }

            $this->writeHandoffState($lockedLead);

            return $ticket->fresh(['lead', 'assignedUser']);
        });

        // Audit event outside the transaction — failure must not undo CRM state.
        try {
            $this->events->record(
                interactionId: (string) Str::uuid(),
                tenantId: (string) $lead->tenant_id,
                eventType: 'handoff_created',
                eventSource: 'ai_tool',
                payload: [
                    'ticket_id' => $ticket->id,
                    'lead_id' => $lead->id,
                    'reason' => Arr::get($data, 'reason'),
                    'summary_excerpt' => mb_substr((string) Arr::get($data, 'summary', ''), 0, 120),
                ],
                severity: 'info',
                leadId: $lead->id,
            );
        } catch (\Throwable) {
            // Intentionally swallow — audit failure must not roll back ticket/lead.
        }

        // Broadcast events outside transaction — failure must not undo CRM state.
        try {
            event(new HumanHandoffCreated(
                tenantId: (string) $lead->tenant_id,
                ticketId: $ticket->id,
                leadId: $ticket->lead_id,
                priority: $ticket->priority,
                slaAt: $ticket->sla_due_at?->toIso8601String(),
                summaryExcerpt: mb_substr((string) Arr::get($data, 'summary', ''), 0, 120),
            ));
            event(new AtendimentoCountersUpdated((string) $lead->tenant_id));
        } catch (\Throwable) {
            // Intentionally swallow — broadcast failure must not block the caller.
        }

        return $ticket;
    }

    private function writeHandoffState(Lead $lead): void
    {
        $this->pause->pause(
            (string) $lead->whatsapp,
            (string) $lead->tenant_id,
            stage: Lead::STAGE_HUMAN_PENDING,
            reason: 'handoff_requested_by_ai',
            followupStatus: 'paused',
        );

        if ($lead->status !== 'escalado' && $lead->canTransitionTo('escalado')) {
            $lead->update(['status' => 'escalado']);
        }
    }
}
