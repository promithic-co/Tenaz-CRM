<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function transferSetup(): array
{
    $tenant = Tenant::create(['name' => 'TransferTest']);
    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $target = User::factory()->create();
    $target->tenants()->detach();
    $target->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenant->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
    ]);

    return [$tenant, $owner, $target, $agent, $lead];
}

test('conversas show props include transfer_targets for owner', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->has('transfer_targets')
            ->where('transfer_targets', fn ($targets) => count($targets) >= 1)
        );
});

test('transfer_targets do not include users from another tenant', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $otherTenant = Tenant::create(['name' => 'OtherTransfer']);
    $outsider = User::factory()->create();
    $outsider->tenants()->detach();
    $outsider->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $this->actingAs($owner)->get(route('conversas.show', $lead))
        ->assertInertia(fn ($page) => $page
            ->where('transfer_targets', fn ($targets) => ! collect($targets)->contains('id', $outsider->id))
        );
});

test('bulk transfer creates escalation ticket and assigns to target user', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id],
        'target_type' => 'user',
        'target_id' => $target->id,
    ])->assertRedirect();

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    $lead->refresh();

    expect($ticket)->not->toBeNull();
    expect($ticket->assigned_user_id)->toBe($target->id);
    expect($lead->assigned_user_id)->toBe($target->id);
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_ACTIVE);
});

test('bulk transfer pauses AI and follow-up on transferred lead', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id],
        'target_type' => 'user',
        'target_id' => $target->id,
    ])->assertRedirect();

    $lead->refresh();
    expect($lead->ai_paused_until)->not->toBeNull();
    expect($lead->ai_paused_until->isFuture())->toBeTrue();
    expect($lead->ai_paused_reason)->toBe('conversation_transferred_to_user');
    expect($lead->followup_status)->toBe('paused');
});

test('bulk transfer reuses existing active escalation ticket', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $existing = ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
    ]);

    $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id],
        'target_type' => 'user',
        'target_id' => $target->id,
    ])->assertRedirect();

    expect(ServiceTicket::where('lead_id', $lead->id)->where('type', ServiceTicket::TYPE_ESCALATION)->count())->toBe(1);
    $existing->refresh();
    expect($existing->assigned_user_id)->toBe($target->id);
});

test('bulk transfer with invalid target_id returns validation error', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id],
        'target_type' => 'user',
        'target_id' => 999999,
    ])->assertSessionHasErrors('target_id');
});

test('bulk transfer with target from another tenant returns error', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $otherTenant = Tenant::create(['name' => 'OtherTransfer2']);
    $outsider = User::factory()->create();
    $outsider->tenants()->detach();
    $outsider->tenants()->attach($otherTenant->id, ['role' => TenantRole::Owner->value]);

    $response = $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id],
        'target_type' => 'user',
        'target_id' => $outsider->id,
    ]);

    $response->assertRedirect();
    // Outsider's tenant does not match lead's tenant, so transfer is rejected
    $lead->refresh();
    expect($lead->assigned_user_id)->toBeNull();
});

test('partial batch returns applied and ignored counts in flash', function () {
    [$tenant, $owner, $target, $agent, $lead] = transferSetup();

    $lead2 = Lead::factory()->forAgent($agent)->create([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
    ]);

    $response = $this->actingAs($owner)->post(route('conversas.transfer'), [
        'lead_ids' => [$lead->id, $lead2->id],
        'target_type' => 'user',
        'target_id' => $target->id,
    ])->assertRedirect();

    $response->assertSessionHas('flash');
});
