<?php

use App\Models\NicheTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin can promote a tenant snapshot to system visibility', function () {
    $template = NicheTemplate::factory()->create([
        'visibility' => 'tenant',
        'origin_tenant_id' => 'tenant-x',
        'is_active' => true,
        'sort_order' => 100,
    ]);

    $this->actingAs(superAdmin())
        ->patch(route('backoffice.niche-templates.update', $template), [
            'is_active' => true,
            'visibility' => 'system',
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $template->refresh();

    expect($template->visibility)->toBe('system')
        ->and($template->sort_order)->toBe(5);
});

test('non super admin cannot reach the backoffice niche template index', function () {
    $user = userWithTenant();

    $this->actingAs($user)
        ->get(route('backoffice.niche-templates.index'))
        ->assertForbidden();
});
