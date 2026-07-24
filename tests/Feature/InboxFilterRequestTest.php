<?php

use App\Http\Requests\InboxFilterRequest;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Validation + normalization parity for InboxFilterRequest (Plan B.1), the
 * FormRequest port of ConversasController::parseInboxFilters.
 */
test('defaults are applied when no query string is present', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.group', 'todas')
            ->where('filters.status', 'todos')
            ->where('filters.instance', '')
            ->where('filters.search', '')
            ->where('filters.ai_mode', 'todos')
            ->where('filters.stage', 'todos')
            ->where('filters.assigned', 'todos')
            ->where('filters.sort', 'last_interaction_at')
            ->where('filters.direction', 'desc')
        );
});

test('whitelisted sort is honored and direction asc is preserved', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['sort' => 'nome', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'nome')
            ->where('filters.direction', 'asc')
        );
});

test('non-whitelisted sort falls back to last_interaction_at and bad direction to desc', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['sort' => 'cpf', 'direction' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'last_interaction_at')
            ->where('filters.direction', 'desc')
        );
});

test('an unknown group falls back to todas instead of erroring the page', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['group' => 'inventado']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.group', 'todas'));
});

test('the queue tab defaults to oldest first and any other tab to newest first', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['group' => 'fila']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.group', 'fila')
            ->where('filters.direction', 'asc')
        );

    $this->actingAs($user)
        ->get(route('conversas.index', ['group' => 'minhas']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.direction', 'desc'));
});

test('an explicit direction wins over the queue default', function () {
    $user = User::factory()->create();
    Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)
        ->get(route('conversas.index', ['group' => 'fila', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.direction', 'desc'));
});

test('authorize denies an unauthenticated request', function () {
    $request = InboxFilterRequest::create('/conversas', 'GET');

    expect($request->authorize())->toBeFalse();
});

test('normalized payload passes the validation rules and rejects bad sort/direction', function () {
    $rules = (new InboxFilterRequest)->rules();

    $valid = Validator::make([
        'group' => 'todas',
        'status' => 'todos',
        'instance' => '',
        'search' => '',
        'ai_mode' => 'todos',
        'stage' => 'todos',
        'assigned' => 'todos',
        'sort' => 'last_interaction_at',
        'direction' => 'desc',
    ], $rules);

    expect($valid->passes())->toBeTrue();

    $invalid = Validator::make([
        'group' => 'inventado',
        'status' => 'todos',
        'instance' => '',
        'search' => '',
        'ai_mode' => 'todos',
        'stage' => 'todos',
        'assigned' => 'todos',
        'sort' => 'cpf',
        'direction' => 'sideways',
    ], $rules);

    expect($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->keys())->toContain('group')
        ->toContain('sort')
        ->toContain('direction');
});
