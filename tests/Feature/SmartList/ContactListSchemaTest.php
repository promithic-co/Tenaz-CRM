<?php

use App\Models\ContactList;
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

it('dynamic scope returns only dynamic lists', function () {
    ContactList::factory()->create(['is_dynamic' => false]);
    ContactList::factory()->create(['is_dynamic' => true]);
    expect(ContactList::dynamic()->count())->toBe(1)
        ->and(ContactList::static()->count())->toBe(1);
});

it('casts last_resolved_at as datetime', function () {
    $list = ContactList::factory()->create(['last_resolved_at' => now()]);
    expect($list->fresh()->last_resolved_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});
