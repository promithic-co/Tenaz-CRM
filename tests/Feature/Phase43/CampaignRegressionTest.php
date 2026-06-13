<?php

use App\Events\CampaignProgressUpdated;
use App\Events\CampaignStatusChanged;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Support\Facades\Cache;

it('test_campaign_dispatch_fires_progress_update', function () {
    Event::fake([CampaignProgressUpdated::class, CampaignStatusChanged::class]);
    Cache::flush();

    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 1]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'kind' => 'meta_hsm',
    ]);
    $campaign->update([
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => '5511999990001',
    ]);
    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(\App\Contracts\WhatsApp\WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.test');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    Event::assertDispatched(CampaignProgressUpdated::class);
});

it('test_campaign_progress_debounced_within_2s', function () {
    Event::fake([CampaignProgressUpdated::class]);
    Cache::flush();

    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 2]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'kind' => 'meta_hsm',
    ]);
    $campaign->update([
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $providerMock = Mockery::mock(\App\Contracts\WhatsApp\WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->andReturn('wamid.test');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $debouncer = app(BroadcastDebouncer::class);
    $service = app(CampaignService::class);

    $entry1 = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => '5511999990001',
    ]);
    $message1 = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry1->id,
        'status' => 'pending',
    ]);

    $entry2 = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => '5511999990002',
    ]);
    $message2 = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry2->id,
        'status' => 'pending',
    ]);

    // Both jobs run within the 2s debounce window — only the first fires the broadcast
    (new SendCampaignMessageJob($message1))->handle($service, $factoryMock, $debouncer);
    (new SendCampaignMessageJob($message2))->handle($service, $factoryMock, $debouncer);

    Event::assertDispatchedTimes(CampaignProgressUpdated::class, 1);
});

it('test_campaign_status_changed_on_start_and_complete', function () {
    Event::fake([CampaignStatusChanged::class]);
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 1]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
    ]);
    $campaign->update([
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);

    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    // Dispatched once on start (status=sending) and once if all sent → completed
    Event::assertDispatched(CampaignStatusChanged::class);
});
