<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function rbacSetup(): array
{
    $tenant = Tenant::create(['name' => 'RbacTest']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create(['tenant_id' => (string) $tenant->id]);

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

function rbacMember(Tenant $tenant, string $role = TenantRole::User->value): User
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => $role]);

    return $user;
}

test('owner sees all tenant tickets on index', function () {
    [$tenant, $owner] = rbacSetup();

    $this->actingAs($owner)->get(route('atendimentos.index'))
        ->assertOk();
});

test('unauthenticated user is redirected from index', function () {
    $this->get(route('atendimentos.index'))
        ->assertRedirect();
});

test('user from another tenant cannot claim ticket', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = rbacSetup();

    $otherTenant = Tenant::create(['name' => 'OtherTenant']);
    $intruder = User::factory()->create();
    $intruder->tenants()->detach();
    $intruder->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($intruder)->post(route('atendimentos.claim', $ticket))
        ->assertNotFound();
});

test('already claimed ticket returns redirect with error flash for different user', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = rbacSetup();
    $other = rbacMember($tenant);

    // First user claims
    $ticket->update([
        'status' => ServiceTicket::STATUS_ASSIGNED,
        'assigned_user_id' => $owner->id,
        'claimed_at' => now(),
    ]);

    // Second user tries to claim
    $response = $this->actingAs($other)->post(route('atendimentos.claim', $ticket));

    $response->assertRedirect();
    $response->assertSessionHasErrors('ticket');
});

test('user cannot perform return-to-ai on ticket from another tenant', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = rbacSetup();
    $ticket->update(['status' => ServiceTicket::STATUS_ASSIGNED, 'assigned_user_id' => $owner->id]);

    $otherTenant = Tenant::create(['name' => 'OtherTenant2']);
    $intruder = User::factory()->create();
    $intruder->tenants()->detach();
    $intruder->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($intruder)->post(route('atendimentos.return-to-ai', $ticket))
        ->assertNotFound();
});

test('regular member can claim an open unassigned ticket', function () {
    [$tenant, $owner, $agent, $lead, $ticket] = rbacSetup();
    $member = rbacMember($tenant);

    $this->actingAs($member)->post(route('atendimentos.claim', $ticket))
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->assigned_user_id)->toBe($member->id);
});
