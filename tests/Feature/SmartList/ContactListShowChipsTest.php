<?php

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// buildFilterChips — via show endpoint (integration)
// ─────────────────────────────────────────────────────────────────────────────

it('renders empty chips array when list has no filters', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => null,
    ]);

    $response = $this->actingAs($user)
        ->get("/listas-contato/{$list->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('filterChips')
        ->where('filterChips', [])
    );
});

it('renders Tags chip with includes_all modifier', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [
                ['field' => 'tags', 'op' => 'includes_all', 'value' => ['vip', 'idoso']],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterChips', 1)
            ->where('filterChips.0.label', 'Tags')
            ->where('filterChips.0.values', ['vip', 'idoso'])
            ->where('filterChips.0.modifier', '(todas)')
        );
});

it('renders Status chip with in op (no modifier)', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [
                ['field' => 'status', 'op' => 'in', 'value' => ['qualificado']],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterChips', 1)
            ->where('filterChips.0.label', 'Status')
            ->where('filterChips.0.values', ['qualificado'])
        );
});

it('renders Inatividade chip with older_than_days value embedded inline', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [
                ['field' => 'last_interaction_at', 'op' => 'older_than_days', 'value' => 30],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterChips', 1)
            ->where('filterChips.0.label', 'Inatividade')
            ->where('filterChips.0.values', ['> 30 dias'])
        );
});

it('renders has_open_ticket chip as sim when true', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [
                ['field' => 'has_open_ticket', 'op' => 'eq', 'value' => true],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterChips', 1)
            ->where('filterChips.0.label', 'Tem ticket aberto')
            ->where('filterChips.0.values', ['sim'])
        );
});

it('exposes has_campaign_in_sending true when a campaign is sending', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => ['version' => 1, 'match' => 'all', 'rules' => []],
    ]);

    Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
        'status' => 'sending',
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('list.has_campaign_in_sending', true)
        );
});

it('exposes has_campaign_in_sending false when no campaign is sending', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create([
        'tenant_id' => $user->tenantId,
        'is_dynamic' => true,
        'filters_json' => ['version' => 1, 'match' => 'all', 'rules' => []],
    ]);

    $this->actingAs($user)
        ->get("/listas-contato/{$list->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('list.has_campaign_in_sending', false)
        );
});
