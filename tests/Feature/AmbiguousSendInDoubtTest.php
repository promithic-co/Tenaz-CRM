<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Exceptions\MetaAmbiguousSendException;
use App\Jobs\ProcessWhatsappOutboxMessageJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\ConversationTimelineService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Services\WhatsappOutboxService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['credflow.campaigns.rate_per_minute' => 0]);
});

function makeSendableCampaignMessage(): CampaignMessage
{
    $campaign = Campaign::factory()->sending()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
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
        'phone' => '5511999990001',
        'name' => 'Test User',
    ]);

    return CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);
}

function bindProvider(WhatsAppProviderInterface $provider): WhatsAppProviderFactory
{
    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldReceive('makeProvider')->andReturn($provider);
    app()->instance(WhatsAppProviderFactory::class, $factory);

    return $factory;
}

test('an ambiguous send marks the campaign message in_doubt without counting it failed and without re-sending', function () {
    $message = makeSendableCampaignMessage();

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    // Exactly one provider call — the job must NOT retry an undecidable send.
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaAmbiguousSendException('timeout'));
    $factory = bindProvider($provider);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('in_doubt')
        ->and($fresh->error_code)->toBe('IN_DOUBT')
        ->and($fresh->provider_attempted_at)->not->toBeNull()
        ->and($message->campaign->fresh()->total_failed)->toBe(0)
        ->and($message->campaign->fresh()->total_sent)->toBe(0);
});

test('an empty provider message id is treated as an ambiguous send', function () {
    $message = makeSendableCampaignMessage();

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andReturn('');
    $factory = bindProvider($provider);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('in_doubt');
});

test('a campaign message already marked provider_attempted is not re-sent on re-execution', function () {
    $message = makeSendableCampaignMessage();
    $message->update(['status' => 'queued', 'provider_attempted_at' => now()]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    // The defensive guard must short-circuit before any provider call.
    $provider->shouldNotReceive('sendTemplate');
    $factory = bindProvider($provider);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('in_doubt');
});

function queueOutboxRow(): WhatsappOutboxMessage
{
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->for($user)->create(['name' => 'in-doubt-outbox']);
    $lead = Lead::factory()->create([
        'tenant_id' => (string) $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
    ]);

    return app(WhatsappOutboxService::class)->queueTextForLead(
        $lead, $instance, $lead->whatsapp, 'Olá', 'agent', 'agent', 'int-in-doubt'
    );
}

test('an ambiguous outbox send (5xx) is marked in_doubt and not re-sent', function () {
    $outbox = queueOutboxRow();

    Http::fake(['graph.facebook.com/*' => Http::response('upstream error', 503)]);

    (new ProcessWhatsappOutboxMessageJob($outbox->id))
        ->handle(app(WhatsAppService::class), app(ConversationTimelineService::class));

    expect($outbox->fresh()->status)->toBe('in_doubt')
        ->and($outbox->fresh()->provider_attempted_at)->not->toBeNull();

    Http::assertSentCount(1);
});

test('an outbox row already provider-attempted is not re-sent on re-execution', function () {
    $outbox = queueOutboxRow();
    $outbox->update(['status' => 'sending', 'provider_attempted_at' => now()]);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]], 200)]);

    (new ProcessWhatsappOutboxMessageJob($outbox->id))
        ->handle(app(WhatsAppService::class), app(ConversationTimelineService::class));

    expect($outbox->fresh()->status)->toBe('in_doubt');
    Http::assertNothingSent();
});
