<?php

use App\Events\LeadStatusChanged;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('accepts a valid transition novo to qualificado', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'qualificado',
        ])
        ->assertRedirect();

    expect($lead->fresh()->status)->toBe('qualificado');
});

it('accepts a manual Kanban override to any visible status', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'convertido',
        ])
        ->assertRedirect();

    expect($lead->fresh()->status)->toBe('convertido');
});

it('accepts reverse move from terminal status ignoring machine transition rules', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'convertido',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'convertido',
            'to_status' => 'novo',
        ])
        ->assertRedirect();

    expect($lead->fresh()->status)->toBe('novo');
});

it('rejects moving to a status hidden from the Kanban board', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'sem_credito',
        ])
        ->assertSessionHasErrors('to_status');

    expect($lead->fresh()->status)->toBe('novo');
});

it('rejects stale Kanban moves when the lead status already changed', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'qualificado',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'convertido',
        ])
        ->assertSessionHasErrors('from_status');

    expect($lead->fresh()->status)->toBe('qualificado');
});

it('dispatches LeadStatusChanged after successful move', function () {
    Event::fake([LeadStatusChanged::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'qualificado',
        ]);

    Event::assertDispatched(
        LeadStatusChanged::class,
        fn (LeadStatusChanged $event): bool => $event->leadId === $lead->id
            && $event->newStatus === 'qualificado',
    );
    Event::assertDispatchedTimes(LeadStatusChanged::class, 1);
});

it('returns 404 for cross-tenant lead', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $userB->tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($userA)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'qualificado',
        ])
        ->assertNotFound();
});
