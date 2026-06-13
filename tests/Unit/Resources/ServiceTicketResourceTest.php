<?php

use App\Http\Resources\ServiceTicketResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Verbatim replica of ServiceTicketController::mapTicket — the parity baseline.
 */
function legacyMapTicket(ServiceTicket $t): array
{
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

it('matches the legacy mapTicket output for an open, assigned, overdue ticket', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'nome' => 'João Lima',
        'whatsapp' => '5511977776666',
        'status' => 'escalado',
        'ai_mode' => 'manual',
        'operational_stage' => 'human_pending',
        'followup_status' => 'active',
        'followup_count' => 3,
    ]);

    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'assigned_user_id' => $user->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'priority' => ServiceTicket::PRIORITY_HIGH,
        'reason' => 'Cliente pediu atendente',
        'summary' => 'Escalado por solicitação',
        'sla_due_at' => now()->subHours(2),
        'claimed_at' => now()->subHour(),
    ]);
    $ticket->created_at = now()->subHours(13);
    $ticket->save();
    $ticket->load(['lead', 'assignedUser']);

    $resource = (new ServiceTicketResource($ticket))->toArray(request());

    expect($resource)->toEqual(legacyMapTicket($ticket));
    expect($resource['sla_overdue'])->toBeTrue();
    expect($resource['urgency'])->toBe('high');
    expect($resource['hours_open'])->toBeGreaterThanOrEqual(13);
    expect($resource['assigned_user_name'])->toBe($user->name);
});

it('matches the legacy mapTicket output for a resolved ticket', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create(['followup_status' => '']);

    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'reason' => 'Resolvido',
        'sla_due_at' => now()->subDay(),
        'resolved_at' => now()->subHour(),
        'resolution_reason' => ServiceTicket::RESOLUTION_CONVERTED,
        'resolution_notes' => 'Fechou contrato',
        'chosen_product' => 'INSS',
        'total_value' => '1500.00',
    ]);
    $ticket->load(['lead', 'assignedUser']);

    $resource = (new ServiceTicketResource($ticket))->toArray(request());

    expect($resource)->toEqual(legacyMapTicket($ticket));
    expect($resource['sla_overdue'])->toBeFalse();
    expect($resource['urgency'])->toBeNull();
    expect($resource['hours_open'])->toBeNull();
    expect($resource['resolved_at'])->not->toBeNull();
    expect($resource['lead_followup_status'])->toBe('inactive');
});
