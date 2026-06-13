<?php

use App\Models\Lead;
use App\Services\StatusMachineService;

// ─── DestroyStatus ────────────────────────────────────────────────────────────

it('deleting a canonical status returns 422', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->deleteJson('/configuracoes/pipeline/statuses/novo')
        ->assertStatus(422);
});

it('deleting a custom status with no leads removes it', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Aguardando Doc']);

    $this->actingAs($user)
        ->deleteJson('/configuracoes/pipeline/statuses/aguardando-doc')
        ->assertOk();

    $machine->refresh();
    $slugs = collect($machine->statuses)->pluck('slug')->all();
    expect($slugs)->not->toContain('aguardando-doc');
});

it('deleting a custom status with leads returns 409', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Em Análise']);

    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'em-analise',
    ]);

    $this->actingAs($user)
        ->deleteJson('/configuracoes/pipeline/statuses/em-analise')
        ->assertStatus(409);
});
