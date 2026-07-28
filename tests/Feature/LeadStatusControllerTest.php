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

it('allows a transition the graph does not contain', function (): void {
    // 'novo' → 'convertido' is not an edge in the default machine. Manual moves
    // deliberately ignore the graph — it only constrains automation.
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
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->status)->toBe('convertido');
});

it('allows reverting out of a terminal status', function (string $from, string $to): void {
    // The regression this feature exists for: 'optou_sair' and 'convertido' have no
    // outgoing edges at all, so the operator could not undo either one.
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => $from,
    ]);

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", ['status' => $to])
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->status)->toBe($to);
})->with([
    ['optou_sair', 'novo'],
    ['optou_sair', 'qualificado'],
    ['convertido', 'qualificado'],
    ['desqualificado', 'novo'],
    ['escalado', 'novo'],
]);

it('rejects a status slug that is not in the tenant pipeline', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'novo',
    ]);

    $this->actingAs($user)
        ->from('/conversas')
        ->post("/leads/{$lead->id}/status", ['status' => 'status_inventado'])
        ->assertStatus(302)
        ->assertSessionHasErrors('status');

    expect($lead->fresh()->status)->toBe('novo');
});

it('keeps the transition graph in force for automation', function (): void {
    // Lead::canTransitionTo is what the AI tools and lifecycle services call. The
    // manual path getting looser must not loosen this one.
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $lead = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'optou_sair',
    ]);

    expect($lead->canTransitionTo('qualificado'))->toBeFalse()
        ->and($lead->canTransitionTo('novo'))->toBeFalse();
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
