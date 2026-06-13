<?php

use App\Services\StatusMachineService;

// ─── Transitions ──────────────────────────────────────────────────────────────

it('admin can add a new transition', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/transitions', ['from' => 'convertido', 'to' => 'novo'])
        ->assertOk()
        ->assertJsonStructure(['transitions']);
});

it('removing a canonical transition returns 422', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->deleteJson('/configuracoes/pipeline/transitions/novo/qualificado')
        ->assertStatus(422);
});

it('admin can remove a non-canonical transition', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addTransition($machine, 'convertido', 'novo');

    $this->actingAs($user)
        ->deleteJson('/configuracoes/pipeline/transitions/convertido/novo')
        ->assertOk();
});

it('adding a transition with unknown slug returns 422', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/transitions', ['from' => 'inexistente', 'to' => 'novo'])
        ->assertStatus(422);
});
