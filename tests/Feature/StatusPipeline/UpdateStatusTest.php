<?php

use App\Models\StatusMachine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StatusMachineService;

// ─── UpdateStatus ─────────────────────────────────────────────────────────────

it('admin can rename the label of a canonical status', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->putJson('/settings/pipeline/statuses/qualificado', ['label' => 'Lead Quente'])
        ->assertOk()
        ->assertJsonPath('statuses.0.slug', 'novo'); // statuses are sorted by position
});

it('canonical status label is updated in the database', function (): void {
    $user = userWithTenant();
    $tenantId = (string) $user->tenantId;

    // Force creation of a persisted machine
    app(StatusMachineService::class)->getOrCreateForTenant($tenantId);

    $this->actingAs($user)
        ->putJson('/settings/pipeline/statuses/qualificado', ['label' => 'Lead Quente'])
        ->assertOk();

    $machine = StatusMachine::where('tenant_id', $tenantId)->first();
    $status = collect($machine->statuses)->firstWhere('slug', 'qualificado');

    expect($status['label'])->toBe('Lead Quente')
        ->and($status['slug'])->toBe('qualificado');
});

it('renaming slug returns 422', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->putJson('/settings/pipeline/statuses/qualificado', ['slug' => 'qualificado_novo'])
        ->assertStatus(422);
});

it('non-admin cannot update a status', function (): void {
    $user = User::factory()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Test']);
    $user->tenants()->attach($tenant->id, ['role' => 'user']);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->putJson('/settings/pipeline/statuses/qualificado', ['label' => 'Hacked'])
        ->assertStatus(403);
});
