<?php

use App\Enums\TaggableSource;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tag;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function buildFilters(array $rules, string $match = 'all'): array
{
    return ['version' => 1, 'match' => $match, 'rules' => $rules];
}

// ─── Tags: includes_all ──────────────────────────────────────────────────────

it('count with tags includes_all returns leads with ALL listed tags', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $vip = Tag::factory()->forTenant($tenantId)->create(['slug' => 'vip', 'name' => 'VIP']);
    $idoso = Tag::factory()->forTenant($tenantId)->create(['slug' => 'idoso', 'name' => 'Idoso']);

    $both = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $both->attachTag($vip);
    $both->attachTag($idoso);

    $onlyVip = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $onlyVip->attachTag($vip);

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tags', 'op' => 'includes_all', 'value' => ['vip', 'idoso']]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── Tags: includes_any ──────────────────────────────────────────────────────

it('count with tags includes_any returns leads with EITHER tag', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $vip = Tag::factory()->forTenant($tenantId)->create(['slug' => 'vip', 'name' => 'VIP']);
    $urgente = Tag::factory()->forTenant($tenantId)->create(['slug' => 'urgente', 'name' => 'Urgente']);

    $lead1 = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $lead1->attachTag($vip);

    $lead2 = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $lead2->attachTag($urgente);

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']); // no tags

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tags', 'op' => 'includes_any', 'value' => ['vip', 'urgente']]]);

    expect($resolver->count($tenantId, $filters))->toBe(2);
});

// ─── Tags: excludes ──────────────────────────────────────────────────────────

it('count with tags excludes returns leads without those tags', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $bad = Tag::factory()->forTenant($tenantId)->create(['slug' => 'optou-sair-tag', 'name' => 'Optou Sair Tag']);

    $clean = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $dirty = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $dirty->attachTag($bad);

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tags', 'op' => 'excludes', 'value' => ['optou-sair-tag']]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── tag_is_hot ──────────────────────────────────────────────────────────────

it('count with tag_is_hot eq true returns only leads with at least one hot tag', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $hotTag = Tag::factory()->forTenant($tenantId)->hot()->create();
    $coldTag = Tag::factory()->forTenant($tenantId)->create();

    $hot = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $hot->attachTag($hotTag);

    $cold = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $cold->attachTag($coldTag);

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']); // no tags

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tag_is_hot', 'op' => 'eq', 'value' => true]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── tag_source ──────────────────────────────────────────────────────────────

it('count with tag_source eq ai returns leads with at least one ai-sourced tag', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $tag = Tag::factory()->forTenant($tenantId)->create(['slug' => 'some-tag', 'name' => 'Some Tag']);

    $aiTagged = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $aiTagged->attachTag($tag, TaggableSource::Ai);

    $manualTagged = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $manualTagged->attachTag($tag, TaggableSource::Manual);

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']); // no tag

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tag_source', 'op' => 'eq', 'value' => 'ai']]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── Status ──────────────────────────────────────────────────────────────────

it('count with status in returns matching leads', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'sem_credito']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'novo']);

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'status', 'op' => 'in', 'value' => ['qualificado', 'sem_credito']]]);

    expect($resolver->count($tenantId, $filters))->toBe(2);
});

// ─── agent_id ────────────────────────────────────────────────────────────────

it('count with agent_id eq returns leads with that agent', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'agent_id' => $agent->id]);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'agent_id' => null]);

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'agent_id', 'op' => 'eq', 'value' => $agent->id]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── last_interaction_at ─────────────────────────────────────────────────────

it('count with last_interaction_at older_than_days 30 returns stale leads', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'last_interaction_at' => now()->subDays(45),
    ]);
    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'last_interaction_at' => now()->subDays(5),
    ]);
    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'last_interaction_at' => null,
    ]);

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'last_interaction_at', 'op' => 'older_than_days', 'value' => 30]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── has_open_ticket ─────────────────────────────────────────────────────────

it('count with has_open_ticket eq true returns leads with open tickets', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $withTicket = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    ServiceTicket::create([
        'lead_id' => $withTicket->id,
        'tenant_id' => $tenantId,
        'type' => 'escalation',
        'status' => 'open',
        'closed_at' => null,
    ]);

    $withClosedTicket = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    ServiceTicket::create([
        'lead_id' => $withClosedTicket->id,
        'tenant_id' => $tenantId,
        'type' => 'escalation',
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']); // no ticket

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'has_open_ticket', 'op' => 'eq', 'value' => true]]);

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

// ─── match=all vs match=any ───────────────────────────────────────────────────

it('match=all requires ALL rules to match', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'agent_id' => $agent->id,
    ]); // matches both

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'agent_id' => null,
    ]); // matches status only

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'novo',
        'agent_id' => $agent->id,
    ]); // matches agent only

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([
        ['field' => 'status', 'op' => 'in', 'value' => ['qualificado']],
        ['field' => 'agent_id', 'op' => 'eq', 'value' => $agent->id],
    ], 'all');

    expect($resolver->count($tenantId, $filters))->toBe(1);
});

it('match=any requires at least ONE rule to match', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;
    $agent = Agent::factory()->create(['user_id' => $user->id]);

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'agent_id' => $agent->id,
    ]); // matches both

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'agent_id' => null,
    ]); // matches status only

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'novo',
        'agent_id' => $agent->id,
    ]); // matches agent only

    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'novo',
        'agent_id' => null,
    ]); // matches neither

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([
        ['field' => 'status', 'op' => 'in', 'value' => ['qualificado']],
        ['field' => 'agent_id', 'op' => 'eq', 'value' => $agent->id],
    ], 'any');

    expect($resolver->count($tenantId, $filters))->toBe(3);
});

// ─── Opt-out compliance ──────────────────────────────────────────────────────

it('never returns opt-out leads even without rule', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'optou_sair']);

    $resolver = app(SmartListResolverService::class);

    expect($resolver->count($tenantId, buildFilters([])))->toBe(1);
});

// ─── Soft-deleted tags ────────────────────────────────────────────────────────

it('treats soft-deleted tags as no match without error', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $tag = Tag::factory()->forTenant($tenantId)->create(['slug' => 'vip', 'name' => 'VIP']);
    $lead = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $lead->attachTag($tag);

    $tag->delete(); // soft-delete

    $resolver = app(SmartListResolverService::class);
    $filters = buildFilters([['field' => 'tags', 'op' => 'includes_any', 'value' => ['vip']]]);

    expect($resolver->count($tenantId, $filters))->toBe(0);
});

// ─── Tenant isolation ────────────────────────────────────────────────────────

it('isolates by tenant — leads from other tenants never appear', function () {
    $userA = userWithTenant();
    $userB = userWithTenant();

    Lead::factory()->forTenant($userA->tenantId)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($userB->tenantId)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($userB->tenantId)->create(['status' => 'qualificado']);

    $resolver = app(SmartListResolverService::class);

    expect($resolver->count($userA->tenantId, buildFilters([])))->toBe(1);
    expect($resolver->count($userB->tenantId, buildFilters([])))->toBe(2);
});

// ─── Preview ─────────────────────────────────────────────────────────────────

it('preview returns at most the given limit ordered by last_interaction_at desc', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    for ($i = 0; $i < 10; $i++) {
        Lead::factory()->forTenant($tenantId)->create([
            'status' => 'qualificado',
            'last_interaction_at' => now()->subDays($i + 1),
        ]);
    }

    $resolver = app(SmartListResolverService::class);
    $results = $resolver->preview($tenantId, buildFilters([]), limit: 5);

    expect($results)->toHaveCount(5);

    // Verify descending order
    $dates = $results->pluck('last_interaction_at')->filter()->values();
    for ($i = 0; $i < $dates->count() - 1; $i++) {
        expect($dates[$i]->gte($dates[$i + 1]))->toBeTrue();
    }
});

it('preview does not include whatsapp field (LGPD)', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);

    $resolver = app(SmartListResolverService::class);
    $results = $resolver->preview($tenantId, buildFilters([]), limit: 5);

    expect($results->first()->toArray())->not->toHaveKey('whatsapp');
});

it('preview eager-loads tags', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $tag = Tag::factory()->forTenant($tenantId)->create();
    $lead = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $lead->attachTag($tag);

    $resolver = app(SmartListResolverService::class);
    $results = $resolver->preview($tenantId, buildFilters([]), limit: 5);

    expect($results->first()->relationLoaded('tags'))->toBeTrue();
});

// ─── countCapped (D-07) ───────────────────────────────────────────────────────

it('countCapped returns capped=false when below cap', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(50)->create(['status' => 'qualificado']);

    $resolver = app(SmartListResolverService::class);
    $result = $resolver->countCapped($tenantId, buildFilters([]), cap: 5001);

    expect($result)->toBe(['count' => 50, 'capped' => false]);
});

it('countCapped returns capped=true and count=5000 when at/above cap', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(5001)->create(['status' => 'qualificado']);

    $resolver = app(SmartListResolverService::class);
    $result = $resolver->countCapped($tenantId, buildFilters([]), cap: 5001);

    expect($result)->toBe(['count' => 5000, 'capped' => true]);
});

// ─── Materialize ─────────────────────────────────────────────────────────────

it('materialize throws LogicException for static lists', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => false,
    ]);

    $resolver = app(SmartListResolverService::class);

    expect(fn () => $resolver->materialize($list))->toThrow(LogicException::class, 'Only dynamic lists can be materialized.');
});

it('materialize inserts entries and updates list counters', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(3)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'optou_sair']); // excluded

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => buildFilters([]),
    ]);

    $resolver = app(SmartListResolverService::class);
    $count = $resolver->materialize($list);

    expect($count)->toBe(3);
    expect($list->fresh()->entries_count)->toBe(3);
    expect($list->fresh()->last_resolved_count)->toBe(3);
    expect($list->fresh()->last_resolved_at)->not->toBeNull();
    expect($list->entries()->count())->toBe(3);
});

it('materialize dedups leads sharing a canonical contact into one entry', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $contact = Contact::factory()->forTenant((string) $tenantId)->create();

    // Same person, two leads → must collapse to a single entry.
    Lead::factory()->forTenant($tenantId)->count(2)->create([
        'status' => 'qualificado',
        'contact_id' => $contact->id,
    ]);

    // A different person → its own entry.
    Lead::factory()->forTenant($tenantId)->create([
        'status' => 'qualificado',
        'contact_id' => Contact::factory()->forTenant((string) $tenantId)->create()->id,
    ]);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => buildFilters([]),
    ]);

    $resolver = app(SmartListResolverService::class);
    $count = $resolver->materialize($list);

    expect($count)->toBe(2);
    expect($list->entries()->count())->toBe(2);
});

it('materialize uses per-list lock key preventing double materialization', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(2)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => buildFilters([]),
    ]);

    $resolver = app(SmartListResolverService::class);

    // First materialize: inserts 2 entries
    $count1 = $resolver->materialize($list);
    expect($count1)->toBe(2);
    expect($list->entries()->count())->toBe(2);

    // Second materialize: clears + re-inserts (not doubles) — proves idempotent
    $count2 = $resolver->materialize($list);
    expect($count2)->toBe(2);
    expect($list->entries()->count())->toBe(2); // still 2, not 4
});
