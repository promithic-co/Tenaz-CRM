<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function inertiaTenant(): array
{
    $tenant = Tenant::create(['name' => 'InertiaTest']);
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

    return [$tenant, $owner, $agent, $lead, $ticket];
}

test('atendimentos index renders correct Inertia component with bucket props', function () {
    [$tenant, $owner] = inertiaTenant();

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->has('buckets')
            ->has('counters')
            ->has('filters')
        );
});

test('conversas show includes active_handoff and handoff_state for lead with ticket', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = inertiaTenant();

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->has('activeConversation.active_handoff')
            ->has('activeConversation.handoff_state')
            ->has('activeConversation.handoff_actions')
        );
});

test('handoff_state is waiting_human for open unassigned ticket', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = inertiaTenant();

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.handoff_state', 'waiting_human')
        );
});

test('handoff_state is human_active after ticket is assigned', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = inertiaTenant();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.handoff_state', 'human_active')
        );
});

test('handoff_state is ai_active when no active escalation ticket', function () {
    [$tenant, $owner, $agent, $lead] = inertiaTenant();
    // No ticket created

    $lead2 = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
    ]);

    $this->actingAs($owner)->get(route('conversas.show', $lead2))
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.handoff_state', 'ai_active')
        );
});

test('already-claimed error from claim action generates flash and redirect', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = inertiaTenant();
    $other = User::factory()->create();
    $other->tenants()->detach();
    $other->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    // Owner claims first
    $ticket->update([
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $owner->id,
        'claimed_at' => now(),
    ]);

    // Other user tries to claim via atendimentos route
    $response = $this->actingAs($other)->post(route('atendimentos.claim', $ticket));

    $response->assertRedirect();
    $response->assertSessionHasErrors('ticket');
});

test('conversas show includes transfer_targets for owner', function () {
    [$tenant, $owner, $agent, $lead] = inertiaTenant();

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->has('transfer_targets')
        );
});
