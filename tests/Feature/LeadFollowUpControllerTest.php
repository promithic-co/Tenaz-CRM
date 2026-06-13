<?php

use App\Models\Lead;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('pause changes status from active to paused', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.pause', $lead))
        ->assertRedirect();

    expect($lead->fresh()->followup_status)->toBe('paused');
});

test('pause rejects when lead is not active', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'inactive',
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.pause', $lead))
        ->assertSessionHasErrors('followup_status');

    expect($lead->fresh()->followup_status)->toBe('inactive');
});

test('resume changes status from paused to active when inside window', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'paused',
        'last_inbound_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.resume', $lead))
        ->assertRedirect();

    expect($lead->fresh()->followup_status)->toBe('active');
});

test('resume rejects when window expired', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'paused',
        'last_inbound_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.resume', $lead))
        ->assertSessionHasErrors('followup_status');
});

test('disable flips status to inactive', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.disable', $lead))
        ->assertRedirect();

    expect($lead->fresh()->followup_status)->toBe('inactive');
});

test('disable rejects when already inactive', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'inactive',
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.disable', $lead))
        ->assertSessionHasErrors('followup_status');
});

test('reactivate re-arms inactive follow-up inside window', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'inactive',
        'status' => 'novo',
        'last_inbound_at' => now()->subHour(),
        'followup_count' => 3,
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.reactivate', $lead))
        ->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->followup_status)->toBe('active')
        ->and((int) $fresh->followup_count)->toBe(0);
});

test('reactivate rejects when window expired', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'inactive',
        'last_inbound_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.reactivate', $lead))
        ->assertSessionHasErrors('followup_status');
});

test('reactivate rejects terminal status', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'followup_status' => 'inactive',
        'status' => 'optou_sair',
        'last_inbound_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->post(route('conversas.followup.reactivate', $lead))
        ->assertSessionHasErrors('followup_status');
});

test('unauthorized user cannot pause', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $lead = Lead::factory()->create([
        'tenant_id' => $userA->tenantId,
        'followup_status' => 'active',
    ]);

    $this->actingAs($userB)
        ->post(route('conversas.followup.pause', $lead))
        ->assertNotFound();
});
