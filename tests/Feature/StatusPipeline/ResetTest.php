<?php

use App\Models\Lead;
use App\Models\StatusMachine;
use App\Services\StatusMachineService;

// ─── Reset ────────────────────────────────────────────────────────────────────

it('reset without X-Confirm header returns 400', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/reset')
        ->assertStatus(400);
});

it('reset with X-Confirm: 1 header resets to defaults', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Custom Status']);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/reset', [], ['X-Confirm' => '1'])
        ->assertOk()
        ->assertJsonStructure(['statuses', 'transitions']);

    $machine->refresh();
    $slugs = collect($machine->statuses)->pluck('slug')->all();
    expect($slugs)->toBe(StatusMachine::CANONICAL_SLUGS);
});

it('reset restores exactly 7 canonical statuses', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Extra A']);
    app(StatusMachineService::class)->addCustomStatus($machine->refresh(), ['name' => 'Extra B']);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/reset', [], ['X-Confirm' => '1'])
        ->assertOk();

    $machine->refresh();
    expect($machine->getStatuses()->count())->toBe(7);
});

it('reset with custom status in use returns 409', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    $machine = app(StatusMachineService::class)->getOrCreateForTenant($tenantId);
    app(StatusMachineService::class)->addCustomStatus($machine, ['name' => 'Aguardando Documento']);

    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'status' => 'aguardando-documento',
    ]);

    $this->actingAs($user)
        ->postJson('/configuracoes/pipeline/reset', [], ['X-Confirm' => '1'])
        ->assertStatus(409);
});
