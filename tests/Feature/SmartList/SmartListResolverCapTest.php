<?php

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Models\ContactList;
use App\Models\Lead;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capFilters(): array
{
    return ['version' => 1, 'match' => 'all', 'rules' => []];
}

it('rejects materialize when resolved count exceeds configured cap', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    // Set cap to 5 for test efficiency
    config(['aria.smart_lists.max_resolve' => 5]);

    Lead::factory()->forTenant($tenantId)->count(6)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => capFilters(),
    ]);

    $resolver = app(SmartListResolverService::class);

    expect(fn () => $resolver->materialize($list))
        ->toThrow(InvalidFiltersException::class, 'Lista muito grande');
});

it('allows materialize when count is within cap', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    // Set cap to 10 — only 3 leads
    config(['aria.smart_lists.max_resolve' => 10]);

    Lead::factory()->forTenant($tenantId)->count(3)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => capFilters(),
    ]);

    $resolver = app(SmartListResolverService::class);
    $count = $resolver->materialize($list);

    expect($count)->toBe(3);
    expect($list->fresh()->entries_count)->toBe(3);
});

it('uses 100000 as default cap when config key is missing', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    // Clear any custom config — default should apply
    config(['aria.smart_lists.max_resolve' => 100000]);

    Lead::factory()->forTenant($tenantId)->count(2)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => capFilters(),
    ]);

    $resolver = app(SmartListResolverService::class);
    $count = $resolver->materialize($list);

    // 2 leads << 100000 default → no exception
    expect($count)->toBe(2);
});

it('exception message includes cap count and human-readable copy', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    config(['aria.smart_lists.max_resolve' => 3]);

    Lead::factory()->forTenant($tenantId)->count(4)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => capFilters(),
    ]);

    $resolver = app(SmartListResolverService::class);

    $threw = false;

    try {
        $resolver->materialize($list);
    } catch (InvalidFiltersException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('3+');
        expect($e->getMessage())->toContain('Adicione mais filtros pra reduzir.');
    }

    expect($threw)->toBeTrue();
});
