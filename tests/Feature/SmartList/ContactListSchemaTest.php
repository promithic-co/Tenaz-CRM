<?php

use App\Models\ContactList;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists is_dynamic and filters_json on contact list', function () {
    $list = ContactList::factory()->create([
        'is_dynamic' => true,
        'filters_json' => ['version' => 1, 'match' => 'all', 'rules' => []],
    ]);
    $fresh = $list->fresh();
    expect($fresh->is_dynamic)->toBeTrue()
        ->and($fresh->filters_json)->toBe(['version' => 1, 'match' => 'all', 'rules' => []]);
});

it('filters lists by is_dynamic flag', function () {
    ContactList::factory()->create(['is_dynamic' => false]);
    ContactList::factory()->create(['is_dynamic' => true]);
    expect(ContactList::where('is_dynamic', true)->count())->toBe(1)
        ->and(ContactList::where('is_dynamic', false)->count())->toBe(1);
});

it('casts last_resolved_at as datetime', function () {
    $list = ContactList::factory()->create(['last_resolved_at' => now()]);
    expect($list->fresh()->last_resolved_at)->toBeInstanceOf(CarbonInterface::class);
});
