<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\HumanHandoffStateService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeHandoffLead(?User $user = null): Lead
{
    $u = $user ?? User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $u->id, 'is_default' => true]);

    return Lead::factory()->forAgent($agent)->create([
        'tenant_id' => $u->tenantId,
        'followup_status' => 'active',
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
    ]);
}

test('ServiceTicket TYPE constants are defined', function () {
    expect(ServiceTicket::TYPE_ESCALATION)->toBe('escalation');
    expect(ServiceTicket::TYPE_NO_CREDIT)->toBe('no_credit');
});

test('ServiceTicket RESOLUTION constants are defined', function () {
    expect(ServiceTicket::RESOLUTION_CONVERTED)->toBe('converted');
    expect(ServiceTicket::RESOLUTION_LOST)->toBe('lost');
    expect(ServiceTicket::RESOLUTION_RETURNED_TO_AI)->toBe('returned_to_ai');
    expect(ServiceTicket::RESOLUTION_MANUAL_KEEP)->toBe('manual_keep');
    expect(ServiceTicket::RESOLUTION_DUPLICATE)->toBe('duplicate');
    expect(ServiceTicket::RESOLUTION_NO_RESPONSE)->toBe('no_response');
});

test('Lead HUMAN_HANDOFF_STAGES contains expected stages', function () {
    expect(Lead::HUMAN_HANDOFF_STAGES)->toContain(Lead::STAGE_HUMAN_PENDING);
    expect(Lead::HUMAN_HANDOFF_STAGES)->toContain(Lead::STAGE_HUMAN_ACTIVE);
    expect(Lead::HUMAN_HANDOFF_STAGES)->toContain(Lead::STAGE_WAITING_CUSTOMER);
    expect(Lead::HUMAN_HANDOFF_STAGES)->not->toContain(Lead::STAGE_AI_QUALIFYING);
});

test('escalation ticket is the active handoff entity', function () {
    $lead = makeHandoffLead();

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $found = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($ticket->id);
    expect($found->type)->toBe(ServiceTicket::TYPE_ESCALATION);
});

test('creating escalation twice updates active ticket instead of duplicating', function () {
    $lead = makeHandoffLead();
    $lifecycle = app(\App\Services\ServiceTicketLifecycleService::class);

    $t1 = $lifecycle->createOpenTicket($lead, ServiceTicket::TYPE_ESCALATION, ['reason' => 'outro', 'summary' => 'first']);
    $t2 = $lifecycle->createOpenTicket($lead, ServiceTicket::TYPE_ESCALATION, ['reason' => 'proposta_aceita', 'summary' => 'second']);

    expect($t1->id)->toBe($t2->id);
    expect($t2->reason)->toBe('proposta_aceita');
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('no_credit ticket does not count as active human handoff', function () {
    $lead = makeHandoffLead();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_NO_CREDIT,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $escalation = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($escalation)->toBeNull();
});

test('derived state returns waiting_human for open escalation and human_pending', function () {
    $lead = makeHandoffLead();
    $lead->update(['operational_stage' => Lead::STAGE_HUMAN_PENDING]);

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $state = app(HumanHandoffStateService::class)->deriveState($lead->fresh(), $ticket);

    expect($state)->toBe('waiting_human');
});

test('derived state returns human_active for assigned escalation', function () {
    $user = User::factory()->create();
    $lead = makeHandoffLead($user);
    $lead->update(['operational_stage' => Lead::STAGE_HUMAN_ACTIVE]);

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $user->id,
    ]);

    $state = app(HumanHandoffStateService::class)->deriveState($lead->fresh(), $ticket);

    expect($state)->toBe('human_active');
});

test('derived state returns waiting_customer after human response', function () {
    $lead = makeHandoffLead();
    $lead->update(['operational_stage' => Lead::STAGE_WAITING_CUSTOMER]);

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_WAITING_CUSTOMER,
        'first_response_at' => now(),
    ]);

    $state = app(HumanHandoffStateService::class)->deriveState($lead->fresh(), $ticket);

    expect($state)->toBe('waiting_customer');
});

test('derived state returns ai_active when no active escalation ticket', function () {
    $lead = makeHandoffLead();

    $state = app(HumanHandoffStateService::class)->deriveState($lead);

    expect($state)->toBe('ai_active');
});

test('activeEscalation scope excludes resolved tickets', function () {
    $lead = makeHandoffLead();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_RESOLVED,
    ]);

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(0);
});
