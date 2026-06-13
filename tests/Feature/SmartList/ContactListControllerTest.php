<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\User;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * Helpers
 */
if (! function_exists('validFilters')) {
    function validFilters(): array
    {
        return ['version' => 1, 'match' => 'all', 'rules' => []];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Preview endpoint
// ─────────────────────────────────────────────────────────────────────────────

it('rejects preview with invalid filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/listas-contato/preview', [
            'filters_json' => ['version' => 99, 'match' => 'all', 'rules' => []],
        ])
        ->assertSessionHasErrors('filters_json');
});

it('returns preview with count, capped, and sample for valid filters', function () {
    $user = User::factory()->create();

    $this->mock(SmartListResolverService::class, function (MockInterface $mock) {
        $mock->shouldReceive('countCapped')
            ->once()
            ->andReturn(['count' => 247, 'capped' => false]);

        $mock->shouldReceive('preview')
            ->once()
            ->andReturn(new Collection([]));
    });

    $response = $this->actingAs($user)
        ->post('/listas-contato/preview', [
            'filters_json' => validFilters(),
        ]);

    $response->assertOk()->assertJsonStructure(['count', 'capped', 'sample']);
    $preview = $response->json();
    expect($preview)->toHaveKeys(['count', 'capped', 'sample'])
        ->and($preview['capped'])->toBeBool()
        ->and($preview['count'])->toBe(247);
});

it('preview sample does not leak whatsapp field', function () {
    $user = User::factory()->create();

    $lead = Lead::factory()->forTenant($user->tenantId)->create(['status' => 'qualificado']);

    $this->mock(SmartListResolverService::class, function (MockInterface $mock) use ($lead) {
        $mock->shouldReceive('countCapped')
            ->once()
            ->andReturn(['count' => 1, 'capped' => false]);

        $collection = new Collection([$lead->load('tags')]);
        $mock->shouldReceive('preview')
            ->once()
            ->andReturn($collection);
    });

    $response = $this->actingAs($user)
        ->post('/listas-contato/preview', [
            'filters_json' => validFilters(),
        ]);

    $response->assertOk();
    foreach ($response->json('sample') as $item) {
        expect($item)->not->toHaveKey('whatsapp');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// store: D-02 redirect logic
// ─────────────────────────────────────────────────────────────────────────────

it('store with is_dynamic=true redirects to show route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/listas-contato', [
            'name' => 'Lista Dinâmica Teste',
            'is_dynamic' => true,
            'filters_json' => validFilters(),
        ]);

    $list = ContactList::withoutGlobalScopes()->where('name', 'Lista Dinâmica Teste')->first();
    expect($list)->not->toBeNull()
        ->and($list->is_dynamic)->toBeTrue();

    $response->assertRedirect(route('listas-contato.show', $list));
});

it('store with is_dynamic=false redirects to index route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/listas-contato', [
            'name' => 'Lista Estática Teste',
            'is_dynamic' => false,
        ]);

    $list = ContactList::withoutGlobalScopes()->where('name', 'Lista Estática Teste')->first();
    expect($list)->not->toBeNull()
        ->and($list->is_dynamic)->toBeFalse();

    $response->assertRedirect(route('listas-contato.index'));
});

it('store flashes success message after redirect', function () {
    $user = User::factory()->create();

    // Dynamic list → success message contains the D-02 copy
    $this->actingAs($user)
        ->post('/listas-contato', [
            'name' => 'Dinâmica Flash',
            'is_dynamic' => true,
            'filters_json' => validFilters(),
        ])
        ->assertSessionHas('success');

    // Static list → also flashes success
    $this->actingAs($user)
        ->post('/listas-contato', [
            'name' => 'Estática Flash',
            'is_dynamic' => false,
        ])
        ->assertSessionHas('success');
});

// ─────────────────────────────────────────────────────────────────────────────
// updateFilters: D-14 block
// ─────────────────────────────────────────────────────────────────────────────

it('blocks updateFilters when list has campaign in sending', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
        'status' => 'sending',
    ]);

    $this->actingAs($user)
        ->patch("/listas-contato/{$list->id}/filters", [
            'filters_json' => validFilters(),
        ])
        ->assertForbidden();
});

it('allows updateFilters when no campaign is sending', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    $newFilters = ['version' => 1, 'match' => 'any', 'rules' => []];

    $this->actingAs($user)
        ->patch("/listas-contato/{$list->id}/filters", [
            'filters_json' => $newFilters,
        ])
        ->assertSessionHas('success');

    expect($list->fresh()->filters_json)->toBe($newFilters);
});

// ─────────────────────────────────────────────────────────────────────────────
// refresh
// ─────────────────────────────────────────────────────────────────────────────

it('refreshes a dynamic list and returns success flash', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    $this->mock(SmartListResolverService::class, function (MockInterface $mock) {
        $mock->shouldReceive('materialize')->once()->andReturn(42);
    });

    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/refresh")
        ->assertSessionHas('success');
});

it('rejects refresh on static list with 422', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => false,
    ]);

    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/refresh")
        ->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// freeze
// ─────────────────────────────────────────────────────────────────────────────

it('freeze converts dynamic list to static', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    $this->mock(SmartListResolverService::class, function (MockInterface $mock) {
        $mock->shouldReceive('materialize')->once()->andReturn(30);
    });

    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/freeze")
        ->assertSessionHas('success');

    expect($list->fresh()->is_dynamic)->toBeFalse();
});

it('freeze materializes the snapshot and flips is_dynamic to false', function () {
    $user = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    $this->mock(SmartListResolverService::class, function (MockInterface $mock) {
        $mock->shouldReceive('materialize')->once()->andReturn(15);
    });

    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/freeze")
        ->assertSessionHas('success');

    expect($list->fresh()->is_dynamic)->toBeFalse();
});

it('isolates tenant: cannot freeze another tenant list', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $list = ContactList::factory()->create([
        'tenant_id' => $owner->tenantId,
        'is_dynamic' => true,
        'filters_json' => validFilters(),
    ]);

    // BelongsToTenant global scope returns 404 for cross-tenant access
    $this->actingAs($other)
        ->post("/listas-contato/{$list->id}/freeze")
        ->assertNotFound();

    // List remains dynamic
    expect($list->fresh()->is_dynamic)->toBeTrue();
});
