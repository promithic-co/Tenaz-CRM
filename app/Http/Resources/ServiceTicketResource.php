<?php

namespace App\Http\Resources;

use App\Models\ServiceTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Board/list shape for a ServiceTicket. Parity port of
 * ServiceTicketController::mapTicket.
 *
 * Requires the `lead` and `assignedUser` relations to be eager-loaded —
 * the lead_* fields and assigned_user_name read through them, so loading
 * them up front avoids N+1 when mapping a collection.
 *
 * @property-read ServiceTicket $resource
 */
class ServiceTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $t = $this->resource;

        return [
            'id' => $t->id,
            'lead_id' => $t->lead_id,
            'lead_nome' => $t->lead?->nome ?? $t->lead?->whatsapp,
            'lead_whatsapp' => $t->lead?->whatsapp,
            'lead_status' => $t->lead?->status,
            'lead_ai_mode' => $t->lead?->ai_mode,
            'lead_operational_stage' => $t->lead?->operational_stage,
            'lead_followup_status' => $t->lead?->followup_status ?: 'inactive',
            'lead_followup_count' => (int) ($t->lead?->followup_count ?? 0),
            'type' => $t->type,
            'status' => $t->status,
            'priority' => $t->priority,
            'reason' => $t->reason,
            'summary' => $t->summary,
            'assigned_user_id' => $t->assigned_user_id,
            'assigned_user_name' => $t->assignedUser?->name,
            'sla_due_at' => $t->sla_due_at?->diffForHumans(),
            'sla_due_at_full' => $t->sla_due_at?->format('d/m/Y H:i'),
            'sla_overdue' => $t->sla_due_at ? $t->sla_due_at->isPast() && in_array($t->status, ServiceTicket::ACTIVE_STATUSES, true) : false,
            'claimed_at' => $t->claimed_at?->format('d/m/Y H:i'),
            'first_response_at' => $t->first_response_at?->format('d/m/Y H:i'),
            'resolved_at' => $t->resolved_at?->format('d/m/Y H:i'),
            'closed_at' => $t->closed_at?->format('d/m/Y H:i'),
            'resolution_reason' => $t->resolution_reason,
            'resolution_notes' => $t->resolution_notes,
            'chosen_product' => $t->chosen_product,
            'total_value' => $t->total_value,
            'created_at' => $t->created_at->diffForHumans(),
            'created_at_full' => $t->created_at->format('d/m/Y H:i'),
            'hours_open' => in_array($t->status, ServiceTicket::ACTIVE_STATUSES, true)
                ? (int) $t->created_at->diffInHours(now())
                : null,
            'urgency' => in_array($t->status, ServiceTicket::ACTIVE_STATUSES, true)
                ? match (true) {
                    $t->sla_due_at?->isPast() => 'high',
                    $t->created_at->diffInHours(now()) > 12 => 'high',
                    $t->created_at->diffInHours(now()) > 4 => 'medium',
                    default => 'low',
                }
                : null,
        ];
    }
}
