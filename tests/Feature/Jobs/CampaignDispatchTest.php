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
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['credflow.campaigns.rate_per_minute' => 0]);
});

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
    $job->handle(app(CampaignService::class));

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
    $job->handle(app(CampaignService::class));

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
    $job->handle(app(CampaignService::class));

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

    $entry->update(['phone' => '5511999990002']);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->andThrow(new RuntimeException('Provider error'));

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);

    // Generic errors rethrow so the queue retries; the message must NOT be finalized
    // mid-flight (otherwise tries/backoff are dead config).
    expect(fn () => $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class)))
        ->toThrow(RuntimeException::class);

    expect($message->fresh()->status)->toBe('queued');

    // Only once retries are exhausted does the framework failed() callback finalize it.
    $job->failed(new RuntimeException('Provider error'));

    expect($message->fresh()->status)->toBe('failed');
    expect($message->fresh()->error_code)->toBe('JOB_FAILED');
    expect($campaign->fresh()->total_failed)->toBe(1);
});

test('DispatchCampaignJob suppresses opted-out entries before enqueueing', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();

    ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);
    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_out',
    ]);

    $job = new DispatchCampaignJob($campaign);
    $job->handle(app(CampaignService::class));

    // Only the 2 opted-in entries are enqueued; the opted-out one is skipped entirely.
    expect(CampaignMessage::where('campaign_id', $campaign->id)->count())->toBe(2);
    Queue::assertPushed(SendCampaignMessageJob::class, 2);
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
        'phone' => '5511999990003',
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

test('DispatchCampaignJob.failed auto-resumes the remainder of a still-sending campaign (SCALE-11)', function () {
    config(['credflow.campaigns.dispatch_max_redispatch' => 2]);
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();

    (new DispatchCampaignJob($campaign))->failed(new RuntimeException('worker crash'));

    Queue::assertPushed(DispatchCampaignJob::class, 1);
    expect((int) Cache::get("campaign_dispatch_resume:{$campaign->id}"))->toBe(1);
});

test('DispatchCampaignJob.failed stops auto-resume once the budget is exhausted (SCALE-11)', function () {
    config(['credflow.campaigns.dispatch_max_redispatch' => 2]);
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();
    Cache::put("campaign_dispatch_resume:{$campaign->id}", 2, now()->addHours(6));

    (new DispatchCampaignJob($campaign))->failed(new RuntimeException('worker crash'));

    Queue::assertNotPushed(DispatchCampaignJob::class);
});

test('DispatchCampaignJob.failed does not auto-resume when disabled or no longer sending (SCALE-11)', function () {
    config(['credflow.campaigns.dispatch_max_redispatch' => 0]);
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();
    (new DispatchCampaignJob($campaign))->failed(new RuntimeException('crash'));
    Queue::assertNotPushed(DispatchCampaignJob::class);

    // Enabled, but the campaign already finished — nothing to resume.
    config(['credflow.campaigns.dispatch_max_redispatch' => 2]);
    $done = Campaign::factory()->completed()->create();
    (new DispatchCampaignJob($done))->failed(new RuntimeException('crash'));
    Queue::assertNotPushed(DispatchCampaignJob::class);
});

test('a successful send writes no counter UPDATE to the campaigns row and derives total_sent (SCALE-1b)', function () {
    Queue::fake(); // suppress the downstream dashboard recompute job
    [$campaign, $message] = makeTenantSendable();

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.scale1b.001');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    DB::enableQueryLog();
    (new SendCampaignMessageJob($message))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    $counterWrites = collect(DB::getQueryLog())->filter(
        fn ($q): bool => str_contains(strtolower($q['query']), 'update "campaigns"') && str_contains($q['query'], 'total_')
    );
    DB::disableQueryLog();

    // The hot path no longer hammers the single campaigns row; the count derives from the message.
    expect($counterWrites)->toBeEmpty()
        ->and($message->fresh()->status)->toBe('sent')
        ->and($campaign->fresh()->total_sent)->toBe(1);
});

test('withCounters() hydrates derived counters in one query without an N+1 (SCALE-1b)', function () {
    foreach (range(1, 3) as $i) {
        $c = Campaign::factory()->sending()->create();
        CampaignMessage::factory()->sent()->count(2)->create(['campaign_id' => $c->id]);
        CampaignMessage::factory()->failed()->create(['campaign_id' => $c->id]);
    }

    DB::enableQueryLog();
    $campaigns = Campaign::query()->withCounters()->get();
    // Touching the accessors must not trigger further queries — values came from withCounters().
    $campaigns->each(fn (Campaign $c) => $c->total_sent + $c->total_failed);
    $messageReads = collect(DB::getQueryLog())->filter(
        fn ($q): bool => str_contains($q['query'], 'campaign_messages')
    );
    DB::disableQueryLog();

    // A single list query carries the correlated subqueries — no per-campaign aggregate.
    expect($messageReads)->toHaveCount(1)
        ->and($campaigns->every(fn (Campaign $c): bool => $c->total_sent === 2 && $c->total_failed === 1))->toBeTrue();
});

/**
 * Build a fully sendable campaign message (sending campaign + instance + approved template + entry).
 *
 * @return array{0: Campaign, 1: CampaignMessage}
 */
function makeTenantSendable(string $phone = '5511999990123'): array
{
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
        'phone' => $phone,
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    return [$campaign, $message];
}

/**
 * Build a sending campaign (instance + approved template) with N pending messages
 * on distinct valid phones, for fan-out / query-count assertions.
 *
 * @return array{0: Campaign, 1: list<CampaignMessage>}
 */
function makeSendableCampaignWithMessages(int $count): array
{
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

    $messages = [];
    for ($i = 0; $i < $count; $i++) {
        $entry = ContactListEntry::factory()->create([
            'contact_list_id' => $campaign->contact_list_id,
            'opt_in_status' => 'opted_in',
            'phone' => '551199999'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        ]);
        $messages[] = CampaignMessage::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_list_entry_id' => $entry->id,
            'status' => 'pending',
        ]);
    }

    return [$campaign, $messages];
}

/**
 * Count logged SELECT queries against a given table.
 *
 * @param  list<array{query: string}>  $log
 */
function countTableSelects(array $log, string $table): int
{
    return collect($log)
        ->filter(fn (array $q): bool => str_starts_with(strtolower(trim($q['query'])), 'select')
            && str_contains($q['query'], $table))
        ->count();
}

/** Bind a provider factory whose provider must never be asked to send. */
function bindNonSendingProvider(): void
{
    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldNotReceive('sendTemplate');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);
}

test('SendCampaignMessageJob releases (not fails) an over-budget send for fairness (SCALE-2)', function () {
    config(['credflow.campaigns.tenant_rate_per_minute' => 5]);
    [$campaign, $message] = makeTenantSendable();

    // This tenant has already used its full minute budget; the next send must release, not POST.
    $bucket = (int) floor(now()->timestamp / 60);
    Cache::add("campaign_tenant_throttle:{$campaign->tenant_id}:{$bucket}", 5, 120);

    bindNonSendingProvider();

    $fakeJob = new FakeJob;
    $job = (new SendCampaignMessageJob($message))->setJob($fakeJob);
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    // Queue semantics: the job released itself back onto the queue with a delay — not deleted, not failed.
    expect($fakeJob->isReleased())->toBeTrue();
    expect($fakeJob->releaseDelay)->toBeGreaterThan(0);
    expect($fakeJob->hasFailed())->toBeFalse();
    expect($message->fresh()->status)->toBe('pending');
    expect($campaign->fresh()->total_sent)->toBe(0);
    expect($campaign->fresh()->total_failed)->toBe(0);
});

test('SendCampaignMessageJob does not fail a throttled message however many times it is released (SCALE-2)', function () {
    config(['credflow.campaigns.tenant_rate_per_minute' => 5]);
    [$campaign, $message] = makeTenantSendable();

    $bucket = (int) floor(now()->timestamp / 60);
    Cache::add("campaign_tenant_throttle:{$campaign->tenant_id}:{$bucket}", 5, 120);

    bindNonSendingProvider();

    // Simulate a message already released far more than $tries (=3) times by the fairness gate.
    $fakeJob = new FakeJob;
    $fakeJob->attempts = 99;
    $job = (new SendCampaignMessageJob($message))->setJob($fakeJob);
    $job->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    // The job releases again and never marks itself failed; the worker-level guarantee that those 99
    // attempts won't fail it is the time-based retry budget (retryUntil) + exception-only failure cap.
    expect($fakeJob->isReleased())->toBeTrue();
    expect($fakeJob->hasFailed())->toBeFalse();
    expect($message->fresh()->status)->toBe('pending');
    expect($campaign->fresh()->total_failed)->toBe(0);

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
    expect($job->maxExceptions)->toBe(3);
});

test('SendCampaignMessageJob retry window can be disabled, reverting to attempt-count tries (SCALE-2)', function () {
    config(['credflow.campaigns.send_retry_window_seconds' => 0]);

    $job = new SendCampaignMessageJob(new CampaignMessage);

    // Window disabled → retryUntil() is null so the worker falls back to the plain $tries cap.
    expect($job->retryUntil())->toBeNull();
    expect($job->tries)->toBe(3);
});

test('SendCampaignMessageJob sends normally when under the per-tenant budget (SCALE-2)', function () {
    config(['credflow.campaigns.tenant_rate_per_minute' => 100]);
    [, $message] = makeTenantSendable();

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.tenant.ok');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('sent');
});

test('SendCampaignMessageJob ignores the fairness gate when disabled by default (SCALE-2)', function () {
    // tenant_rate_per_minute defaults to 0 — no gate, old behaviour: the send proceeds.
    expect(config('credflow.campaigns.tenant_rate_per_minute'))->toBe(0);
    [, $message] = makeTenantSendable();

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->once()->andReturn('wamid.tenant.default');

    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    $job = new SendCampaignMessageJob($message);
    $job->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($message->fresh()->status)->toBe('sent');
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

test('SendCampaignMessageJob resolves the campaign instance + template once across the fan-out (SCALE-4)', function () {
    Cache::flush();
    // Suppress the async dashboard-metrics recompute so its snapshot aggregates don't pollute the log.
    Queue::fake();

    [, $messages] = makeSendableCampaignWithMessages(2);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->twice()->andReturn('wamid.scale4');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    DB::enableQueryLog();
    foreach ($messages as $message) {
        (new SendCampaignMessageJob($message))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    }
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // Immutable config resolved once for the whole fan-out, not re-read per message.
    expect(countTableSelects($log, 'whatsapp_instances'))->toBe(1);
    expect(countTableSelects($log, 'whatsapp_templates'))->toBe(1);

    foreach ($messages as $message) {
        expect($message->fresh()->status)->toBe('sent');
    }
});

test('SendCampaignMessageJob re-resolves config per message when the cache is disabled (SCALE-4)', function () {
    Cache::flush();
    Queue::fake();
    config(['credflow.campaigns.send_config_cache_seconds' => 0]);

    [, $messages] = makeSendableCampaignWithMessages(2);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->twice()->andReturn('wamid.scale4.off');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    DB::enableQueryLog();
    foreach ($messages as $message) {
        (new SendCampaignMessageJob($message))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    }
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // Kill-switch (TTL 0): each message re-resolves the instance + template from the DB.
    expect(countTableSelects($log, 'whatsapp_instances'))->toBe(2);
    expect(countTableSelects($log, 'whatsapp_templates'))->toBe(2);
});

test('SendCampaignMessageJob re-resolves the template the instant it leaves APPROVED mid-campaign (SCALE-4 bust)', function () {
    Cache::flush();
    Queue::fake();

    [$campaign, $messages] = makeSendableCampaignWithMessages(2);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->andReturn('wamid.tmpl.bust');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    // First send caches the APPROVED template under its entity-id key.
    (new SendCampaignMessageJob($messages[0]))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    expect($messages[0]->fresh()->status)->toBe('sent');

    // Meta sync / operator flips the template out of APPROVED. The saved observer busts the cache
    // so the next send sees the new status immediately, not a stale cached APPROVED row within a TTL.
    $campaign->whatsappTemplate->update(['status' => 'PAUSED']);

    (new SendCampaignMessageJob($messages[1]))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    expect($messages[1]->fresh()->status)->toBe('failed')
        ->and($messages[1]->fresh()->error_code)->toBe('TEMPLATE_NOT_APPROVED');
});

test('SendCampaignMessageJob re-reads the instance after it is updated mid-campaign (SCALE-4 bust)', function () {
    Cache::flush();
    Queue::fake();

    [$campaign, $messages] = makeSendableCampaignWithMessages(2);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->andReturn('wamid.inst.bust');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    // First send caches the instance under its entity-id key.
    (new SendCampaignMessageJob($messages[0]))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));

    // Any instance write (e.g. a rotated access token) busts the cache via the saved observer.
    $campaign->whatsappInstance->update(['display_name' => 'rotated']);

    DB::enableQueryLog();
    (new SendCampaignMessageJob($messages[1]))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // The bust forces the second send to re-read the instance from the DB rather than serve a stale row.
    expect(countTableSelects($log, 'whatsapp_instances'))->toBe(1)
        ->and($messages[1]->fresh()->status)->toBe('sent');
});

test('SendCampaignMessageJob reloads the campaign for progress only when the broadcast fires (SCALE-4)', function () {
    Cache::flush();
    Queue::fake();

    [, $messages] = makeSendableCampaignWithMessages(2);

    $providerMock = Mockery::mock(WhatsAppProviderInterface::class);
    $providerMock->shouldReceive('sendTemplate')->twice()->andReturn('wamid.progress');
    $factoryMock = Mockery::mock(WhatsAppProviderFactory::class);
    $factoryMock->shouldReceive('makeProvider')->andReturn($providerMock);
    app()->instance(WhatsAppProviderFactory::class, $factoryMock);

    DB::enableQueryLog();
    foreach ($messages as $message) {
        (new SendCampaignMessageJob($message))->handle(app(CampaignService::class), $factoryMock, app(BroadcastDebouncer::class));
    }
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // Each send does one live status-gate load (2 total); only the first send wins the 2s
    // progress debounce and reloads the campaign for the broadcast (+1) = 3. The debounced
    // second send no longer reloads the campaign just to throw the result away.
    expect(countTableSelects($log, '"campaigns"'))->toBe(3);
});

test('SendCampaignMessageJob anchors retryUntil to the scheduled send slot, not dispatch time (CAMP-01)', function () {
    $window = (int) config('credflow.campaigns.send_retry_window_seconds');
    $scheduledFor = now()->addHours(10);

    $job = new SendCampaignMessageJob(new CampaignMessage, null, $scheduledFor->copy());

    // A message slotted 10h out must get the full window past its slot — a deadline counted
    // from dispatch would be expired when the job first pops, failing it before it runs.
    expect($job->retryUntil()->getTimestamp())
        ->toBe($scheduledFor->copy()->addSeconds($window)->getTimestamp());

    // Without a slot the window still counts from now.
    $bare = new SendCampaignMessageJob(new CampaignMessage);
    expect($bare->retryUntil()->getTimestamp())
        ->toBeGreaterThanOrEqual(now()->addSeconds($window)->getTimestamp() - 1);
});

test('DispatchCampaignJob gives each staggered message a retry window anchored to its own slot (CAMP-01)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['delay_between_ms' => 60000]);
    ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    (new DispatchCampaignJob($campaign))->handle(app(CampaignService::class));

    $jobs = Queue::pushed(SendCampaignMessageJob::class);
    expect($jobs)->toHaveCount(3);

    $window = (int) config('credflow.campaigns.send_retry_window_seconds');

    foreach ($jobs as $job) {
        expect($job->scheduledFor)->not->toBeNull()
            ->and($job->delay->getTimestamp())->toBe($job->scheduledFor->getTimestamp())
            ->and($job->retryUntil()->getTimestamp())
            ->toBe(Carbon::instance($job->scheduledFor)->addSeconds($window)->getTimestamp());
    }

    // Slots staggered by delay_between_ms (60s): last slot ≥ ~120s after the first.
    $slots = $jobs->map(fn (SendCampaignMessageJob $j): int => $j->scheduledFor->getTimestamp())->sort()->values();
    expect($slots->last() - $slots->first())->toBeGreaterThanOrEqual(119);
});

test('SendCampaignMessageJob parks a paused campaign message back in pending instead of stranding it (CAMP-02)', function () {
    [$campaign, $message] = makeTenantSendable();
    $campaign->update(['status' => 'paused']);
    $message->update(['status' => 'queued']);

    bindNonSendingProvider();

    (new SendCampaignMessageJob($message))->handle(app(CampaignService::class), app(WhatsAppProviderFactory::class), app(BroadcastDebouncer::class));

    // 'pending' + no provider attempt = re-enqueueable by the resume dispatcher.
    expect($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_attempted_at)->toBeNull();
});

test('DispatchCampaignJob re-enqueues orphaned pending messages on resume (CAMP-02)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();
    $entries = ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Orphaned by a pause: its queue job was consumed, no live job holds it.
    $orphan = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
        'status' => 'pending',
    ]);
    // Already sent and already provider-attempted rows must NOT be re-enqueued.
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
    ]);
    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[2]->id,
        'status' => 'pending',
        'provider_attempted_at' => now(),
    ]);

    (new DispatchCampaignJob($campaign))->handle(app(CampaignService::class));

    Queue::assertPushed(SendCampaignMessageJob::class, 1);
    expect($orphan->fresh()->status)->toBe('queued');
});

test('CampaignMessage::claimProviderAttempt lets exactly one caller win (CAMP-02)', function () {
    [, $message] = makeTenantSendable();

    expect($message->claimProviderAttempt())->toBeTrue()
        ->and($message->fresh()->claimProviderAttempt())->toBeFalse()
        ->and($message->fresh()->provider_attempted_at)->not->toBeNull();
});

test('DispatchCampaignJob completes an exhausted campaign even with failures in the mix (CAMP-03)', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create(['total_recipients' => 2]);
    $entries = ContactListEntry::factory()->count(2)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // A resume run over a finished list: one sent, one failed. The old counter predicate
    // (total_sent >= total_recipients) left this campaign in `sending` forever.
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[0]->id,
    ]);
    CampaignMessage::factory()->failed()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entries[1]->id,
    ]);

    (new DispatchCampaignJob($campaign))->handle(app(CampaignService::class));

    Queue::assertNotPushed(SendCampaignMessageJob::class);
    expect($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});

test('SendCampaignMessageJob.failed parks an expired unattempted message of a paused campaign (CAMP-01/02)', function () {
    [$campaign, $message] = makeTenantSendable();
    $campaign->update(['status' => 'paused']);
    $message->update(['status' => 'queued']);

    // Worker fails a job whose retryUntil expired without ever running handle(); during a
    // long pause that is not a send failure — the row must return to the re-enqueueable pool.
    (new SendCampaignMessageJob($message))->failed(new MaxAttemptsExceededException('retry window expired'));

    expect($message->fresh()->status)->toBe('pending')
        ->and($campaign->fresh()->total_failed)->toBe(0);

    // The same expiry on a still-sending campaign is a genuine failure.
    $campaign->update(['status' => 'sending']);
    $message->fresh()->update(['status' => 'queued']);

    (new SendCampaignMessageJob($message->fresh()))->failed(new MaxAttemptsExceededException('retry window expired'));

    expect($message->fresh()->status)->toBe('failed')
        ->and($message->fresh()->error_code)->toBe('JOB_FAILED');
});
