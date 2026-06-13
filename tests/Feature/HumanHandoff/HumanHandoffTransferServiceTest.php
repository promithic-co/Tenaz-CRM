<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HumanHandoffTransferService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function handoffTenant(): array
{
    $tenant = Tenant::create(['name' => 'HandoffTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'status' => 'qualificado',
    ]);

    return [$tenant, $user, $lead];
}

function transferFromAi(Lead $lead, array $data = []): ServiceTicket
{
    return app(HumanHandoffTransferService::class)->transferFromAi($lead, $data);
}

test('transferFromAi creates open escalation ticket', function () {
    [,, $lead] = handoffTenant();

    $ticket = transferFromAi($lead, ['reason' => 'proposta_aceita', 'summary' => 'Produto escolhido: Crédito Novo']);

    expect($ticket->type)->toBe(ServiceTicket::TYPE_ESCALATION);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);
});

test('transferFromAi saves reason, summary, chosen_product, total_value', function () {
    [,, $lead] = handoffTenant();

    $ticket = transferFromAi($lead, [
        'reason' => 'proposta_aceita',
        'summary' => 'Crédito Novo aprovado',
        'chosen_product' => 'Crédito Novo INSS',
        'total_value' => '12500.00',
    ]);

    expect($ticket->reason)->toBe('proposta_aceita');
    expect($ticket->summary)->toBe('Crédito Novo aprovado');
    expect($ticket->chosen_product)->toBe('Crédito Novo INSS');
    expect((string) $ticket->total_value)->toBe('12500');
});

test('transferFromAi sets operational_stage to human_pending', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead);

    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
});

test('transferFromAi sets ai_paused_until to future', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead);

    $lead->refresh();
    expect($lead->ai_paused_until)->not->toBeNull();
    expect($lead->ai_paused_until->isFuture())->toBeTrue();
});

test('transferFromAi sets ai_paused_reason to handoff_requested_by_ai', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead);

    expect($lead->fresh()->ai_paused_reason)->toBe('handoff_requested_by_ai');
});

test('transferFromAi sets followup_status to paused', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead);

    expect($lead->fresh()->followup_status)->toBe('paused');
});

test('transferFromAi does not set assigned_user_id on fresh handoff', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead);

    expect($lead->fresh()->assigned_user_id)->toBeNull();
});

test('transferFromAi idempotent: second call reuses active ticket', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead, ['reason' => 'proposta_aceita']);
    transferFromAi($lead, ['reason' => 'proposta_aceita', 'summary' => 'Updated summary']);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('transferFromAi updates summary on existing active ticket', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead, ['reason' => 'proposta_aceita', 'summary' => 'First']);
    transferFromAi($lead, ['reason' => 'proposta_aceita', 'summary' => 'Second']);

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket->summary)->toBe('Second');
});

test('transferFromAi reuses ticket even when Lead.status is already escalado', function () {
    [,, $lead] = handoffTenant();
    $lead->update(['status' => 'escalado']);

    transferFromAi($lead, ['reason' => 'proposta_aceita']);
    transferFromAi($lead, ['reason' => 'proposta_aceita']);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('transferFromAi creates new ticket when previous one is resolved', function () {
    [,, $lead] = handoffTenant();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
    ]);

    transferFromAi($lead, ['reason' => 'proposta_aceita']);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
    expect(ServiceTicket::where('lead_id', $lead->id)->count())->toBe(2);
});

test('transferFromAi creates new ticket when previous one is closed', function () {
    [,, $lead] = handoffTenant();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_CLOSED,
    ]);

    transferFromAi($lead, ['reason' => 'proposta_aceita']);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('transferFromAi transitions Lead.status to escalado when allowed', function () {
    [,, $lead] = handoffTenant();
    // qualificado → escalado is a valid transition in the default machine

    transferFromAi($lead);

    expect($lead->fresh()->status)->toBe('escalado');
});

test('transferFromAi does not fail when Lead.status cannot transition to escalado', function () {
    [,, $lead] = handoffTenant();
    $lead->update(['status' => 'novo']); // novo → escalado not allowed

    $ticket = transferFromAi($lead);

    expect($ticket)->toBeInstanceOf(ServiceTicket::class);
    expect($lead->fresh()->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
    // status stays novo, no exception thrown
    expect($lead->fresh()->status)->toBe('novo');
});

test('transferFromAi preserves coherent existing assignee', function () {
    [$tenant, $user, $lead] = handoffTenant();
    $lead->update(['assigned_user_id' => $user->id]);

    // Create an assigned escalation ticket already present.
    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
    ]);

    transferFromAi($lead, ['summary' => 'Additional context']);

    // Only one active escalation ticket should exist.
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('transferFromAi records handoff_created audit event', function () {
    [,, $lead] = handoffTenant();

    transferFromAi($lead, ['reason' => 'proposta_aceita', 'summary' => 'Produto escolhido']);

    $event = AgentInteractionEvent::where('lead_id', $lead->id)
        ->where('event_type', 'handoff_created')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->event_source)->toBe('ai_tool');
    expect($event->payload_json['reason'])->toBe('proposta_aceita');
});
