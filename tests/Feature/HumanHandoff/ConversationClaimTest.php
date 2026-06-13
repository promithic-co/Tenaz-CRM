<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function claimTenant(): array
{
    $tenant = Tenant::create(['name' => 'ClaimTest']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_HUMAN_PENDING,
    ]);

    return [$tenant, $owner, $lead];
}

function sameTenantUser(Tenant $tenant, string $role = 'user'): User
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => $role]);

    return $user;
}

test('route conversas.claim works and calls canonical action', function () {
    [, $user, $lead] = claimTenant();

    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();

    expect(route('conversas.claim', $lead))->toBe(url("/conversas/{$lead->id}/claim"));
});

test('operator claims unassigned conversation with existing escalation ticket', function () {
    [, $user, $lead] = claimTenant();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();

    $lead->refresh();
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($lead->assigned_user_id)->toBe($user->id);
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
    expect($ticket->assigned_user_id)->toBe($user->id);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_ASSIGNED);
});

test('operator claim creates escalation ticket when none exists', function () {
    [, $user, $lead] = claimTenant();

    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();

    $lead->refresh();
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->assigned_user_id)->toBe($user->id);
    expect($lead->assigned_user_id)->toBe($user->id);
});

test('same operator can retry claim idempotently', function () {
    [, $user, $lead] = claimTenant();

    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();
    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();

    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
    expect($lead->fresh()->assigned_user_id)->toBe($user->id);
});

test('second operator receives validation error when already claimed', function () {
    [$tenant, $owner, $lead] = claimTenant();
    $secondUser = sameTenantUser($tenant);

    $this->actingAs($owner)->post(route('conversas.claim', $lead))->assertRedirect();

    $response = $this->actingAs($secondUser)->post(route('conversas.claim', $lead));
    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

test('final ticket assignee and lead assignee match after claim', function () {
    [, $user, $lead] = claimTenant();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)->post(route('conversas.claim', $lead))->assertRedirect();

    $lead->refresh();
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();

    expect($lead->assigned_user_id)->toBe($ticket->assigned_user_id);
});

test('stale model claim does not overwrite winner', function () {
    [$tenant, $owner, $lead] = claimTenant();
    $winner = sameTenantUser($tenant, TenantRole::Owner->value);

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    // Winner claims first.
    $this->actingAs($winner)->post(route('conversas.claim', $lead))->assertRedirect();

    // Owner tries to claim the already-won lead.
    $response = $this->actingAs($owner)->post(route('conversas.claim', $lead));
    $response->assertRedirect();
    $response->assertSessionHasErrors();

    // Winner's assignment must not be overwritten.
    expect($lead->fresh()->assigned_user_id)->toBe($winner->id);
});
