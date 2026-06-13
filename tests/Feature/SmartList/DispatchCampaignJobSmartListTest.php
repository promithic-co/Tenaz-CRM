<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Services\CampaignService;
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

    $resolverSpy = Mockery::spy(\App\Services\SmartList\SmartListResolverService::class);
    app()->instance(\App\Services\SmartList\SmartListResolverService::class, $resolverSpy);

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
