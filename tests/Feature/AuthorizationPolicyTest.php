<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── LeadPolicy ───────────────────────────────────────────────────────────────

test('user can view own tenant lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant($user->tenantId)->create();

    expect($user->can('view', $lead))->toBeTrue();
});

test('user cannot view other tenant lead', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();

    expect($userA->can('view', $lead))->toBeFalse();
});

test('user cannot update other tenant lead', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();

    expect($userA->can('update', $lead))->toBeFalse();
});

test('sandbox ability allows a same-tenant sandbox lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->sandbox()->forTenant((string) $user->tenantId)->create();

    expect($user->can('sandbox', $lead))->toBeTrue();
});

test('sandbox ability denies a same-tenant non-sandbox lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant((string) $user->tenantId)->create(['is_sandbox' => false]);

    expect($user->can('sandbox', $lead))->toBeFalse();
});

test('sandbox ability denies a cross-tenant sandbox lead', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->sandbox()->forTenant((string) $userB->tenantId)->create();

    expect($userA->can('sandbox', $lead))->toBeFalse();
});

// ─── AgentPolicy ──────────────────────────────────────────────────────────────

test('user can manage own agent', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    expect($user->can('manage', $agent))->toBeTrue();
    expect($user->can('view', $agent))->toBeTrue();
    expect($user->can('update', $agent))->toBeTrue();
});

test('user cannot manage other user agent', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $userB->id]);

    expect($userA->can('manage', $agent))->toBeFalse();
    expect($userA->can('view', $agent))->toBeFalse();
    expect($userA->can('update', $agent))->toBeFalse();
});

// ─── ServiceTicketPolicy ──────────────────────────────────────────────────────

test('user can view own tenant ticket', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant($user->tenantId)->create();
    $ticket = ServiceTicket::create([
        'tenant_id' => $user->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'test',
        'summary' => 'test',
    ]);

    expect($user->can('view', $ticket))->toBeTrue();
});

test('user cannot view other tenant ticket', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();
    $ticket = ServiceTicket::create([
        'tenant_id' => $userB->tenantId,
        'lead_id' => $lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
        'reason' => 'test',
        'summary' => 'test',
    ]);

    expect($userA->can('view', $ticket))->toBeFalse();
});

// ─── Controller integration ──────────────────────────────────────────────────

test('conversas show returns 404 for cross-tenant lead', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();

    $this->actingAs($userA)
        ->get(route('conversas.show', $lead))
        ->assertNotFound();
});

test('conversas show returns 200 for own tenant lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant($user->tenantId)->create();

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk();
});
