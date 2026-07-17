<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Exceptions\MetaAmbiguousSendException;
use App\Exceptions\MetaApiException;
use App\Exceptions\MetaCampaignConfigurationException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use App\Exceptions\MetaRetryableException;
use App\Jobs\SendCampaignMessageJob;
use App\Models\AgentInteractionEvent;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'credflow.campaigns.rate_per_minute' => 0,
        'credflow.campaigns.tenant_rate_per_minute' => 0,
    ]);
});

function makeTaxonomyCampaignMessage(string $phone = '5511999990001'): CampaignMessage
{
    $campaign = Campaign::factory()->sending()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $campaign->tenant_id,
        'tenant_id' => (string) $campaign->tenant_id,
        'meta_waba_id' => 'waba-taxonomy',
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'meta_waba_id' => 'waba-taxonomy',
        'meta_template_name' => 'taxonomy_template',
        'language' => 'pt_BR',
        'status' => 'APPROVED',
    ]);
    $campaign->update([
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
        'phone' => $phone,
    ]);

    return CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);
}

function runTaxonomyJob(CampaignMessage $message, WhatsAppProviderInterface $provider, string $interactionId): void
{
    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldReceive('makeProvider')->andReturn($provider);

    (new SendCampaignMessageJob($message, $interactionId))
        ->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));
}

test('132001 fails one message, pauses the campaign, persists metadata, and is terminal', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $interactionId = '10000000-0000-4000-8000-000000000001';
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaCampaignConfigurationException(
        message: 'Template rejected for 5511999990001',
        code: 132001,
        httpStatus: 400,
        errorSubcode: 2494010,
        errorType: 'OAuthException',
        fbtraceId: 'TRACE-132001',
    ));

    runTaxonomyJob($message, $provider, $interactionId);
    runTaxonomyJob($message, $provider, $interactionId);

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('failed')
        ->and($fresh->error_code)->toBe('132001')
        ->and($fresh->error_subcode)->toBe('2494010')
        ->and($fresh->provider_http_status)->toBe(400)
        ->and($fresh->provider_error_type)->toBe('OAuthException')
        ->and($fresh->provider_error_trace_id)->toBe('TRACE-132001')
        ->and($fresh->error_message)->not->toContain('5511999990001')
        ->and($fresh->campaign->fresh()->status)->toBe('paused')
        ->and($fresh->campaign->fresh()->pause_reason_code)->toBe('META_132001');

    expect(AgentInteractionEvent::where('interaction_id', $interactionId)->get()->toJson())
        ->not->toContain('5511999990001')
        ->not->toContain('destination')
        ->not->toContain('raw_phone');
});

test('recipient rejection fails only the message and never pauses the campaign', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaNoWhatsAppException(
        message: 'Recipient 5511999990001 cannot receive this message',
        code: 131026,
        httpStatus: 400,
        errorType: 'OAuthException',
        fbtraceId: 'TRACE-RECIPIENT',
    ));

    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000002');

    expect($message->fresh()->status)->toBe('failed')
        ->and($message->fresh()->error_code)->toBe('131026')
        ->and($message->fresh()->campaign->fresh()->status)->toBe('sending')
        ->and($message->fresh()->error_message)->not->toContain('5511999990001');
});

test('unknown explicit 4xx is a terminal confirmed rejection rather than retry or in doubt', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaApiException(
        message: 'Rejected destination 5511999990001',
        code: 131000,
        httpStatus: 400,
        errorType: 'OAuthException',
        fbtraceId: 'TRACE-4XX',
    ));

    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000003');

    expect($message->fresh()->status)->toBe('failed')
        ->and($message->fresh()->error_code)->toBe('131000')
        ->and($message->fresh()->provider_attempted_at)->not->toBeNull()
        ->and($message->fresh()->campaign->fresh()->status)->toBe('sending');
});

test('rate rejection clears the provider claim and releases a bounded retry', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaRateLimitException(
        message: 'Rate limited for 5511999990001',
        code: 130429,
        httpStatus: 429,
        fbtraceId: 'TRACE-RATE',
    ));

    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000007');

    expect($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_attempted_at)->toBeNull()
        ->and($message->fresh()->provider_retry_not_before)->not->toBeNull()
        ->and($message->fresh()->error_code)->toBe('130429')
        ->and($message->fresh()->error_message)->not->toContain('5511999990001');
});

test('definite connection failure clears the claim for a sanitized framework retry', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $secret = 'raw-secret-token';
    $phone = '5511999990001';
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new ConnectionException(
        "cURL error 7: https://graph.facebook.com/messages?access_token={$secret} phone={$phone}",
    ));

    try {
        runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000008');
        $this->fail('A retryable exception should have been thrown.');
    } catch (MetaRetryableException $exception) {
        expect($exception->getMessage())
            ->not->toContain($secret)
            ->not->toContain($phone)
            ->not->toContain('https://')
            ->and($exception->getPrevious())->toBeNull();
    }

    expect($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_attempted_at)->toBeNull()
        ->and($message->fresh()->provider_retry_not_before)->not->toBeNull();
});

test('an unknown throwable is replaced before Horizon can retain raw URL token or phone PII', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $secret = 'unknown-raw-secret';
    $phone = '5511999990001';
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new RuntimeException(
        "Failure at https://internal.example/send?token={$secret} for {$phone}",
    ));

    try {
        runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000027');
        $this->fail('A sanitized retryable exception should have been thrown.');
    } catch (MetaRetryableException $exception) {
        expect($exception->getMessage())
            ->not->toContain($secret)
            ->not->toContain($phone)
            ->not->toContain('https://')
            ->and($exception->getPrevious())->toBeNull();
    }

    expect($message->fresh()->status)->toBe('queued')
        ->and($message->fresh()->provider_attempt_token)->not->toBeNull();
});

test('a duplicate worker inside the provider-attempt lease does not mutate or resend the winner claim', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('owner-token', now()->addMinute()))->toBeTrue();

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000009');

    expect($message->fresh()->status)->toBe('queued')
        ->and($message->fresh()->error_code)->toBeNull()
        ->and($message->fresh()->provider_attempt_token)->toBe('owner-token');

    expect($message->fresh()->markSentIfOwned('owner-token', 'wamid.owner'))->toBeTrue()
        ->and($message->fresh()->status)->toBe('sent');
});

test('an expired abandoned provider lease becomes conservatively in doubt and rejects a late winner', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('abandoned-token', now()->subSecond()))->toBeTrue();

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000010');

    expect($message->fresh()->status)->toBe('in_doubt')
        ->and($message->fresh()->error_code)->toBe('IN_DOUBT')
        ->and($message->fresh()->markSentIfOwned('abandoned-token', 'wamid.too-late'))->toBeFalse()
        ->and($message->fresh()->status)->toBe('in_doubt');
});

test('an active provider lease is deferred even after the campaign stops sending', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('paused-owner', now()->addMinute()))->toBeTrue();
    $message->campaign->update(['status' => 'paused']);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');
    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldNotReceive('makeProvider');
    $fakeJob = new FakeJob;
    $job = (new SendCampaignMessageJob($message, '10000000-0000-4000-8000-000000000028'))->setJob($fakeJob);
    $job->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('queued')
        ->and($message->fresh()->provider_attempt_token)->toBe('paused-owner')
        ->and($message->fresh()->error_code)->toBeNull()
        ->and($fakeJob->isReleased())->toBeTrue()
        ->and($fakeJob->releaseDelay)->toBeGreaterThan(0);
});

test('an expired provider lease becomes in doubt after the campaign stops sending', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('paused-expired-owner', now()->subSecond()))->toBeTrue();
    $message->campaign->update(['status' => 'cancelled']);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000029');

    expect($message->fresh()->status)->toBe('in_doubt')
        ->and($message->fresh()->error_code)->toBe('IN_DOUBT')
        ->and($message->fresh()->provider_attempt_token)->toBeNull();
});

test('a paused campaign still parks an unattempted queued message as pending', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    $message->campaign->update(['status' => 'paused']);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000030');

    expect($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_attempted_at)->toBeNull()
        ->and($message->fresh()->provider_attempt_token)->toBeNull();
});

test('an in-flight duplicate cannot steal the owner outcome', function (string $outcome): void {
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andReturnUsing(function () use ($message, $outcome): string {
        $duplicateProvider = Mockery::mock(WhatsAppProviderInterface::class);
        $duplicateProvider->shouldNotReceive('sendTemplate');
        runTaxonomyJob($message, $duplicateProvider, '10000000-0000-4000-8000-000000000012');

        return match ($outcome) {
            'success' => 'wamid.interleaved',
            'rate' => throw new MetaRateLimitException('Rate limited', 130429, httpStatus: 429),
            'dns' => throw new ConnectionException('cURL error 7: Failed to connect'),
            '132001' => throw new MetaCampaignConfigurationException('Template rejected', 132001, httpStatus: 400),
        };
    });

    if ($outcome === 'dns') {
        expect(fn () => runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000013'))
            ->toThrow(MetaRetryableException::class);
    } else {
        runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000013');
    }

    $fresh = $message->fresh();
    expect($fresh->status)->toBe(match ($outcome) {
        'success' => 'sent',
        'rate', 'dns' => 'pending',
        '132001' => 'failed',
    })->and($fresh->error_code)->not->toBe('IN_DOUBT');

    if ($outcome === '132001') {
        expect($fresh->campaign->fresh()->status)->toBe('paused');
    }
})->with(['success', 'rate', 'dns', '132001']);

test('rate-limit retry metadata and claim release are one atomic idempotent CAS transition', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('rate-token', now()->addMinute()))->toBeTrue();

    $exception = new MetaRateLimitException(
        message: 'Rate limit',
        code: 130429,
        httpStatus: 429,
        errorSubcode: 99,
        errorType: 'OAuthException',
        fbtraceId: 'TRACE-RATE-CAS',
    );
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updates[] = $query->sql;
        }
    });

    $retryNotBefore = now()->addMinute();
    expect($message->releaseProviderAttemptForRetry('rate-token', $retryNotBefore, $exception))->toBeTrue();
    $updatesAfterWinner = count($updates);
    expect($message->fresh()->releaseProviderAttemptForRetry('rate-token', $retryNotBefore, $exception))->toBeFalse();

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('pending')
        ->and($fresh->provider_attempted_at)->toBeNull()
        ->and($fresh->provider_attempt_token)->toBeNull()
        ->and($fresh->provider_attempt_lease_expires_at)->toBeNull()
        ->and($fresh->provider_retry_not_before?->getTimestamp())->toBe($retryNotBefore->getTimestamp())
        ->and($fresh->error_code)->toBe('130429')
        ->and($fresh->provider_error_code)->toBe('130429')
        ->and($fresh->provider_http_status)->toBe(429)
        ->and($updatesAfterWinner)->toBe(1)
        ->and(collect($updates)->filter(fn (string $sql): bool => str_contains($sql, 'campaign_messages')))->toHaveCount(2);
});

test('a rolled-back rate transition exposes no partial state', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('rollback-token', now()->addMinute()))->toBeTrue();
    $exception = new MetaRateLimitException('Rate limit', 130429, httpStatus: 429);

    DB::beginTransaction();
    try {
        expect($message->releaseProviderAttemptForRetry('rollback-token', now()->addMinute(), $exception))->toBeTrue();
        throw new RuntimeException('Simulated worker transaction fault.');
    } catch (RuntimeException) {
        DB::rollBack();
    }

    $fresh = $message->fresh();
    expect($fresh->status)->toBe('queued')
        ->and($fresh->provider_attempted_at)->not->toBeNull()
        ->and($fresh->provider_attempt_token)->toBe('rollback-token')
        ->and($fresh->provider_retry_not_before)->toBeNull()
        ->and($fresh->error_code)->toBeNull()
        ->and($fresh->provider_error_code)->toBeNull();
});

test('a duplicate deferred by the active lease cannot post before the durable rate-limit deadline', function (): void {
    $this->travelTo(now()->startOfSecond());
    config(['credflow.campaigns.rate_limit_release_seconds' => 60]);
    $message = makeTaxonomyCampaignMessage();
    $owner = Mockery::mock(WhatsAppProviderInterface::class);
    $owner->shouldReceive('sendTemplate')->once()->andReturnUsing(function () use ($message): never {
        $duplicate = Mockery::mock(WhatsAppProviderInterface::class);
        $duplicate->shouldNotReceive('sendTemplate');
        runTaxonomyJob($message, $duplicate, '10000000-0000-4000-8000-000000000020');

        throw new MetaRateLimitException('Rate limited', 130429, httpStatus: 429);
    });

    runTaxonomyJob($message, $owner, '10000000-0000-4000-8000-000000000021');
    $retryNotBefore = $message->fresh()->provider_retry_not_before;
    expect($retryNotBefore)->not->toBeNull();

    $this->travelTo($retryNotBefore->copy()->subSecond());
    $earlyDuplicate = Mockery::mock(WhatsAppProviderInterface::class);
    $earlyDuplicate->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message->fresh(), $earlyDuplicate, '10000000-0000-4000-8000-000000000022');
    expect($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_attempted_at)->toBeNull();

    $this->travelTo($retryNotBefore);
    $retry = Mockery::mock(WhatsAppProviderInterface::class);
    $retry->shouldReceive('sendTemplate')->once()->andReturn('wamid.after-rate-backoff');
    runTaxonomyJob($message->fresh(), $retry, '10000000-0000-4000-8000-000000000023');
    expect($message->fresh()->status)->toBe('sent');
});

test('DNS retry deadline is durable and blocks every duplicate until the same deadline', function (): void {
    $this->travelTo(now()->startOfSecond());
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new ConnectionException('Could not resolve host'));

    expect(fn () => runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000024'))
        ->toThrow(MetaRetryableException::class);
    $retryNotBefore = $message->fresh()->provider_retry_not_before;
    expect($retryNotBefore)->not->toBeNull();

    $this->travelTo($retryNotBefore->copy()->subSecond());
    $duplicate = Mockery::mock(WhatsAppProviderInterface::class);
    $duplicate->shouldNotReceive('sendTemplate');
    runTaxonomyJob($message->fresh(), $duplicate, '10000000-0000-4000-8000-000000000025');
    expect($message->fresh()->provider_retry_not_before?->equalTo($retryNotBefore))->toBeTrue();

    $this->travelTo($retryNotBefore);
    $retry = Mockery::mock(WhatsAppProviderInterface::class);
    $retry->shouldReceive('sendTemplate')->once()->andReturn('wamid.after-dns-backoff');
    runTaxonomyJob($message->fresh(), $retry, '10000000-0000-4000-8000-000000000026');
    expect($message->fresh()->status)->toBe('sent');
});

test('MaxAttemptsExceeded with an active lease schedules an expiry probe and eventually becomes in doubt', function (): void {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    $leaseExpiresAt = now()->addSeconds(30);
    expect($message->claimProviderAttempt('active-owner', $leaseExpiresAt))->toBeTrue();

    (new SendCampaignMessageJob($message))->failed(new MaxAttemptsExceededException('expired'));

    expect($message->fresh()->status)->toBe('queued');
    Queue::assertPushed(SendCampaignMessageJob::class, function (SendCampaignMessageJob $job) use ($message, $leaseExpiresAt): bool {
        return $job->campaignMessage->is($message)
            && $job->delay instanceof DateTimeInterface
            && $job->delay->getTimestamp() === $leaseExpiresAt->copy()->addSecond()->getTimestamp();
    });

    /** @var SendCampaignMessageJob $probe */
    $probe = Queue::pushed(SendCampaignMessageJob::class)->last();
    $this->travelTo($leaseExpiresAt->copy()->addSecond());
    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldNotReceive('makeProvider');
    $probe->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('in_doubt')
        ->and($message->fresh()->error_code)->toBe('IN_DOUBT');
});

test('active lease fails closed to in doubt when the expiry probe cannot be dispatched', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $message->update(['status' => 'queued']);
    expect($message->claimProviderAttempt('active-owner', now()->addMinute()))->toBeTrue();
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unavailable'));
    app()->instance(Dispatcher::class, $dispatcher);

    (new SendCampaignMessageJob($message))->failed(new MaxAttemptsExceededException('expired'));

    expect($message->fresh()->status)->toBe('in_doubt')
        ->and($message->fresh()->error_code)->toBe('IN_DOUBT')
        ->and($message->fresh()->provider_attempt_token)->toBeNull();
});

test('sanitized provider metadata is persisted without phone or log-injection sequences', function (): void {
    $phone = '5511999990001';
    $message = makeTaxonomyCampaignMessage($phone);
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaApiException(
        message: "Rejected {$phone}\r\nInjected",
        code: 131000,
        httpStatus: 400,
        errorType: "OAuth{$phone}\r\nInjected",
        fbtraceId: "TRACE-{$phone}\r\nInjected",
    ));

    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000014');

    $fresh = $message->fresh();
    $serialized = json_encode([
        $fresh->error_message,
        $fresh->provider_error_type,
        $fresh->provider_error_trace_id,
    ]);
    expect($serialized)->not->toContain($phone)
        ->not->toContain("\r")
        ->not->toContain("\n");
});

test('ambiguous 5xx retains provider code separately while error_code remains in doubt', function (): void {
    $message = makeTaxonomyCampaignMessage();
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andThrow(new MetaAmbiguousSendException(
        message: 'Upstream failure',
        code: 131000,
        httpStatus: 503,
        errorType: 'OAuthException',
        fbtraceId: 'TRACE-5XX-DB',
    ));

    runTaxonomyJob($message, $provider, '10000000-0000-4000-8000-000000000011');

    expect($message->fresh()->status)->toBe('in_doubt')
        ->and($message->fresh()->error_code)->toBe('IN_DOUBT')
        ->and($message->fresh()->provider_error_code)->toBe('131000')
        ->and($message->fresh()->provider_http_status)->toBe(503);
});

test('success, ambiguous, retry, and invalid-phone events never expose a full destination', function (): void {
    $customerPhone = '5511987654321';

    $success = makeTaxonomyCampaignMessage($customerPhone);
    $successProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $successProvider->shouldReceive('sendTemplate')->once()->andReturn('wamid.success');
    runTaxonomyJob($success, $successProvider, '10000000-0000-4000-8000-000000000004');

    $ambiguous = makeTaxonomyCampaignMessage($customerPhone);
    $ambiguousProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $ambiguousProvider->shouldReceive('sendTemplate')->once()->andThrow(new MetaAmbiguousSendException("Timeout sending to {$customerPhone}"));
    runTaxonomyJob($ambiguous, $ambiguousProvider, '10000000-0000-4000-8000-000000000005');

    $invalid = makeTaxonomyCampaignMessage('55119876543212345');
    $invalidProvider = Mockery::mock(WhatsAppProviderInterface::class);
    $invalidProvider->shouldNotReceive('sendTemplate');
    runTaxonomyJob($invalid, $invalidProvider, '10000000-0000-4000-8000-000000000006');

    $serializedEvents = AgentInteractionEvent::query()->get()->toJson();
    expect($serializedEvents)
        ->not->toContain($customerPhone)
        ->not->toContain('destination')
        ->not->toContain('raw_phone')
        ->and($ambiguous->fresh()->error_message)->not->toContain($customerPhone)
        ->and($invalid->fresh()->error_message)->not->toContain($customerPhone);
});
