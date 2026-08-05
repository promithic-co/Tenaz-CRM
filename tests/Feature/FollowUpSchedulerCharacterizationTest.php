<?php

use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use App\Models\ConversationTimelineMessage;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use App\Services\PauseService;
use App\Services\WhatsappOutboxService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for canonical path 3 — the follow-up scheduler
 * (Phase 61 / RUNT-01, success criteria SC3 and SC4 in 61-VALIDATION.md).
 *
 * These assertions pin the CURRENT, UNDESIRABLE behaviour of UNMODIFIED production code as a
 * receipt for Phase 62.
 *
 * FINDING F19 is deliberately LEFT UNFIXED in this phase, per orchestrator decision OD-2:
 * ProcessLeadFollowUpJob resolves its agent with AgentFactory::makeFollowUp() and calls prompt()
 * directly (app/Jobs/ProcessLeadFollowUpJob.php:234-257), never AgentService::process(). Two
 * consequences are pinned here. The fact-check guardrail (AgentService::applyFactCheckGuardrail,
 * which is private and reachable only from process()) never runs on a follow-up message even
 * though CredFlowFollowUpAgent carries ConsultarCreditoInssTool and can surface financial figures.
 * And AiRunRecorder::start() is only ever called from AgentService::process(), so a follow-up turn
 * writes NO ai_runs row — making it invisible to Laboratory's aiRunSummary()/architectureComparison().
 * Routing follow-up through AgentService or adding the missing AiRun row would change runtime
 * behaviour, which a characterization phase must not do. That is Phase 62's work.
 *
 * A FUTURE READER MUST NOT "FIX" THESE ASSERTIONS TO DESCRIBE DESIRED BEHAVIOUR. When Phase 62
 * routes follow-up through the runtime, this file is EXPECTED to fail — starting with the
 * doesntExist() assertion against AiRun. It must then be rewritten deliberately as part of that
 * change, never silently adjusted, which would erase the before/after this phase exists to create.
 *
 * Fixture-accuracy note: the model call is stubbed at the GATEWAY seam with
 * CredFlowFollowUpAgent::fake(), the idiom already established in FollowUpEngineTest.php, so the
 * real job, the real eligibility gates, the real AgentFactory::makeFollowUp() resolution, the real
 * transaction and the real WhatsappOutboxService all execute — only the provider call is replaced.
 * No production file was modified to make this test possible, which is itself part of the receipt.
 *
 * @see .engineering/runtime-characterization/path-3-followup-scheduler.md
 */

/** Obviously-fake customer number: never a real subscriber, per D-29. */
const FOLLOWUP_CHARACTERIZATION_PHONE = '5500000000003';

const FOLLOWUP_CHARACTERIZATION_TENANT = 'char-path3-tenant';

/**
 * An eligible lead with a usable Meta instance, inside business hours.
 *
 * Named with a path-3 prefix because Pest shares one global function namespace across every test
 * file — a redeclare against FollowUpEngineTest.php would be fatal. tenant_id is a string on every
 * row, because BelongsToTenant's global scope is inert in queue/console context.
 *
 * @return array{WhatsappInstance, Lead}
 */
function followUpCharacterizationLead(): array
{
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => FOLLOWUP_CHARACTERIZATION_TENANT,
        'name' => 'char-path3-instance',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => FOLLOWUP_CHARACTERIZATION_TENANT,
        'whatsapp' => FOLLOWUP_CHARACTERIZATION_PHONE,
        'followup_status' => 'active',
        'followup_count' => 0,
        'last_interaction_at' => now()->subHours(2),
        'last_inbound_at' => now()->subHours(2),
        'whatsapp_instance_id' => $instance->id,
    ]);

    return [$instance, $lead];
}

/** Run the real job with its real injected dependencies resolved from the container. */
function runFollowUpCharacterizationJob(Lead $lead): void
{
    (new ProcessLeadFollowUpJob($lead))->handle(
        app(WhatsappOutboxService::class),
        app(FollowUpSettingsResolver::class),
        app(FollowUpWindowService::class),
        app(PauseService::class),
    );
}

beforeEach(function (): void {
    Queue::fake();
    // Freeze inside the São Paulo business-hours window the follow-up engine requires.
    $this->travelTo(Carbon::create(2026, 3, 20, 15, 0, 0, 'UTC'));
});

// ---------------------------------------------------------------------------
// 1. Happy-path evidence shape — the send boundary is intact on this path
// ---------------------------------------------------------------------------

test('characterization: a completed follow-up turn writes an outbox row and a followup_messages row', function () {
    CredFlowFollowUpAgent::fake(['Passando para saber se posso ajudar em algo.']);

    [, $lead] = followUpCharacterizationLead();

    runFollowUpCharacterizationJob($lead);

    $outbox = WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->get();
    $followups = FollowupMessage::query()->where('lead_id', $lead->id)->get();

    expect($outbox)->toHaveCount(1);
    expect($followups)->toHaveCount(1);

    // Delivery-side evidence is shared verbatim with path 1 and is intact here.
    expect($outbox->first()->status)->toBe('queued');
    expect($outbox->first()->interaction_id)->not->toBeNull();

    // followup_messages.status is stamped 'sent' at QUEUE time, not at provider confirmation —
    // the authoritative delivery state lives on whatsapp_outbox_messages.
    expect($followups->first()->status)->toBe('sent');
    expect($followups->first()->attempt)->toBe(1);

    // Tenant attribution survives as a string on both rows: the queue context has no auth scope,
    // so every write has to carry it explicitly.
    expect($outbox->first()->tenant_id)->toBe((string) $lead->tenant_id);
    expect($outbox->first()->tenant_id)->toBeString();
    expect($followups->first()->tenant_id)->toBe((string) $lead->tenant_id);
    expect($followups->first()->tenant_id)->toBeString();

    expect($lead->fresh()->followup_count)->toBe(1);
});

// ---------------------------------------------------------------------------
// 2. FINDING F19, ledger half — the same turn writes no ai_runs row at all
// ---------------------------------------------------------------------------

test('characterization: a follow-up turn produces no ai_runs row', function () {
    CredFlowFollowUpAgent::fake(['Passando para saber se posso ajudar em algo.']);

    [, $lead] = followUpCharacterizationLead();

    runFollowUpCharacterizationJob($lead);

    // The turn definitely happened: the outbox row proves the model returned and the message was
    // queued.
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->exists())->toBeTrue();

    // FINDING F19 (ledger half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // AiRunRecorder::start() is called from exactly one place in the codebase —
    // AgentService::process() at app/Services/AgentService.php:112-117 — and this path never calls
    // it. AuditLogMiddleware then asks AiRunRecorder::recordModelCall() to record the call, and it
    // silently returns because no row exists (app/Services/AiRunRecorder.php:40-42). Per-turn
    // duration, cost, token counts, model name and outcome are therefore unrecoverable for every
    // follow-up message in production.
    // DO NOT "FIX" THIS ASSERTION. When Phase 62 adds the AiRun row it is EXPECTED to fail, and
    // must be rewritten deliberately as part of that change.
    expect(AiRun::query()->where('tenant_id', (string) $lead->tenant_id)->doesntExist())->toBeTrue();

    // Nothing else wrote a run either — the absence is total, not tenant-scoped bookkeeping.
    expect(AiRun::query()->doesntExist())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 3. The split evidence picture — interaction events yes, ai_runs no
// ---------------------------------------------------------------------------

test('characterization: the follow-up turn still writes interaction events even though the ledger is empty', function () {
    CredFlowFollowUpAgent::fake(['Passando para saber se posso ajudar em algo.']);

    [, $lead] = followUpCharacterizationLead();

    runFollowUpCharacterizationJob($lead);

    $events = AgentInteractionEvent::query()->where('lead_id', $lead->id)->get();

    expect($events)->not->toBeEmpty();

    // The COMPLETE event vocabulary a successful follow-up turn produces. Asserted exhaustively
    // rather than by containment, because the absences are the point:
    //
    //  - followup_started  — emitted by the job itself (ProcessLeadFollowUpJob.php:189-199)
    //  - model_called      — emitted by AuditLogMiddleware (AuditLogMiddleware.php:57-70), proving
    //                        the middleware IS attached at the AGENT level
    //                        (CredFlowFollowUpAgent::middleware(), app/Ai/Agents/CredFlowFollowUpAgent.php:136-142)
    //                        and therefore fires without AgentService. Langfuse and LogAiUsageJob
    //                        ship from the same block, which is why follow-up turns are only
    //                        PARTLY invisible.
    //  - outbound_queued   — emitted per part and once as a summary (:314-325)
    //
    // NOT PRESENT, and that is FINDING F19's guardrail half: there is no fact_check_passed and no
    // fact_check_failed row, because AgentService::applyFactCheckGuardrail() is private and
    // reachable only from AgentService::process() (app/Services/AgentService.php:165, :322), which
    // this path never calls. A follow-up message carrying a financial figure produced by
    // ConsultarCreditoInssTool reaches the customer unverified.
    // DO NOT "FIX" THIS ASSERTION. When Phase 62 routes follow-up through the runtime, a
    // fact_check_* row will appear here and this test is EXPECTED to fail.
    expect($events->pluck('event_type')->unique()->sort()->values()->all())
        ->toBe(['followup_started', 'model_called', 'outbound_queued']);

    // The trail is written and the ledger is not. That asymmetry IS finding F19: a follow-up turn
    // is partly observable, which is exactly why the gap is easy to miss.
    expect($events->pluck('tenant_id')->unique()->values()->all())
        ->toBe([(string) $lead->tenant_id]);
    expect(AiRun::query()->doesntExist())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 4. Late-inbound collision — the eligibility gates are never re-evaluated
// ---------------------------------------------------------------------------

test('characterization: an inbound arriving after the gates pass does not stop the follow-up from being queued', function () {
    [, $lead] = followUpCharacterizationLead();

    $inboundArrived = false;

    // The closure runs INSIDE the model call, which is the real window this pins: the three
    // eligibility gates (app/Jobs/ProcessLeadFollowUpJob.php:74-90, :92-124, :126-153) have all
    // passed, and the message has not been queued yet.
    CredFlowFollowUpAgent::fake(function () use ($lead, &$inboundArrived): string {
        $inboundArrived = true;

        // The customer replies. This is precisely the state a latest-state check would consult:
        // last_inbound_at now points at "just now", well inside the recent-inbound skip threshold
        // that would have suppressed this follow-up had it been evaluated one moment later.
        $lead->forceFill(['last_inbound_at' => now()])->saveQuietly();

        ConversationTimelineMessage::create([
            'tenant_id' => (string) $lead->tenant_id,
            'lead_id' => $lead->id,
            'direction' => 'inbound',
            'sender_type' => 'lead',
            'channel' => 'whatsapp',
            'body' => 'Mensagem do cliente durante o turno',
            'status' => 'received',
            'source' => 'whatsapp',
        ]);

        return 'Passando para saber se posso ajudar em algo.';
    });

    runFollowUpCharacterizationJob($lead);

    expect($inboundArrived)->toBeTrue();

    // THE RECEIPT: the follow-up is queued anyway. Nothing between the gates and the transaction
    // at :271-296 re-reads followup_status, the window, or last_inbound_at, so the customer gets an
    // unsolicited "still there?" immediately after answering.
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->count())->toBe(1);
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->first()->status)->toBe('queued');
    expect(FollowupMessage::query()->where('lead_id', $lead->id)->where('status', 'sent')->exists())
        ->toBeTrue();

    // The newer inbound really was on record before the message was queued.
    expect($lead->fresh()->last_inbound_at->greaterThan(now()->subMinute()))->toBeTrue();
    expect(ConversationTimelineMessage::query()->where('lead_id', $lead->id)->where('direction', 'inbound')->exists())
        ->toBeTrue();

    // No supersession concept exists anywhere in the codebase to record what just happened.
    // Asserted as a tripwire: the day one of these rows can appear, this path's contract has
    // changed and this file must be rewritten.
    expect(AgentInteractionEvent::query()->where('event_type', 'execution_superseded')->doesntExist())
        ->toBeTrue();
    expect(AgentInteractionEvent::query()->where('event_type', 'response_blocked_stale')->doesntExist())
        ->toBeTrue();
});
