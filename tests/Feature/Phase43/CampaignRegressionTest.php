<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
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
    makeCampaignMetaConfigurationCompatible($campaign);

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

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
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
    makeCampaignMetaConfigurationCompatible($campaign);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
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

it('maps template parameters by placeholder number, not appearance order', function () {
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
        // Placeholders deliberately out of appearance order: {{2}} precedes {{1}}.
        'components_json' => [
            ['type' => 'BODY', 'text' => 'Saldo {{2}} vence em {{1}}'],
        ],
    ]);
    $campaign->update([
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
        'template_params_mapping' => ['1' => 'extra_data.data', '2' => 'extra_data.valor'],
    ]);
    makeCampaignMetaConfigurationCompatible($campaign);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => '5511999990001',
        'extra_data' => ['data' => '10/07', 'valor' => 'R$ 1.000'],
    ]);
    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $capturedComponents = null;
    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')
        ->once()
        ->andReturnUsing(function ($phone, $name, $lang, $components, $opaqueId) use (&$capturedComponents) {
            $capturedComponents = $components;

            return 'wamid.test';
        });

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    // parameters[0] fills {{1}} (the "data" value), parameters[1] fills {{2}} (the "valor" value),
    // regardless of the placeholders appearing as {{2}} … {{1}} in the body text.
    $body = collect($capturedComponents)->firstWhere('type', 'body');
    expect($body['parameters'])->toBe([
        ['type' => 'text', 'text' => '10/07'],
        ['type' => 'text', 'text' => 'R$ 1.000'],
    ]);
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
    makeCampaignMetaConfigurationCompatible($campaign);

    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    // Dispatched once on start (status=sending) and once if all sent → completed
    Event::assertDispatched(CampaignStatusChanged::class);
});
