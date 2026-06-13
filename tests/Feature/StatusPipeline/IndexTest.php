<?php

use App\Models\Tenant;
use App\Models\User;

function nonAdminUser(): array
{
    $user = User::factory()->create();
    $user->tenants()->detach();
    $tenant = Tenant::create(['name' => 'Test']);
    $user->tenants()->attach($tenant->id, ['role' => 'user']);

    return [$user, $tenant];
}

// ─── Index page access ────────────────────────────────────────────────────────

it('admin can access the pipeline index page', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)
        ->get('/configuracoes/pipeline')
        ->assertStatus(200);
});

it('non-admin (user role) gets 403 on pipeline index', function (): void {
    [$user, $tenant] = nonAdminUser();

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/configuracoes/pipeline')
        ->assertStatus(403);
});

it('unauthenticated request is redirected to login', function (): void {
    $this->get('/configuracoes/pipeline')
        ->assertRedirect('/login');
});

it('pipeline index returns statuses and canonical_slugs', function (): void {
    $user = userWithTenant();

    $response = $this->actingAs($user)
        ->get('/configuracoes/pipeline');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('configuracoes/pipeline/Index')
            ->has('statuses')
            ->has('transitions')
            ->has('canonical_slugs')
            ->has('lead_counts_by_status')
        );
});

it('pipeline index returns the 7 canonical statuses for a fresh tenant', function (): void {
    $user = userWithTenant();

    $response = $this->actingAs($user)
        ->get('/configuracoes/pipeline');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('statuses', 7)
        );
});
