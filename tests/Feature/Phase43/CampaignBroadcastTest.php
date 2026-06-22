<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Events\CampaignProgressUpdated;
use App\Events\CampaignStatusChanged;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Create a Campaign in 'sending' status with all FK-correct related models
 * (ContactList.tenant_id → tenants.id after the realign migration).
 *
 * @return array{campaign: Campaign, contactList: ContactList}
 */
function campaignWithTenant(array $state = []): array
{
    $user = userWithTenant();
    $tenant = $user->tenants()->first();

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);
    $contactList = ContactList::factory()->create(['tenant_id' => $tenant->id]);
    $template = WhatsappTemplate::factory()->create(['tenant_id' => $tenant->id]);

    $campaign = Campaign::factory()->sending()->create(array_merge([
        'tenant_id' => $tenant->id,
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $contactList->id,
        'whatsapp_template_id' => $template->id,
        'daily_limit' => 1000,
    ], $state));

    return compact('campaign', 'contactList');
}

it('test_sendCampaignMessageJob_dispatches_campaign_progress_on_success', function () {
    Event::fake([CampaignProgressUpdated::class]);
    Cache::flush();

    ['campaign' => $campaign, 'contactList' => $contactList] = campaignWithTenant(['total_recipients' => 1]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $contactList->id,
        'phone' => '5511999990201',
    ]);
    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'queued',
    ]);

    $mockProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $mockProvider->shouldReceive('sendTemplate')->once()->andReturn('wamid.test');

    $this->mock(WhatsAppProviderFactory::class, fn ($m) => $m->shouldReceive('makeProvider')->andReturn($mockProvider));

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    Event::assertDispatched(CampaignProgressUpdated::class, fn ($e) => $e->campaignId === $campaign->id);
});

it('test_dispatchCampaignJob_dispatches_status_changed_on_start_and_end', function () {
    Event::fake([CampaignStatusChanged::class]);
    Queue::fake();

    ['campaign' => $campaign, 'contactList' => $contactList] = campaignWithTenant(['total_recipients' => 1]);
    ContactListEntry::factory()->create(['contact_list_id' => $contactList->id]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    Event::assertDispatched(CampaignStatusChanged::class, fn ($e) => $e->campaignId === $campaign->id);
});

it('test_campaign_broadcast_debounced', function () {
    Event::fake([CampaignProgressUpdated::class]);
    Cache::flush();

    ['campaign' => $campaign, 'contactList' => $contactList] = campaignWithTenant(['total_recipients' => 2]);

    $entry1 = ContactListEntry::factory()->create([
        'contact_list_id' => $contactList->id,
        'phone' => '5511999990202',
    ]);
    $entry2 = ContactListEntry::factory()->create([
        'contact_list_id' => $contactList->id,
        'phone' => '5511999990203',
    ]);

    $msg1 = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry1->id,
        'status' => 'queued',
    ]);
    $msg2 = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry2->id,
        'status' => 'queued',
    ]);

    $mockProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $mockProvider->shouldReceive('sendTemplate')->twice()->andReturn('wamid.test');

    $this->mock(WhatsAppProviderFactory::class, fn ($m) => $m->shouldReceive('makeProvider')->andReturn($mockProvider));

    $service = app(CampaignService::class);
    $factory = app(WhatsAppProviderFactory::class);
    $debouncer = app(BroadcastDebouncer::class);

    (new SendCampaignMessageJob($msg1))->handle($service, $factory, $debouncer);
    (new SendCampaignMessageJob($msg2))->handle($service, $factory, $debouncer);

    // Second job's progress event is debounced (only 1 dispatch within the 2s window)
    Event::assertDispatchedTimes(CampaignProgressUpdated::class, 1);
});
