<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HumanHandoffTransferService;
use App\Services\ServiceTicketLifecycleService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function handoffFlowSetup(): array
{
    $tenant = Tenant::create(['name' => 'HandoffFlowTest']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'whatsapp' => '5511999990001',
    ]);

    return [$tenant, $owner, $lead];
}

test('full flow: AI transfers → lead enters waiting → operator claims → resolves → ticket closed', function () {
    [$tenant, $owner, $lead] = handoffFlowSetup();

    $transfer = app(HumanHandoffTransferService::class);
    $lifecycle = app(ServiceTicketLifecycleService::class);

    // AI transfers
    $ticket = $transfer->transferFromAi($lead, [
        'reason' => 'solicitacao_cliente',
        'summary' => 'Quer proposta de empréstimo',
    ]);

    $lead->refresh();
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
    expect($lead->followup_status)->toBe('paused');
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);

    // Operator claims
    $claimed = $lifecycle->claim($ticket, $owner);
    $lead->refresh();

    expect($claimed->status)->toBe(ServiceTicket::STATUS_ASSIGNED);
    expect((int) $claimed->assigned_user_id)->toBe($owner->id);
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);

    // Operator sends response (mark human response)
    $lifecycle->markHumanResponse($lead, $owner);
    $lead->refresh();
    expect($lead->operational_stage)->toBe(Lead::STAGE_WAITING_CUSTOMER);

    // Operator resolves
    $resolved = $lifecycle->resolve($claimed->fresh(), $owner, 'converted');

    expect($resolved->status)->toBe(ServiceTicket::STATUS_RESOLVED);
    expect($resolved->resolution_reason)->toBe('converted');
    expect($resolved->resolved_at)->not->toBeNull();

    // Closed bucket: ticket is resolved
    $inClosed = ServiceTicket::whereIn('status', [ServiceTicket::STATUS_RESOLVED, ServiceTicket::STATUS_CLOSED])
        ->where('lead_id', $lead->id)
        ->exists();

    expect($inClosed)->toBeTrue();
});

test('AI handoff creates exactly one escalation ticket (idempotent)', function () {
    [$tenant, $owner, $lead] = handoffFlowSetup();

    $transfer = app(HumanHandoffTransferService::class);

    $transfer->transferFromAi($lead, ['reason' => 'solicitacao_cliente']);
    $transfer->transferFromAi($lead, ['reason' => 'solicitacao_cliente', 'summary' => 'Updated summary']);

    $count = ServiceTicket::where('lead_id', $lead->id)
        ->where('type', ServiceTicket::TYPE_ESCALATION)
        ->whereIn('status', ServiceTicket::ACTIVE_STATUSES)
        ->count();

    expect($count)->toBe(1);
});

test('claim sets claimed_at and ticket enters assigned status', function () {
    [$tenant, $owner, $lead] = handoffFlowSetup();

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    $result = app(ServiceTicketLifecycleService::class)->claim($ticket, $owner);

    expect($result->status)->toBe(ServiceTicket::STATUS_ASSIGNED);
    expect($result->claimed_at)->not->toBeNull();
});

test('sla_overdue is true when sla_due_at is in the past', function () {
    [$tenant, $owner, $lead] = handoffFlowSetup();

    $ticket = ServiceTicket::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->subHours(2),
    ]);

    $overdue = ServiceTicket::forTenant((string) $tenant->id)
        ->where('type', ServiceTicket::TYPE_ESCALATION)
        ->whereIn('status', ServiceTicket::ACTIVE_STATUSES)
        ->where('sla_due_at', '<', now())
        ->exists();

    expect($overdue)->toBeTrue();
});
