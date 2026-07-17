<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Exceptions\CampaignConfigurationException;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/** @return array{Campaign, WhatsappInstance, WhatsappTemplate, ContactList} */
function phase09BarrierCampaign(string $status = 'draft'): array
{
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'meta_phone_number_id' => '111111111111111',
        'meta_waba_id' => 'waba-barrier',
        'meta_access_token' => 'token-barrier',
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => 'meta_hsm',
        'status' => 'APPROVED',
        'meta_template_name' => 'template_barrier',
        'language' => 'pt_BR',
        'meta_waba_id' => $instance->meta_waba_id,
        'variables_count' => 0,
    ]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
        'contact_list_id' => $list->id,
        'status' => $status,
    ]);

    return [$campaign, $instance, $template, $list];
}

function phase09BarrierMessage(
    Campaign $campaign,
    ContactList $list,
    string $phone = '5565999999999',
): CampaignMessage {
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => $phone,
        'opt_in_status' => 'opted_in',
    ]);

    return CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'queued',
    ]);
}

test('start and resume validate compatibility before transition or dispatch', function (string $initialStatus) {
    Queue::fake();
    [$campaign, $instance, $template] = phase09BarrierCampaign($initialStatus);
    $template->update(['meta_waba_id' => 'waba-other']);

    $service = app(CampaignService::class);
    $operation = $initialStatus === 'paused' ? 'resume' : 'start';

    expect(fn () => $service->{$operation}($campaign))->toThrow(RuntimeException::class);
    expect($campaign->fresh()->status)->toBe($initialStatus);
    Queue::assertNotPushed(DispatchCampaignJob::class);
})->with(['draft', 'scheduled', 'paused']);

test('duplicate start attempts dispatch the fanout only once', function () {
    Queue::fake();
    [$campaign] = phase09BarrierCampaign('draft');
    $service = app(CampaignService::class);

    $service->start($campaign);
    expect(fn () => $service->start($campaign->fresh()))->toThrow(RuntimeException::class);

    expect($campaign->fresh()->status)->toBe('sending');
    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

test('dispatch pauses incompatible campaign before materialization or fanout', function () {
    Queue::fake();
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    ContactListEntry::factory()->count(600)->create(['contact_list_id' => $list->id]);
    $template->update(['whatsapp_instance_id' => WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $campaign->tenant_id,
    ])->id]);

    (new DispatchCampaignJob($campaign))->handle(app(CampaignService::class));

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('paused')
        ->and($fresh->pause_reason_code)->toBe('TEMPLATE_INSTANCE_MISMATCH')
        ->and(CampaignMessage::where('campaign_id', $campaign->id)->count())->toBe(0);
    Queue::assertNotPushed(SendCampaignMessageJob::class);
});

test('send-time violation pauses atomically, fails only current message, and later jobs park', function () {
    Http::fake();
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $current = phase09BarrierMessage($campaign, $list);
    $later = phase09BarrierMessage($campaign, $list, '5565888888888');
    $template->update(['meta_waba_id' => 'waba-other']);

    $job = new SendCampaignMessageJob($current);
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    expect($campaign->fresh()->status)->toBe('paused')
        ->and($campaign->fresh()->pause_reason_code)->toBe('TEMPLATE_WABA_MISMATCH')
        ->and($current->fresh()->status)->toBe('failed')
        ->and($current->fresh()->error_code)->toBe('TEMPLATE_WABA_MISMATCH')
        ->and($current->fresh()->provider_attempted_at)->toBeNull()
        ->and($later->fresh()->status)->toBe('queued');
    Http::assertNothingSent();

    $laterJob = new SendCampaignMessageJob($later);
    $laterJob->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    expect($later->fresh()->status)->toBe('pending')
        ->and($later->fresh()->provider_attempted_at)->toBeNull();
    Http::assertNothingSent();
});

test('campaign pause and current-message failure roll back together on a write fault', function () {
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $message = phase09BarrierMessage($campaign, $list);
    $exception = new CampaignConfigurationException(['TEMPLATE_WABA_MISMATCH']);

    DB::statement(<<<'SQL'
        CREATE TRIGGER phase09_fail_configuration_message_update
        BEFORE UPDATE ON campaign_messages
        WHEN NEW.status = 'failed'
        BEGIN
            SELECT RAISE(ABORT, 'forced message failure');
        END
        SQL);

    expect(fn () => app(CampaignService::class)->pauseAndFailForConfigurationViolation(
        $campaign,
        $message,
        $exception,
    ))->toThrow(QueryException::class);

    expect($campaign->fresh()->status)->toBe('sending')
        ->and($campaign->fresh()->pause_reason_code)->toBeNull()
        ->and($message->fresh()->status)->toBe('queued')
        ->and($message->fresh()->error_code)->toBeNull();
});

test('a stale job cannot claim a message made terminal by configuration handling', function () {
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $staleMessage = phase09BarrierMessage($campaign, $list);
    $exception = new CampaignConfigurationException(['TEMPLATE_WABA_MISMATCH']);

    expect(app(CampaignService::class)->pauseAndFailForConfigurationViolation(
        $campaign,
        $staleMessage,
        $exception,
    ))->toBeTrue();

    expect($staleMessage->status)->toBe('queued')
        ->and($staleMessage->claimProviderAttempt())->toBeFalse()
        ->and($staleMessage->fresh()->status)->toBe('failed')
        ->and($staleMessage->fresh()->provider_attempted_at)->toBeNull();
});

test('configuration pause preserves a message already claimed by the provider winner', function (string $outcome) {
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $message = phase09BarrierMessage($campaign, $list);
    $exception = new CampaignConfigurationException(['TEMPLATE_WABA_MISMATCH']);

    expect($message->claimProviderAttempt())->toBeTrue();
    $claimedAt = $message->fresh()->provider_attempted_at;

    expect(app(CampaignService::class)->pauseAndFailForConfigurationViolation(
        $campaign,
        $message,
        $exception,
    ))->toBeTrue();

    $claimedMessage = $message->fresh();

    expect($campaign->fresh()->status)->toBe('paused')
        ->and($claimedMessage->status)->toBe('queued')
        ->and($claimedMessage->provider_attempted_at?->equalTo($claimedAt))->toBeTrue()
        ->and($claimedMessage->error_code)->toBeNull()
        ->and($claimedMessage->error_message)->toBeNull()
        ->and($claimedMessage->failed_at)->toBeNull();

    if ($outcome === 'sent') {
        $claimedMessage->markSent('wamid.claim-winner');
    } else {
        $claimedMessage->markInDoubt('Provider response was ambiguous.');
    }

    $finalizedMessage = $claimedMessage->fresh();

    expect($finalizedMessage->status)->toBe($outcome)
        ->and($finalizedMessage->provider_message_id)->toBe($outcome === 'sent' ? 'wamid.claim-winner' : null)
        ->and($finalizedMessage->error_code)->toBe($outcome === 'in_doubt' ? 'IN_DOUBT' : null)
        ->and($finalizedMessage->failed_at)->toBeNull();
})->with(['sent', 'in_doubt']);

test('configuration violation pauses a sending campaign after the message already reached an outcome', function (string $outcome) {
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $message = phase09BarrierMessage($campaign, $list);
    $exception = new CampaignConfigurationException(['TEMPLATE_WABA_MISMATCH']);

    expect($message->claimProviderAttempt())->toBeTrue();

    if ($outcome === 'sent') {
        $message->markSent('wamid.outcome-before-lock');
    } else {
        $message->markInDoubt('Provider response was ambiguous.');
    }

    $messageBeforePause = $message->fresh();

    expect(app(CampaignService::class)->pauseAndFailForConfigurationViolation(
        $campaign,
        $messageBeforePause,
        $exception,
    ))->toBeTrue();

    expect($campaign->fresh()->status)->toBe('paused')
        ->and($campaign->fresh()->pause_reason_code)->toBe('TEMPLATE_WABA_MISMATCH')
        ->and($message->fresh()->getAttributes())->toBe($messageBeforePause->getAttributes());
})->with(['sent', 'in_doubt']);

test('duplicate incompatible jobs neither claim nor call the provider', function () {
    Http::fake();
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $message = phase09BarrierMessage($campaign, $list);
    $template->update(['meta_waba_id' => null]);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('failed')
        ->and($message->fresh()->provider_attempted_at)->toBeNull();
    Http::assertNothingSent();
});

test('a validated immutable snapshot cannot mix configuration changed before provider claim', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.snapshot']]], 200)]);
    [$campaign, $instance, $template, $list] = phase09BarrierCampaign('sending');
    $message = phase09BarrierMessage($campaign, $list);
    $baselineTransactionLevel = DB::transactionLevel();

    $factory = new class($instance->id, $template->id) extends WhatsAppProviderFactory
    {
        public int $transactionLevelAtProviderConstruction = -1;

        public function __construct(
            private readonly int $instanceId,
            private readonly int $templateId,
        ) {}

        public function makeProvider(WhatsappInstance $instance, bool $allowExpiredToken = false): WhatsAppProviderInterface
        {
            $this->transactionLevelAtProviderConstruction = DB::transactionLevel();

            WhatsappInstance::withoutGlobalScopes()->findOrFail($this->instanceId)->update([
                'meta_phone_number_id' => '222222222222222',
                'meta_waba_id' => 'waba-raced',
                'meta_access_token' => 'token-raced',
            ]);
            WhatsappTemplate::withoutGlobalScopes()->findOrFail($this->templateId)->update([
                'meta_template_name' => 'template_raced',
                'meta_waba_id' => 'waba-raced',
            ]);

            return parent::makeProvider($instance, $allowExpiredToken);
        }
    };

    (new SendCampaignMessageJob($message))->handle(
        app(CampaignService::class),
        $factory,
        app(BroadcastDebouncer::class),
    );

    expect($message->fresh()->status)->toBe('sent')
        ->and($message->fresh()->provider_message_id)->toBe('wamid.snapshot')
        ->and($instance->fresh()->meta_phone_number_id)->toBe('222222222222222')
        ->and($template->fresh()->meta_template_name)->toBe('template_raced')
        ->and($factory->transactionLevelAtProviderConstruction)->toBe($baselineTransactionLevel);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/111111111111111/messages')
        && $request->hasHeader('Authorization', 'Bearer token-barrier')
        && $request['template']['name'] === 'template_barrier'
        && $request['template']['language']['code'] === 'pt_BR');
});
