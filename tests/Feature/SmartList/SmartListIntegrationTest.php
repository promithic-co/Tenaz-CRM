<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Lead;
use App\Models\Tag;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches campaign with dynamic list and materializes before chunk', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = userWithTenant();
    $tenantId = $user->tenantId;

    // 30 leads with tag 'vip', 5 of those opted-out, 1 without the tag
    $vipTag = Tag::factory()->forTenant($tenantId)->create(['slug' => 'vip', 'name' => 'VIP']);

    $activeLeads = Lead::factory()->forTenant($tenantId)->count(25)->create(['status' => 'qualificado']);
    $optedOutLeads = Lead::factory()->forTenant($tenantId)->count(5)->create(['status' => 'optou_sair']);

    foreach ($activeLeads as $lead) {
        $lead->attachTag($vipTag);
    }
    foreach ($optedOutLeads as $lead) {
        $lead->attachTag($vipTag);
    }

    // 1 lead without vip tag
    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'tags', 'op' => 'includes_any', 'value' => ['vip']]],
        ],
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    // opt-out 5 excluded; active 25 included
    expect($list->fresh()->entries_count)->toBe(25);
    Queue::assertPushed(SendCampaignMessageJob::class, 25);
});

it('auto-completes campaign when dynamic list resolves to 0 leads', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = userWithTenant();
    $tenantId = $user->tenantId;

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['nao_existe_xyz']]],
        ],
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    expect($campaign->fresh()->status)->toBe('completed');
    Queue::assertPushed(SendCampaignMessageJob::class, 0);
});

it('end-to-end: create dynamic list via HTTP, dispatch, freeze to static, refresh rejected', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = userWithTenant();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->count(3)->create(['status' => 'qualificado']);

    // Create dynamic list via HTTP
    $response = $this->actingAs($user)->post('/listas-contato', [
        'name' => 'Lista Integração E2E',
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['qualificado']]],
        ],
    ]);

    $response->assertRedirect();
    $list = ContactList::where('name', 'Lista Integração E2E')->first();
    expect($list)->not->toBeNull();
    expect($list->is_dynamic)->toBeTrue();

    // Dispatch campaign
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    expect($list->fresh()->entries_count)->toBe(3);
    Queue::assertPushed(SendCampaignMessageJob::class, 3);

    // Freeze via HTTP — converts to static snapshot
    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/freeze")
        ->assertRedirect();

    $list->refresh();
    expect($list->is_dynamic)->toBeFalse();
    expect($list->entries()->count())->toBe(3);

    // Refresh on static list must be rejected (422)
    $this->actingAs($user)
        ->post("/listas-contato/{$list->id}/refresh")
        ->assertStatus(422);
});

it('respects tenant isolation — cross-tenant list access returns 403 or 404', function () {
    $userA = userWithTenant();
    $userB = userWithTenant();

    Lead::factory()->forTenant($userA->tenantId)->count(2)->create(['status' => 'qualificado']);

    $listA = ContactList::factory()->create([
        'tenant_id' => $userA->tenantId,
        'is_dynamic' => true,
        'filters_json' => ['version' => 1, 'match' => 'all', 'rules' => []],
    ]);

    // userB tries to access userA's list
    $this->actingAs($userB)
        ->post("/listas-contato/{$listA->id}/refresh")
        ->assertNotFound();
});
