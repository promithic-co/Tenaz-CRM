<?php

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Models\WhatsappTemplate;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for canonical path 4 — campaign dispatch and the reply bridge
 * (Phase 61 / RUNT-01, success criterion SC3 in 61-VALIDATION.md).
 *
 * These assertions pin the CURRENT behaviour of UNMODIFIED production code as a receipt for
 * Phase 62. Some of what they pin is UNDESIRABLE. A FUTURE READER MUST NOT "FIX" THESE
 * ASSERTIONS TO DESCRIBE DESIRED BEHAVIOUR — when Phase 62 unifies the effect-safety
 * mechanisms this file is EXPECTED to fail, and it must then be rewritten deliberately as
 * part of that change, never quietly adjusted, which would erase the before/after this phase
 * exists to create.
 *
 * What is pinned here, and why each absence IS the finding:
 *
 *  - P4-F01: CampaignMessage carries its OWN provider-attempt lease and in_doubt state,
 *    structurally parallel to but sharing no code with WhatsappOutboxMessage. A campaign send
 *    therefore writes NO whatsapp_outbox_messages row at all, and campaign_messages has no
 *    tenant_id column — the two mechanisms are not one mechanism seen twice.
 *  - P4-F04: this path never calls AgentService or any model, so no ai_runs row and no
 *    conversational interaction event is ever produced. Phase 62 must not assume the
 *    conversational evidence shape generalises here.
 *  - P4-F02/P4-F03 (strengths Phase 62 must PRESERVE): the send-time opt-out re-check and the
 *    ownership-guarded lease. They are asserted positively so a regression is loud.
 *
 * Fixture-accuracy note: the provider is mocked at the WhatsAppProviderFactory seam, the idiom
 * already established in tests/Feature/Jobs/CampaignDispatchTest.php, so the real jobs, the real
 * campaign-state gates, the real throttles and the real CampaignMessage lease all execute — only
 * the Meta POST is replaced. No real template approval is required and no production file was
 * modified to make this test possible.
 *
 * @see .engineering/runtime-characterization/path-4-campaign-dispatch.md
 */

/** Obviously-fake destination: never a real subscriber, per D-29. */
const CAMPAIGN_CHARACTERIZATION_PHONE = '5511900000004';

beforeEach(function (): void {
    // The fan-out throttle is orthogonal to what this file characterizes.
    config(['credflow.campaigns.rate_per_minute' => 0]);

    // Meta identifiers the compatibility validator requires, mirrored from
    // tests/Feature/Jobs/CampaignDispatchTest.php so no real WABA is needed.
    WhatsappTemplate::creating(function (WhatsappTemplate $template): void {
        if ($template->whatsapp_instance_id === null) {
            return;
        }

        $instance = WhatsappInstance::withoutGlobalScopes()->find($template->whatsapp_instance_id);
        $template->meta_template_name ??= 'char_path4_'.fake()->uuid();
        $template->meta_waba_id ??= $instance?->meta_waba_id;
    });

    Campaign::created(function (Campaign $campaign): void {
        $instance = $campaign->whatsappInstance()->first();
        $template = $campaign->whatsappTemplate()->first();

        if (! $instance || ! $template) {
            return;
        }

        $template->update([
            'whatsapp_instance_id' => $instance->id,
            'meta_template_name' => $template->meta_template_name ?? 'char_path4_'.$template->id,
            'meta_waba_id' => $instance->meta_waba_id,
        ]);
    });
});

/**
 * A fully sendable campaign message: sending campaign + Meta instance + APPROVED template + entry.
 *
 * Named with a path-4 prefix because Pest shares one global function namespace across every test
 * file — a redeclare against tests/Feature/Jobs/CampaignDispatchTest.php's makeTenantSendable()
 * would be fatal.
 *
 * @return array{Campaign, CampaignMessage}
 */
function campaignCharacterizationSendable(string $phone = CAMPAIGN_CHARACTERIZATION_PHONE): array
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
        'name' => '[TESTE]',
    ]);

    $message = CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $entry->id,
        'status' => 'pending',
    ]);

    return [$campaign, $message];
}

/** Bind a provider that accepts exactly one template send and returns an opaque provider id. */
function campaignCharacterizationSendingProvider(string $providerMessageId): WhatsAppProviderFactory
{
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('sendTemplate')->once()->andReturn($providerMessageId);

    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldReceive('makeProvider')->andReturn($provider);
    app()->instance(WhatsAppProviderFactory::class, $factory);

    return $factory;
}

/** Bind a provider that must never be asked to send — the absence of the POST is the assertion. */
function campaignCharacterizationSilentProvider(): WhatsAppProviderFactory
{
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldNotReceive('sendTemplate');

    $factory = Mockery::mock(WhatsAppProviderFactory::class);
    $factory->shouldReceive('makeProvider')->andReturn($provider);
    app()->instance(WhatsAppProviderFactory::class, $factory);

    return $factory;
}

function runCampaignCharacterizationSend(CampaignMessage $message, WhatsAppProviderFactory $factory, ?FakeJob $fakeJob = null): void
{
    $job = new SendCampaignMessageJob($message);

    if ($fakeJob !== null) {
        $job->setJob($fakeJob);
    }

    $job->handle(app(CampaignService::class), $factory, app(BroadcastDebouncer::class));
}

// ---------------------------------------------------------------------------
// 1. The fan-out contract — one send job per eligible recipient
// ---------------------------------------------------------------------------

test('characterization: DispatchCampaignJob fans out exactly one SendCampaignMessageJob per eligible recipient', function () {
    Queue::fake();

    $campaign = Campaign::factory()->sending()->create();

    ContactListEntry::factory()->count(3)->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_in',
    ]);

    // Consent is enforced BEFORE any send job is enqueued (DispatchCampaignJob.php:162-184).
    // Recorded as a strength: Phase 62 must preserve the pre-suppression, not only the
    // send-time re-check.
    ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'opt_in_status' => 'opted_out',
    ]);

    (new DispatchCampaignJob($campaign))->handle(app(CampaignService::class));

    Queue::assertPushed(SendCampaignMessageJob::class, 3);
    expect(CampaignMessage::query()->where('campaign_id', $campaign->id)->count())->toBe(3);

    // Each send job carries its OWN interaction id, minted per recipient at
    // DispatchCampaignJob.php:206 — the fan-out is not one correlated turn but N of them.
    $interactionIds = Queue::pushed(SendCampaignMessageJob::class)
        ->map(fn (SendCampaignMessageJob $job): ?string => $job->interactionId)
        ->unique()
        ->values();

    expect($interactionIds)->toHaveCount(3);

    // The fan-out itself is the only part of this path with a shared correlation id, and it
    // writes campaign-scoped events with NO lead_id — so a campaign trail cannot be joined to a
    // conversation the way paths 1 and 3 can.
    $dispatchEvents = AgentInteractionEvent::query()
        ->whereIn('event_type', ['campaign_dispatch_started', 'campaign_dispatch_queued'])
        ->get();

    expect($dispatchEvents)->toHaveCount(2);
    expect($dispatchEvents->pluck('lead_id')->unique()->all())->toBe([null]);
    expect($dispatchEvents->pluck('tenant_id')->unique()->all())->toBe([(string) $campaign->tenant_id]);
});

// ---------------------------------------------------------------------------
// 2. P4-F01 — a completed send writes campaign_messages and NO outbox row
// ---------------------------------------------------------------------------

test('characterization: a completed campaign send writes a campaign_messages row and no whatsapp_outbox_messages row', function () {
    Queue::fake();

    [$campaign, $message] = campaignCharacterizationSendable();
    $factory = campaignCharacterizationSendingProvider('wamid.char.path4.001');

    runCampaignCharacterizationSend($message, $factory);

    $fresh = $message->fresh();

    expect($fresh->status)->toBe('sent')
        ->and($fresh->provider_message_id)->toBe('wamid.char.path4.001')
        ->and($fresh->sent_at)->not->toBeNull()
        // The lease is released on a confirmed outcome, but the attempt stamp survives as the
        // record that a POST was made.
        ->and($fresh->provider_attempt_token)->toBeNull()
        ->and($fresh->provider_attempt_lease_expires_at)->toBeNull()
        ->and($fresh->provider_attempted_at)->not->toBeNull();

    // FINDING P4-F01. THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // The campaign path never touches WhatsappOutboxService: CampaignMessage reimplements the
    // provider-attempt lease and the in_doubt state on its own model. The two mechanisms are
    // structurally parallel and share no code, so Phase 62 must characterize and migrate them
    // separately (D-01). DO NOT "FIX" THIS ASSERTION by routing campaigns through the outbox.
    expect(WhatsappOutboxMessage::query()->doesntExist())->toBeTrue();

    // The schema difference that makes the two mechanisms genuinely distinct rather than a
    // naming variation: campaign evidence carries no tenant column at all and is scoped only by
    // the globally-unique campaign_id FK (see the class comment on DispatchCampaignJob.php:18-20).
    expect(Schema::hasColumn('campaign_messages', 'tenant_id'))->toBeFalse();
    expect(Schema::hasColumn('whatsapp_outbox_messages', 'tenant_id'))->toBeTrue();

    // ...and the reverse: the lease columns exist only on campaign_messages.
    expect(Schema::hasColumn('campaign_messages', 'provider_attempt_token'))->toBeTrue();
    expect(Schema::hasColumn('campaign_messages', 'provider_attempt_lease_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('whatsapp_outbox_messages', 'provider_attempt_token'))->toBeFalse();
    expect(Schema::hasColumn('whatsapp_outbox_messages', 'provider_attempt_lease_expires_at'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3. The lease — an existing provider attempt is never re-POSTed
// ---------------------------------------------------------------------------

test('characterization: a message that already carries a provider attempt is never re-POSTed', function () {
    Queue::fake();

    // (a) After a confirmed send, a re-executed job returns on the status gate without a POST.
    [, $sent] = campaignCharacterizationSendable();
    runCampaignCharacterizationSend($sent, campaignCharacterizationSendingProvider('wamid.char.path4.002'));
    expect($sent->fresh()->status)->toBe('sent');

    // The ->once() expectation on the first factory is the assertion: a second POST would fail it.
    runCampaignCharacterizationSend($sent->fresh(), app(WhatsAppProviderFactory::class));
    expect($sent->fresh()->status)->toBe('sent')
        ->and($sent->fresh()->provider_message_id)->toBe('wamid.char.path4.002');

    // (b) An in-flight attempt whose lease is still ACTIVE defers instead of racing the owner.
    [, $leased] = campaignCharacterizationSendable('5511900000014');
    $leased->update([
        'status' => 'queued',
        'provider_attempted_at' => now()->subSeconds(5),
        'provider_attempt_token' => 'char-path4-owner',
        'provider_attempt_lease_expires_at' => now()->addSeconds(45),
    ]);

    $silent = campaignCharacterizationSilentProvider();
    $fakeJob = new FakeJob;
    runCampaignCharacterizationSend($leased, $silent, $fakeJob);

    expect($fakeJob->isReleased())->toBeTrue()
        ->and($fakeJob->hasFailed())->toBeFalse()
        ->and($leased->fresh()->status)->toBe('queued');

    // (c) An attempt whose lease EXPIRED without a confirmed outcome becomes terminal in_doubt.
    // This is D-21/D-22 satisfied on this path by its own implementation: uncertain is neither
    // re-sent nor counted as failed.
    [$campaign, $abandoned] = campaignCharacterizationSendable('5511900000024');
    $abandoned->update([
        'status' => 'queued',
        'provider_attempted_at' => now()->subMinutes(5),
        'provider_attempt_token' => null,
        'provider_attempt_lease_expires_at' => now()->subMinute(),
    ]);

    runCampaignCharacterizationSend($abandoned, campaignCharacterizationSilentProvider());

    expect($abandoned->fresh()->status)->toBe('in_doubt')
        ->and($abandoned->fresh()->error_code)->toBe('IN_DOUBT')
        // in_doubt is deliberately NOT a failure: an uncertain effect must not trip the
        // failure-rate machinery.
        ->and($campaign->fresh()->total_failed)->toBe(0);
});

test('characterization: the send-time opt-out re-check suppresses a recipient who opted out after dispatch (LGPD)', function () {
    Queue::fake();

    [$campaign, $message] = campaignCharacterizationSendable('5511900000034');

    // The opt-out lands while the staggered job waits for its send slot — hours or days after
    // the fan-out already cleared this recipient.
    $message->contactListEntry->update(['opt_in_status' => 'opted_out']);

    runCampaignCharacterizationSend($message, campaignCharacterizationSilentProvider());

    // A STRENGTH Phase 62 must preserve (SendCampaignMessageJob.php:188-211): consent is
    // re-evaluated immediately before the side effect, and 'skipped' is terminal but explicitly
    // not 'failed' so a mass opt-out cannot trip the failure-rate auto-pause.
    expect($message->fresh()->status)->toBe('skipped')
        ->and($message->fresh()->error_code)->toBe('OPTED_OUT')
        ->and($message->fresh()->provider_attempted_at)->toBeNull()
        ->and($campaign->fresh()->total_failed)->toBe(0);

    expect(AgentInteractionEvent::query()->where('event_type', 'outbound_skipped_optout')->exists())
        ->toBeTrue();
});

// ---------------------------------------------------------------------------
// 4. P4-F04 — no model call, therefore no ledger and no conversational evidence
// ---------------------------------------------------------------------------

test('characterization: the campaign path writes no ai_runs row and no conversational interaction event', function () {
    Queue::fake();

    [$campaign, $message] = campaignCharacterizationSendable('5511900000044');

    runCampaignCharacterizationSend($message, campaignCharacterizationSendingProvider('wamid.char.path4.003'));

    // The send definitely happened.
    expect($message->fresh()->status)->toBe('sent');

    // FINDING P4-F04 (ledger half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // Campaign dispatch is template-only sending of a pre-approved Meta template. It never calls
    // AgentService::process(), which is the only caller of AiRunRecorder::start()
    // (app/Services/AgentService.php:112-117), so there is no per-turn ledger row to hold cost,
    // latency, model name or outcome. Unlike path 3 (finding F19) this is not a bypass of an
    // expected runtime — there is no model turn to record. It is recorded so Phase 62 does not
    // assume the conversational evidence shape generalises to this path.
    expect(AiRun::query()->doesntExist())->toBeTrue();

    // The COMPLETE event vocabulary a successful campaign send produces. Asserted exhaustively
    // rather than by containment, because the absences are the point: no agent_started, no
    // model_called, no fact_check_passed/fact_check_failed, no agent_response_ready. The day one
    // of those can appear on this path, its contract has changed and this file must be rewritten.
    $events = AgentInteractionEvent::query()
        ->where('tenant_id', (string) $campaign->tenant_id)
        ->get();

    expect($events->pluck('event_type')->unique()->sort()->values()->all())
        ->toBe(['outbound_queued', 'outbound_sent']);

    // Tenant attribution survives as a string even though campaign_messages itself has no tenant
    // column — the interaction event is the only tenant-attributable evidence on this path.
    expect($events->pluck('tenant_id')->unique()->values()->all())
        ->toBe([(string) $campaign->tenant_id]);
    expect($events->first()->tenant_id)->toBeString();

    // No supersession concept exists anywhere in the codebase to record a stale campaign send.
    // Asserted as a tripwire, identically to paths 1 and 3.
    expect(AgentInteractionEvent::query()->where('event_type', 'execution_superseded')->doesntExist())
        ->toBeTrue();
    expect(AgentInteractionEvent::query()->where('event_type', 'response_blocked_stale')->doesntExist())
        ->toBeTrue();
});
