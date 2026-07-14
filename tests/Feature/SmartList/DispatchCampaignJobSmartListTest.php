<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('materializes dynamic list before dispatching messages', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    $leadA = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000001']);
    $leadB = Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000002']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['qualificado']]],
        ],
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    expect($list->fresh()->entries_count)->toBe(2)
        ->and($list->fresh()->last_resolved_at)->not->toBeNull();
    Queue::assertPushed(SendCampaignMessageJob::class, 2);
});

it('writes total_recipients from the materialized count for dynamic lists (CAMP-06)', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000001']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000002']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000005']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['qualificado']]],
        ],
    ]);

    // start() runs before materialization, so total_recipients is stale (0) at dispatch time.
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
        'total_recipients' => 0,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    expect($campaign->fresh()->total_recipients)->toBe(3);
});

it('does not re-materialize a dynamic list once the fan-out has begun (CAMP-06 snapshot)', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [],
        ],
    ]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'opt_in_status' => 'opted_in',
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
        'total_recipients' => 1,
    ]);

    // A message row proves the first dispatch already snapshotted the audience; a revive run
    // must reuse that snapshot, never re-resolve the dynamic list.
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
        'provider_attempted_at' => null,
    ]);

    $resolverSpy = Mockery::spy(SmartListResolverService::class);
    app()->instance(SmartListResolverService::class, $resolverSpy);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    $resolverSpy->shouldNotHaveReceived('materialize');
    expect($campaign->fresh()->total_recipients)->toBe(1);
});

it('auto-completes campaign when dynamic list resolves to 0 leads', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['inexistente_xyz']]],
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

it('does not call materialize for static lists (regression)', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => false,
    ]);

    ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $list->id,
        'opt_in_status' => 'opted_in',
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $resolverSpy = Mockery::spy(SmartListResolverService::class);
    app()->instance(SmartListResolverService::class, $resolverSpy);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    $resolverSpy->shouldNotHaveReceived('materialize');
    Queue::assertPushed(SendCampaignMessageJob::class, 2);
});

it('opt-out leads never receive messages from dynamic list', function () {
    Queue::fake([SendCampaignMessageJob::class]);

    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    Lead::factory()->forTenant($tenantId)->create(['status' => 'qualificado', 'whatsapp' => '5511900000003']);
    Lead::factory()->forTenant($tenantId)->create(['status' => 'optou_sair', 'whatsapp' => '5511900000004']);

    $list = ContactList::factory()->create([
        'tenant_id' => $tenantId,
        'is_dynamic' => true,
        'filters_json' => [
            'version' => 1,
            'match' => 'all',
            'rules' => [],
        ],
    ]);

    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $tenantId,
        'contact_list_id' => $list->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    Queue::assertPushed(SendCampaignMessageJob::class, 1);
});
