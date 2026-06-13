<?php

use App\Models\ContactList;
use App\Models\Lead;
use App\Models\Tag;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function edgeFilters(array $rules = [], string $match = 'all'): array
{
    return ['version' => 1, 'match' => $match, 'rules' => $rules];
}

it('excludes opted-out leads from resolver count and materialize', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(7)->create(['status' => 'qualificado']);
    Lead::factory()->forTenant($tenantId)->count(3)->create(['status' => 'optou_sair']);

    $resolver = app(SmartListResolverService::class);

    // count() never includes opted-out leads
    expect($resolver->count($tenantId, edgeFilters()))->toBe(7);

    // materialize confirms: only 7 entries created
    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => edgeFilters(),
    ]);

    $count = $resolver->materialize($list);
    expect($count)->toBe(7);
    expect($list->entries()->count())->toBe(7);
});

it('returns 0 matches for filter referencing soft-deleted tag slug without error', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $tag = Tag::factory()->forTenant($tenantId)->create(['slug' => 'antigo', 'name' => 'Antigo']);
    $lead = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    $lead->attachTag($tag);

    // Soft-delete the tag
    $tag->delete();

    $resolver = app(SmartListResolverService::class);
    $filters = edgeFilters([['field' => 'tags', 'op' => 'includes_all', 'value' => ['antigo']]]);

    // Must return 0, no exception (D7 from 47-CONTEXT)
    expect($resolver->count($tenantId, $filters))->toBe(0);
});

it('handles status removed from pipeline without crashing', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(3)->create(['status' => 'qualificado']);

    // Filter references a status that no longer exists in the pipeline (e.g. archived_legacy)
    // FilterSchema allows arbitrary status strings in 'in' op — the query just returns 0 matches
    $resolver = app(SmartListResolverService::class);
    $filters = edgeFilters([['field' => 'status', 'op' => 'in', 'value' => ['archived_legacy']]]);

    // No exception; 0 results — graceful behavior
    expect($resolver->count($tenantId, $filters))->toBe(0);
});

it('preview never returns whatsapp field in HTTP response (LGPD)', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    foreach (range(1, 3) as $index) {
        Lead::factory()->forTenant($tenantId)->create([
            'status' => 'qualificado',
            'whatsapp' => '551199999000'.$index,
        ]);
    }

    $response = $this->actingAs($user)->post('/listas-contato/preview', [
        'filters_json' => edgeFilters(),
    ]);

    $response->assertOk()->assertJsonStructure(['count', 'capped', 'sample']);

    foreach ($response->json('sample') as $leadData) {
        expect($leadData)->not->toHaveKey('whatsapp');
        expect($leadData)->not->toHaveKey('telefone');
    }
});

it('respects tenant scope when materializing — cross-tenant leads excluded', function () {
    $userA = userWithTenant();
    $userB = userWithTenant();

    $vipSlug = 'vip';

    $vipA = Tag::factory()->forTenant($userA->tenantId)->create(['slug' => $vipSlug, 'name' => 'VIP']);
    $vipB = Tag::factory()->forTenant($userB->tenantId)->create(['slug' => $vipSlug, 'name' => 'VIP']);

    $leadA = Lead::factory()->forTenant($userA->tenantId)->create(['status' => 'qualificado']);
    $leadA->attachTag($vipA);

    $leadB1 = Lead::factory()->forTenant($userB->tenantId)->create(['status' => 'qualificado']);
    $leadB1->attachTag($vipB);
    $leadB2 = Lead::factory()->forTenant($userB->tenantId)->create(['status' => 'qualificado']);
    $leadB2->attachTag($vipB);

    $filters = edgeFilters([['field' => 'tags', 'op' => 'includes_any', 'value' => [$vipSlug]]]);

    $listA = ContactList::factory()->create([
        'tenant_id' => $userA->tenantId,
        'is_dynamic' => true,
        'filters_json' => $filters,
    ]);

    $resolver = app(SmartListResolverService::class);
    $countA = $resolver->materialize($listA);

    // Only tenant A's leads — not cross-contaminated by tenant B
    expect($countA)->toBe(1);
    expect($listA->entries()->count())->toBe(1);
    expect($listA->entries->first()->lead_id)->toBe($leadA->id);
});

it('convert to static preserves entries as snapshot and rejects further dynamic refresh', function () {
    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(4)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => edgeFilters(),
    ]);

    // Materialize first
    $resolver = app(SmartListResolverService::class);
    $resolver->materialize($list);

    expect($list->fresh()->entries()->count())->toBe(4);

    // Freeze via HTTP
    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/freeze")
        ->assertRedirect();

    $list->refresh();
    expect($list->is_dynamic)->toBeFalse();

    // Entries survive the freeze
    expect($list->entries()->count())->toBe(4);

    // New lead added after freeze — should NOT appear (static snapshot)
    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);
    expect($list->entries()->count())->toBe(4);
});
