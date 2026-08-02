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
        ->get('/settings/pipeline')
        ->assertStatus(200);
});

it('non-admin (user role) gets 403 on pipeline index', function (): void {
    [$user, $tenant] = nonAdminUser();

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get('/settings/pipeline')
        ->assertStatus(403);
});

it('unauthenticated request is redirected to login', function (): void {
    $this->get('/settings/pipeline')
        ->assertRedirect('/login');
});

/**
 * Both pages moved out of /configuracoes; the old URLs stay reachable so bookmarks
 * and the links already sent to tenants keep working.
 */
it('redirects the old configuracoes URLs to their settings home', function (string $from, string $to): void {
    $this->actingAs(userWithTenant())
        ->get($from)
        ->assertRedirect($to);
})->with([
    ['/configuracoes/pipeline', '/settings/pipeline'],
    ['/configuracoes/campos', '/settings/campos'],
]);

it('pipeline index returns statuses and canonical_slugs', function (): void {
    $user = userWithTenant();

    $response = $this->actingAs($user)
        ->get('/settings/pipeline');

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
        ->get('/settings/pipeline');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('statuses', 7)
        );
});
