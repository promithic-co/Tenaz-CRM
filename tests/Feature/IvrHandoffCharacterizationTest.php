<?php

use App\Jobs\SendInboundLeadWhatsAppJob;
use App\Jobs\SendPostCallWhatsAppJob;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignCall;
use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for canonical path 5 — the IVR/URA WhatsApp handoff
 * (Phase 61 / RUNT-01, success criterion SC3 in 61-VALIDATION.md).
 *
 * These assertions pin the CURRENT, UNDESIRABLE behaviour of UNMODIFIED production code as a
 * receipt for Phase 62. A FUTURE READER MUST NOT "FIX" THESE ASSERTIONS TO DESCRIBE DESIRED
 * BEHAVIOUR. When Phase 62 gives this path an ambiguity state and real evidence, this file is
 * EXPECTED to fail; it must then be rewritten deliberately as part of that change, never quietly
 * adjusted, which would erase the before/after this phase exists to create.
 *
 * The path is three independently-maintained jobs — SendPostCallWhatsAppJob,
 * SendInboundLeadWhatsAppJob and SendUraTemplateJob — that were written from one template and
 * have since DIVERGED. Per D-01 three copies are not "a shared mechanism characterized once", and
 * this file measures the divergence rather than asserting it:
 *
 *  - P5-F01 (evidence vacuum, partial): SendPostCallWhatsAppJob writes NO agent_interaction_events
 *    row at all. The other two DO. A single claim about "the IVR path's evidence" would be false.
 *  - P5-F02 (D-21/D-22 absent, and inverted between copies): SendPostCallWhatsAppJob releases its
 *    claim on ANY throwable (SendPostCallWhatsAppJob.php:107), so every failure — including a
 *    timeout after Meta accepted the template — is treated as safe-to-retry and re-sends.
 *    SendInboundLeadWhatsAppJob has no try/catch at all, so its claim survives its full 10-minute
 *    TTL and every retry inside that window silently drops the message instead. Neither
 *    distinguishes confirmed from uncertain; they fail in OPPOSITE directions.
 *  - P5-F03: no whatsapp_outbox_messages row and no ai_runs row on any of the three.
 *
 * Fixture-accuracy note: WhatsAppService is mocked, the idiom already established in
 * tests/Feature/SendPostCallWhatsAppJobTest.php, so the real jobs, the real lead lock, the real
 * template gate and the real cache claim all execute — only the provider call is replaced. No real
 * Twilio call completion is required and no production file was modified to make this possible.
 *
 * @see .engineering/runtime-characterization/path-5-ivr-ura-handoff.md
 */

/** Obviously-fake destinations: never real subscribers, per D-29. */
const IVR_CHARACTERIZATION_POST_CALL_PHONE = '+5511900000005';

const IVR_CHARACTERIZATION_URA_PHONE = '+5511900000015';

beforeEach(function (): void {
    // Suppress the downstream dashboard recompute the post-call job dispatches on success.
    Queue::fake();

    $this->whatsapp = $this->mock(WhatsAppService::class);
});

/**
 * A voice instance wired to a Meta instance and an APPROVED post-call template.
 *
 * Named with a path-5 prefix because Pest shares one global function namespace across every test
 * file — a redeclare against SendPostCallWhatsAppJobTest.php's makeCallWithMetaTemplate() would be
 * fatal. tenant_id is a string on every row, because BelongsToTenant's global scope is inert in
 * queue context.
 *
 * @return array{VoiceInstance, WhatsappTemplate}
 */
function ivrCharacterizationVoiceInstance(): array
{
    $user = userWithTenant();
    $tenantId = (string) $user->tenants()->first()->id;

    $whatsappInstance = WhatsappInstance::factory()->metaCloud()->create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
    ]);

    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        'kind' => 'meta_hsm',
        'status' => 'APPROVED',
        'meta_template_name' => 'char_path5_template',
        'language' => 'pt_BR',
    ]);

    $voiceInstance = VoiceInstance::factory()->create([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $whatsappInstance->id,
        // Deliberately set: this operator-configured text is loaded by both jobs and then never
        // used, because a template is sent instead. Pinned by P5-F06.
        'post_call_message' => 'Texto configurado pelo operador.',
        'post_call_meta_template_id' => $template->id,
    ]);

    return [$voiceInstance, $template];
}

/**
 * A completed IVR call ready to hand off, on the representative post-call job.
 *
 * @return array{VoiceCampaignCall, VoiceInstance}
 */
function ivrCharacterizationPostCall(): array
{
    [$voiceInstance] = ivrCharacterizationVoiceInstance();

    $campaign = VoiceCampaign::factory()->create([
        'tenant_id' => $voiceInstance->tenant_id,
        'voice_instance_id' => $voiceInstance->id,
        'post_call_message' => null,
    ]);

    $call = VoiceCampaignCall::factory()->create([
        'voice_campaign_id' => $campaign->id,
        'phone' => IVR_CHARACTERIZATION_POST_CALL_PHONE,
        'status' => 'interested',
    ]);

    return [$call, $voiceInstance];
}

// ---------------------------------------------------------------------------
// 1. The happy-path contract — lead created or reused, one direct provider call
// ---------------------------------------------------------------------------

test('characterization: a post-call handoff creates the lead and calls WhatsAppService exactly once', function () {
    $this->whatsapp->shouldReceive('sendTemplateViaInstance')->once()->andReturn('wamid.char.path5.001');

    [$call, $voiceInstance] = ivrCharacterizationPostCall();

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsapp);

    $lead = Lead::query()->where('whatsapp', ltrim(IVR_CHARACTERIZATION_POST_CALL_PHONE, '+'))->first();

    expect($lead)->not->toBeNull()
        ->and($lead->modo)->toBe('receptivo')
        ->and((string) $lead->tenant_id)->toBe((string) $voiceInstance->tenant_id);

    // The claim survives a successful send for its full 10-minute TTL — that is what makes a
    // retry inside the window a no-op after success.
    expect(Cache::has("postcall_send:{$call->id}"))->toBeTrue();

    // The ONLY durable first-party artefact of the send on this job, besides the log line:
    // a conversation timeline row written by TemplateTimelineRecorder. Note it carries no
    // interaction_id, because the post-call job never mints one.
    $timeline = ConversationTimelineMessage::query()->where('lead_id', $lead->id)->get();

    expect($timeline)->toHaveCount(1)
        ->and($timeline->first()->direction)->toBe('outbound')
        ->and($timeline->first()->interaction_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// 2. P5-F01 / P5-F03 — the evidence vacuum on the representative job
// ---------------------------------------------------------------------------

test('characterization: a post-call handoff writes no interaction event, no ai_runs row and no outbox row', function () {
    $this->whatsapp->shouldReceive('sendTemplateViaInstance')->once()->andReturn('wamid.char.path5.002');

    [$call] = ivrCharacterizationPostCall();

    (new SendPostCallWhatsAppJob($call->id))->handle($this->whatsapp);

    // The send definitely happened.
    expect(Lead::query()->where('whatsapp', ltrim(IVR_CHARACTERIZATION_POST_CALL_PHONE, '+'))->exists())->toBeTrue();

    // FINDING P5-F01. THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // SendPostCallWhatsAppJob contains no reference to AgentInteractionEventService anywhere —
    // not on the success path, not on either skip path, not in failed(). A customer-visible
    // message therefore leaves the platform with ZERO interaction-correlated evidence: a golden
    // trace for this job has to be assembled from the structured log lines
    // (ivr.whatsapp_sent, ivr.no_whatsapp_instance, ivr.meta_template_unavailable) plus the
    // voice_campaign_calls and leads rows. DO NOT "FIX" THIS ASSERTION by adding events; that
    // changes runtime behaviour and belongs to Phase 62.
    expect(AgentInteractionEvent::query()->doesntExist())->toBeTrue();

    // FINDING P5-F03 (ledger half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // No agent is constructed and AgentService::process() — the only caller of
    // AiRunRecorder::start() (app/Services/AgentService.php:112-117) — is never reached, so there
    // is no per-turn ledger row at all.
    expect(AiRun::query()->doesntExist())->toBeTrue();

    // FINDING P5-F03 (send-boundary half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // The job calls WhatsAppService::sendTemplateViaInstance() directly
    // (SendPostCallWhatsAppJob.php:95-100), bypassing WhatsappOutboxService entirely. There is no
    // durable row carrying status/provider_attempted_at/in_doubt for this send, so the
    // confirmed / proven-not-performed / uncertain distinction D-21 requires has nowhere to live.
    expect(WhatsappOutboxMessage::query()->doesntExist())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 3. P5-F02 — the retry-safety gap, and its two opposite shapes
// ---------------------------------------------------------------------------

test('characterization: a failed post-call send releases the claim, so the retry re-sends with no ambiguity state', function () {
    [$call] = ivrCharacterizationPostCall();
    $claimKey = "postcall_send:{$call->id}";

    // A timeout is indistinguishable, from inside this job, from a connection refused: both
    // surface as a Throwable. Meta may or may not have accepted the template.
    $this->whatsapp->shouldReceive('sendTemplateViaInstance')
        ->once()->ordered()->andThrow(new RuntimeException('meta timeout after the request left the client'));
    $this->whatsapp->shouldReceive('sendTemplateViaInstance')
        ->once()->ordered()->andReturn('wamid.char.path5.003');

    $job = new SendPostCallWhatsAppJob($call->id);

    expect(fn () => $job->handle($this->whatsapp))->toThrow(RuntimeException::class);

    // FINDING P5-F02. THIS IS THE DUPLICATE-SEND EXPOSURE, PINNED DELIBERATELY.
    // SendPostCallWhatsAppJob.php:101-110 forgets the claim key UNCONDITIONALLY before rethrowing,
    // so EVERY failure is treated as "proven not performed" regardless of what actually happened.
    // D-21's three-way distinction (confirmed / proven not performed / uncertain) is not
    // implemented on this path at all: there is no in_doubt state, no provider-attempt stamp and
    // no reconciliation. Contrast paths 1, 3 and 4, which each have one.
    expect(Cache::has($claimKey))->toBeFalse();

    // The retry re-POSTs the same template. The ->once()->ordered() pair above is the assertion:
    // the second send genuinely reached the provider. If Meta had accepted the first attempt, the
    // customer now has the same template twice, and nothing records that it happened.
    $job->handle($this->whatsapp);

    expect(Cache::has($claimKey))->toBeTrue();

    // Still no evidence of any of it.
    expect(AgentInteractionEvent::query()->doesntExist())->toBeTrue();
});

test('characterization: the URA inbound copy keeps its claim on failure and silently drops the message instead', function () {
    [$voiceInstance] = ivrCharacterizationVoiceInstance();
    $phone = ltrim(IVR_CHARACTERIZATION_URA_PHONE, '+');
    $claimKey = "ura_inbound_send:{$voiceInstance->id}:{$phone}";

    // Exactly one provider call is permitted for the whole test: the retry must NOT reach it.
    $this->whatsapp->shouldReceive('sendTemplateViaInstance')
        ->once()->andThrow(new RuntimeException('meta timeout after the request left the client'));

    $job = new SendInboundLeadWhatsAppJob($voiceInstance->id, IVR_CHARACTERIZATION_URA_PHONE);

    expect(fn () => $job->handle($this->whatsapp))->toThrow(RuntimeException::class);

    // FINDING P5-F02, INVERTED. THIS DIVERGENCE IS THE FINDING, NOT A TEST DEFECT.
    // SendInboundLeadWhatsAppJob has NO try/catch around its send (SendInboundLeadWhatsAppJob.php:125-130),
    // so the claim it took at :112-123 survives its full 10-minute TTL. Every retry inside that
    // window — and backoff() is [10, 30, 60], so all of them — short-circuits on the existing
    // claim and returns without sending. The failure mode is therefore the OPPOSITE of the
    // post-call job's: silent non-delivery rather than duplicate delivery. Two copies of one
    // pattern, two contradictory D-21 answers, neither of them the correct one.
    expect(Cache::has($claimKey))->toBeTrue();

    // The retry runs and sends nothing. The ->once() expectation above is the assertion.
    $job->handle($this->whatsapp);

    // FINDING P5-F01, THE OTHER HALF OF THE DIVERGENCE.
    // Unlike the post-call job, THIS copy does write interaction events — ura_inbound_received on
    // arrival and outbound_failed on the unavailable-template branch. So "the IVR path produces no
    // interaction evidence" is FALSE as a blanket statement about path 5, and 61-RESEARCH.md's
    // claim that a grep across all three files confirmed the absence is stale. The correct
    // statement is per-copy, which is exactly what D-01 predicts for three independent copies.
    $events = AgentInteractionEvent::query()->get();

    expect($events)->not->toBeEmpty();
    expect($events->pluck('event_type')->unique()->sort()->values()->all())->toBe(['ura_inbound_received']);
    expect($events->pluck('tenant_id')->unique()->values()->all())->toBe([(string) $voiceInstance->tenant_id]);
    expect($events->first()->tenant_id)->toBeString();

    // ...but this copy shares the other two absences: no ledger row and no outbox row, so it has
    // no ambiguity state either.
    expect(AiRun::query()->doesntExist())->toBeTrue();
    expect(WhatsappOutboxMessage::query()->doesntExist())->toBeTrue();
});
