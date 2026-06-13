<?php

use App\Models\Lead;
use App\Services\StatusMachineService;
use Carbon\Carbon;

// ─── Manual lead status transitions ───────────────────────────────────────────

it('admin can transition a lead to a reachable status', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", ['status' => 'qualificado'])
        ->assertRedirect('/conversas');

    expect($lead->fresh()->status)->toBe('qualificado');
});

it('manual status transition pauses AI for 24 hours', function (): void {
    $frozenTime = Carbon::parse('2026-05-19 12:00:00');
    Carbon::setTestNow($frozenTime);

    try {
        $user = userWithTenant();
        $tenantId = (string) $user->tenantId;
        app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

        $lead = Lead::factory()->create([
            'tenant_id' => $tenantId,
            'status' => 'novo',
        ]);

        $this->actingAs($user)
            ->from('/conversas')
            ->post("/leads/{$lead->id}/status", ['status' => 'qualificado'])
            ->assertRedirect('/conversas');

        $lead->refresh();

        expect($lead->status)->toBe('qualificado')
            ->and($lead->ai_paused_until->equalTo($frozenTime->copy()->addHours(24)))->toBeTrue()
            ->and($lead->ai_paused_by)->toBe($user->id)
            ->and($lead->ai_paused_reason)->toBe('manual_status_override');
    } finally {
        Carbon::setTestNow();
    }
});

it('rejects unreachable transitions with 422', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", ['status' => 'convertido'])
        ->assertStatus(302) // redirect-back with session error
        ->assertSessionHasErrors('status');

    expect($lead->fresh()->status)->toBe('novo');
});

it('same-status post is a no-op (idempotent)', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'qualificado',
        'ai_paused_until' => null,
        'ai_paused_by' => null,
        'ai_paused_reason' => null,
    ]);

    $originalUpdatedAt = $lead->updated_at;

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", ['status' => 'qualificado'])
        ->assertSessionHasNoErrors();

    $lead->refresh();

    expect($lead->updated_at->equalTo($originalUpdatedAt))->toBeTrue()
        ->and($lead->ai_paused_until)->toBeNull()
        ->and($lead->ai_paused_by)->toBeNull()
        ->and($lead->ai_paused_reason)->toBeNull();
});

it('requires status field', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", [])
        ->assertSessionHasErrors('status');
});
