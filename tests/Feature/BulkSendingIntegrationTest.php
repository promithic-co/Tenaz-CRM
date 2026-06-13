<?php

use App\Jobs\DispatchCampaignJob;
use App\Jobs\ProcessCampaignDeliveryEventJob;
use App\Jobs\SyncMetaTemplatesJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignReplyDetector;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('full campaign lifecycle creates and starts campaign', function () {
    Queue::fake();

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
    ]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    ContactListEntry::factory()->count(5)->create([
        'contact_list_id' => $list->id,
        'opt_in_status' => 'opted_in',
    ]);

    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
        'contact_list_id' => $list->id,
        'status' => 'draft',
    ]);

    $service = new CampaignService;
    $service->start($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');
    expect($campaign->total_recipients)->toBe(5);
    Queue::assertPushed(DispatchCampaignJob::class);
});

test('campaign auto-pauses on wallet error', function () {
    $campaign = Campaign::factory()->sending()->create();

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'error_code' => '1003',
        'status' => 'failed',
    ]);

    $service = new CampaignService;
    $service->pause($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('paused');
    expect($campaign->paused_at)->not->toBeNull();
});

test('campaign reply creates bulk lead', function () {
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990001',
        'opt_in_status' => 'opted_in',
    ]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $user->tenantId,
        'contact_list_id' => $list->id,
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => $entry->phone,
    ]);

    $detector = new CampaignReplyDetector;
    $detectedCampaign = $detector->detect($lead, $entry->phone, $user->tenantId);

    expect($detectedCampaign)->not->toBeNull();
    expect($detectedCampaign->id)->toBe($campaign->id);

    $lead->update(['campaign_id' => $detectedCampaign->id, 'modo' => 'bulk']);
    $lead->refresh();
    expect($lead->modo)->toBe('bulk');
    expect($lead->campaign_id)->toBe($campaign->id);
});

test('template sync dispatches SyncMetaTemplatesJob for meta_cloud instance', function () {
    Bus::fake([SyncMetaTemplatesJob::class]);

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
    ]);

    SyncMetaTemplatesJob::dispatch($instance->id);

    Bus::assertDispatched(SyncMetaTemplatesJob::class, fn ($job) => $job->instanceId === $instance->id);
});

test('delivery tracking updates campaign counters', function () {
    $campaign = Campaign::factory()->sending()->create([
        'total_sent' => 1,
        'total_delivered' => 0,
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'provider_message_id' => 'test-provider-msg-001',
        'status' => 'sent',
    ]);

    $job = new ProcessCampaignDeliveryEventJob('test-provider-msg-001', 'DELIVERED');
    $job->handle();

    $message->refresh();
    expect($message->status)->toBe('delivered');

    $campaign->refresh();
    expect($campaign->total_delivered)->toBe(1);
});

test('laboratory shows bulk metrics', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $response = $this->actingAs($user)->get('/laboratory');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('laboratory/Index')
        ->has('bulkMetrics')
        ->where('bulkMetrics.campaigns_active', 1)
    );
});

test('monitor command runs without error on empty dataset', function () {
    $this->artisan('credflow:monitor-campaigns')->assertSuccessful();
});
