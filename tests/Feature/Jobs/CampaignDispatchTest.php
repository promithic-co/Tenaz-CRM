<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('DispatchCampaignJob creates CampaignMessages for all entries regardless of opt_in_status', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();

    ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'pending',
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(\App\Services\CampaignService::class));

    expect(CampaignMessage::where('campaign_id', $campaign->id)->count())->toBe(4);
    Queue::assertPushed(SendCampaignMessageJob::class, 4);
});

test('DispatchCampaignJob skips already-sent entries on resume', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();
    $entries = ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Pre-create one message as already sent
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(\App\Services\CampaignService::class));

    // Should only dispatch 2 new messages
    Queue::assertPushed(SendCampaignMessageJob::class, 2);
});

test('DispatchCampaignJob stops if campaign paused mid-dispatch', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create(['status' => 'paused']);

    ContactListEntry::factory()->count(5)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(\App\Services\CampaignService::class));

    Queue::assertNotPushed(SendCampaignMessageJob::class);
});

test('SendCampaignMessageJob sends template via meta cloud provider and marks sent', function () {
    $campaign = Campaign::factory()->sending()->create();
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $campaign->update(['whatsapp_instance_id' => $instance->id]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'kind' => 'meta_hsm',
    ]);
    $campaign->update(['whatsapp_template_id' => $template->id]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => '5511999990001',
        'name' => 'Test User',
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.campaign.001');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('sent');
    expect($message->fresh()->provider_message_id)->toBe('wamid.campaign.001');
});

test('SendCampaignMessageJob marks failed on provider exception', function () {
    $campaign = Campaign::factory()->sending()->create();
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $campaign->update(['whatsapp_instance_id' => $instance->id]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
    ]);
    $campaign->update(['whatsapp_template_id' => $template->id]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->andThrow(new \RuntimeException('Provider error'));

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);

    expect(fn () => $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class)))
        ->toThrow(\RuntimeException::class);

    expect($message->fresh()->status)->toBe('failed');
    expect($message->fresh()->error_code)->toBe('EXCEPTION');
});

test('SendCampaignMessageJob resolves template params from mapping', function () {
    $campaign = Campaign::factory()->sending()->create([
        'template_params_mapping' => ['1' => 'name', '2' => 'extra_data.valor'],
    ]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $campaign->update(['whatsapp_instance_id' => $instance->id]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
    ]);
    $campaign->update(['whatsapp_template_id' => $template->id]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'name' => 'Maria',
        'extra_data' => ['valor' => '5000'],
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.campaign.002');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    $resolved = $message->fresh()->template_params_resolved;
    expect($resolved['1'])->toBe('Maria');
    expect($resolved['2'])->toBe('5000');
});

test('SendCampaignMessageJob fails fast when template is not approved', function () {
    $campaign = Campaign::factory()->sending()->create();
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $campaign->update(['whatsapp_instance_id' => $instance->id]);

    $template = WhatsappTemplate::factory()->rejected()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
    ]);
    $campaign->update(['whatsapp_template_id' => $template->id]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldNotReceive('sendTemplate');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('failed');
    expect($message->fresh()->error_code)->toBe('TEMPLATE_NOT_APPROVED');
});

test('SendCampaignMessageJob builds Meta header body and button components from synced schema', function () {
    $campaign = Campaign::factory()->sending()->create([
        'template_params_mapping' => ['1' => 'name', '2' => 'extra_data.valor', '3' => 'extra_data.slug'],
    ]);
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
    ]);
    $campaign->update(['whatsapp_instance_id' => $instance->id]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'schema_template',
        'components_json' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Olá {{1}}'],
            ['type' => 'BODY', 'text' => 'Valor {{2}}'],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'Abrir', 'url' => 'https://example.com/{{3}}'],
            ]],
        ],
    ]);
    $campaign->update(['whatsapp_template_id' => $template->id]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'name' => 'Maria',
        'phone' => '5511999990099',
        'extra_data' => ['valor' => '1200', 'slug' => 'abc'],
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $phone, string $templateName, string $langCode, array $components): bool {
            return $templateName === 'schema_template'
                && $components === [
                    ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => 'Maria']]],
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '1200']]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => 'abc']]],
                ];
        })
        ->andReturn('wamid.schema');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($message->fresh()->provider_message_id)->toBe('wamid.schema');
});
