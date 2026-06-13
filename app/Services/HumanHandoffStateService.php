<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ServiceTicket;

class HumanHandoffStateService
{
    /**
     * Derive the human handoff state for a lead.
     *
     * Returns machine-readable values:
     * - waiting_human: open escalation ticket, no assignee yet
     * - human_active: escalation ticket assigned to a human
     * - waiting_customer: human responded, waiting for customer reply
     * - ai_active: no active escalation, AI handling or no agent
     * - closed: ticket resolved/closed
     */
    public function deriveState(Lead $lead, ?ServiceTicket $activeTicket = null): string
    {
        if ($activeTicket === null) {
            $activeTicket = ServiceTicket::query()
                ->activeEscalation($lead->id)
                ->latest()
                ->first();
        }

        if ($activeTicket === null) {
            return 'ai_active';
        }

        return match ($activeTicket->status) {
            ServiceTicket::STATUS_OPEN => 'waiting_human',
            ServiceTicket::STATUS_ASSIGNED, ServiceTicket::STATUS_WAITING_INTERNAL => 'human_active',
            ServiceTicket::STATUS_WAITING_CUSTOMER => 'waiting_customer',
            ServiceTicket::STATUS_RESOLVED, ServiceTicket::STATUS_CLOSED => 'closed',
            default => 'ai_active',
        };
    }

    /**
     * Derive the ordered list of operator actions available for an active
     * escalation ticket. Empty when there is no ticket or the ticket is not in
     * an active status. Parity port of the handoff-action derivation that lived
     * in ConversasController::conversationProps.
     *
     * @return list<string>
     */
    public function handoffActions(?ServiceTicket $activeTicket): array
    {
        if ($activeTicket === null) {
            return [];
        }

        if (! in_array($activeTicket->status, ServiceTicket::ACTIVE_STATUSES, true)) {
            return [];
        }

        $actions = [];

        if ($activeTicket->status === ServiceTicket::STATUS_OPEN) {
            $actions[] = 'claim';
        }

        $actions[] = 'resolve';
        $actions[] = 'return_to_ai';
        $actions[] = 'keep_manual';

        return $actions;
    }

    /**
     * Return a structured active handoff payload for frontend consumption.
     *
     * @return array<string, mixed>|null
     */
    public function activeHandoffPayload(Lead $lead): ?array
    {
        $ticket = ServiceTicket::query()
            ->activeEscalation($lead->id)
            ->with('assignedUser')
            ->latest()
            ->first();

        if ($ticket === null) {
            return null;
        }

        return [
            'id' => $ticket->id,
            'type' => $ticket->type,
            'status' => $ticket->status,
            'reason' => $ticket->reason,
            'summary' => $ticket->summary,
            'priority' => $ticket->priority,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'sla_overdue' => $ticket->sla_due_at?->isPast() ?? false,
            'assigned_user_id' => $ticket->assigned_user_id,
            'assigned_user_name' => $ticket->assignedUser?->name,
            'claimed_at' => $ticket->claimed_at?->toIso8601String(),
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
        ];
    }
}
