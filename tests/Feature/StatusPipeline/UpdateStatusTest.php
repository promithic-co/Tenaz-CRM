<?php

use App\Models\StatusMachine;

// ─── UpdateStatus ─────────────────────────────────────────────────────────────

it('admin can rename the label of a canonical status', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->putJson('/configuracoes/pipeline/statuses/qualificado', ['label' => 'Lead Quente'])
        ->assertOk()
        ->assertJsonPath('statuses.0.slug', 'novo'); // statuses are sorted by position
});

it('canonical status label is updated in the database', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    // Force creation of a persisted machine
    app(\App\Services\StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->putJson('/configuracoes/pipeline/statuses/qualificado', ['label' => 'Lead Quente'])
        ->assertOk();

    $machine = StatusMachine::where('tenant_id', $tenantId)->first();
    $status = collect($machine->statuses)->firstWhere('slug', 'qualificado');

    expect($status['label'])->toBe('Lead Quente')
        ->and($status['slug'])->toBe('qualificado');
});

it('renaming slug returns 422', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->putJson('/configuracoes/pipeline/statuses/qualificado', ['slug' => 'qualificado_novo'])
        ->assertStatus(422);
});

it('non-admin cannot update a status', function (): void {
    $user = \App\Models\User::factory()->create();
    $user->tenants()->detach();
    $tenant = \App\Models\Tenant::create(['name' => 'Test']);
    $user->tenants()->attach($tenant->id, ['role' => 'user']);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->putJson('/configuracoes/pipeline/statuses/qualificado', ['label' => 'Hacked'])
        ->assertStatus(403);
});
