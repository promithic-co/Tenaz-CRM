<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function actionTenant(): array
{
    $tenant = Tenant::create(['name' => 'ActionTest']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_HUMAN_PENDING,
    ]);

    $ticket = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHours(4),
    ]);

    return [$tenant, $owner, $lead, $ticket];
}

test('claim assigns ticket and lead to current user', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();

    $this->actingAs($owner)->post(route('atendimentos.claim', $ticket))->assertRedirect();

    $ticket->refresh();
    $lead->refresh();
    expect($ticket->assigned_user_id)->toBe($owner->id);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_ASSIGNED);
    expect($lead->assigned_user_id)->toBe($owner->id);
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
});

test('claim uses the canonical claim action from the lifecycle service', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();

    $this->actingAs($owner)->post(route('atendimentos.claim', $ticket))->assertRedirect();

    $ticket->refresh();
    expect($ticket->claimed_at)->not->toBeNull();
});

test('resolve changes ticket status to resolved', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('atendimentos.resolve', $ticket))->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_RESOLVED);
    expect($ticket->resolved_at)->not->toBeNull();
});

test('return-to-ai resolves ticket and resumes AI on lead', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);
    $lead->update([
        'assigned_user_id' => $owner->id,
        'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
        'ai_paused_until' => now()->addHours(10),
        'ai_paused_reason' => 'ticket_claimed',
    ]);

    $this->actingAs($owner)->post(route('atendimentos.return-to-ai', $ticket))->assertRedirect();

    $ticket->refresh();
    $lead->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_RESOLVED);
    expect($ticket->resolution_reason)->toBe(ServiceTicket::RESOLUTION_RETURNED_TO_AI);
    expect($lead->assigned_user_id)->toBeNull();
    expect($lead->ai_paused_until)->toBeNull();
    expect($lead->operational_stage)->toBe(Lead::STAGE_AI_QUALIFYING);
});

test('keep-manual closes ticket and keeps AI paused on lead', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);
    $lead->update([
        'assigned_user_id' => $owner->id,
        'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
        'ai_paused_until' => now()->addHours(10),
    ]);

    $this->actingAs($owner)->post(route('atendimentos.keep-manual', $ticket))->assertRedirect();

    $ticket->refresh();
    $lead->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_CLOSED);
    expect($ticket->resolution_reason)->toBe(ServiceTicket::RESOLUTION_MANUAL_KEEP);
    // Lead remains assigned, AI stays paused
    expect($lead->assigned_user_id)->toBe($owner->id);
    expect($lead->ai_paused_until)->not->toBeNull();
});

test('close changes ticket status to closed', function () {
    [$tenant, $owner, $lead, $ticket] = actionTenant();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('atendimentos.close', $ticket))->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_CLOSED);
    expect($ticket->closed_at)->not->toBeNull();
});
