<?php

use App\Ai\Agents\CredFlowAgent;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use App\Models\Lead;
use App\Models\WhatsappOutboxMessage;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * CHARACTERIZATION TEST for canonical path 2 — the direct agent API POST /api/tenaz and its
 * legacy POST /api/aria alias (Phase 61 / RUNT-01, success criteria SC3 and SC5 in
 * 61-VALIDATION.md).
 *
 * These assertions pin the CURRENT, UNDESIRABLE behaviour of UNMODIFIED production code as a
 * receipt for Phase 62. This path has NO concurrency guard of any kind — no queue, no debounce,
 * no WithoutOverlapping — so two calls for the same whatsapp + tenant_id both run and both return
 * an answer, and neither is ever marked stale or superseded. Because the CALLING SYSTEM, not
 * Tenaz, sends the WhatsApp message, there is no send boundary here for D-06/D-07/D-17 to gate:
 * D-18's future contract will have to signal staleness in the API RESPONSE itself. That is a
 * defect. This file documents it; it does not endorse it.
 *
 * A FUTURE READER MUST NOT "FIX" THESE ASSERTIONS TO DESCRIBE DESIRED BEHAVIOUR. When Phase 62
 * gives this path a supersession contract, this file is EXPECTED to fail — most likely where it
 * asserts that two sequential answers come back undifferentiated. It must then be rewritten
 * deliberately as part of that change, never silently adjusted, which would erase the
 * before/after this phase exists to create.
 *
 * Fixture-accuracy note: the collision is driven as two SEQUENTIAL HTTP calls, which deliberately
 * UNDER-states the real gap rather than approximating it — production has no lock at all, so
 * genuine concurrency is possible and strictly worse. The sequential form is enough to pin the
 * fact this phase needs (the earlier answer is never marked superseded) while staying
 * deterministic. AgentService is stubbed into the container following the established
 * tests/Feature/ApiAgentRenameTest.php shape, so the real route, middleware, controller, tenant
 * gate and persistence all run; only the model turn is replaced. One test instead stubs the
 * GATEWAY (CredFlowAgent::fake) and lets the real AgentService run, so the golden trace's
 * evidence-row claims are measured rather than asserted.
 *
 * @see .engineering/runtime-characterization/path-2-direct-api.md
 */

/** Obviously-fake integrator-supplied number: never a real subscriber, per D-29. */
const TENAZ_API_CHARACTERIZATION_PHONE = '5500000000002';

/** Bearer token and the tenant it is bound to. Test-only values, never a real key. */
const TENAZ_API_CHARACTERIZATION_TOKEN = 'char-path2-token';

const TENAZ_API_CHARACTERIZATION_TENANT = 'char-path2-tenant';

beforeEach(function (): void {
    config([
        'services.credflow.api_key' => null,
        'services.credflow.api_keys' => [
            TENAZ_API_CHARACTERIZATION_TOKEN => TENAZ_API_CHARACTERIZATION_TENANT,
        ],
    ]);
});

/**
 * Stub the model turn at the AgentService seam and return the answers it will hand back, in order.
 *
 * Named with a path-2 prefix because Pest shares one global function namespace across every test
 * file — a redeclare would be fatal.
 *
 * @param  list<string|null>  $answers
 */
function tenazApiCharacterizationStubAgent(array $answers): void
{
    $agentService = Mockery::mock(AgentService::class);

    foreach ($answers as $answer) {
        $agentService->shouldReceive('process')->once()->ordered()->andReturn($answer);
    }

    app()->instance(AgentService::class, $agentService);
}

/** POST one direct-API turn to the given route name with the characterization bearer token. */
function tenazApiCharacterizationCall(string $routeName, string $message): TestResponse
{
    return test()->postJson(route($routeName), [
        'whatsapp' => TENAZ_API_CHARACTERIZATION_PHONE,
        'message' => $message,
        'tenant_id' => TENAZ_API_CHARACTERIZATION_TENANT,
        'modo' => 'receptivo',
    ], [
        'Authorization' => 'Bearer '.TENAZ_API_CHARACTERIZATION_TOKEN,
    ]);
}

/** The lead this path creates on first contact, or null before the first call. */
function tenazApiCharacterizationLead(): ?Lead
{
    return Lead::query()
        ->where('tenant_id', TENAZ_API_CHARACTERIZATION_TENANT)
        ->where('whatsapp', TENAZ_API_CHARACTERIZATION_PHONE)
        ->first();
}

// ---------------------------------------------------------------------------
// 1. Entry contract — synchronous, in-request, tenant bound to the token
// ---------------------------------------------------------------------------

test('characterization: the tenaz endpoint answers synchronously and creates the lead under the token tenant', function () {
    tenazApiCharacterizationStubAgent(['Resposta sincrona do agente']);

    tenazApiCharacterizationCall('api.tenaz', 'Quero entender as condicoes')
        ->assertOk()
        ->assertExactJson(['response' => 'Resposta sincrona do agente']);

    $lead = tenazApiCharacterizationLead();

    expect($lead)->not->toBeNull();
    // Tenancy comes from the bearer token, never from the payload, and is stored as a string.
    expect($lead->tenant_id)->toBe(TENAZ_API_CHARACTERIZATION_TENANT);
    expect($lead->tenant_id)->toBeString();

    // The whole turn happened inside the request: nothing was queued, and the response body
    // carries ONLY the answer — no interaction id the caller could echo back, which is why a
    // supersession contract cannot be expressed in today's shape (P2-F04).
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->doesntExist())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 2. The legacy alias is still live — deprecation is advisory metadata only
// ---------------------------------------------------------------------------

test('characterization: the legacy aria alias returns the same body and only advises deprecation', function () {
    tenazApiCharacterizationStubAgent(['Resposta pelo alias legado']);

    tenazApiCharacterizationCall('api.aria', 'Oi pelo alias')
        ->assertOk()
        ->assertExactJson(['response' => 'Resposta pelo alias legado'])
        // RFC 8594 signals only. There is no sunset date, no 410, no throttling escalation:
        // the alias shares AgentController::handle() verbatim and remains fully functional.
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Link', '<'.route('api.tenaz').'>; rel="successor-version"');

    $lead = tenazApiCharacterizationLead();

    expect($lead)->not->toBeNull();
    // The alias writes the same rows as the canonical route, so nothing in the evidence tables
    // distinguishes which route a turn arrived on (P2-F06).
    expect($lead->tenant_id)->toBe(TENAZ_API_CHARACTERIZATION_TENANT);
});

// ---------------------------------------------------------------------------
// 3. The collision receipt — neither answer is ever marked stale
// ---------------------------------------------------------------------------

test('characterization: two calls for the same lead both return an answer and neither is marked superseded', function () {
    tenazApiCharacterizationStubAgent(['Resposta do turno A', 'Resposta do turno B']);

    $responseA = tenazApiCharacterizationCall('api.tenaz', 'Primeira mensagem');
    $responseB = tenazApiCharacterizationCall('api.tenaz', 'Segunda mensagem');

    // THE RECEIPT: both turns answered. Turn A was written with no knowledge of the second
    // message and is handed back to the integrator exactly like a current answer would be.
    $responseA->assertOk()->assertExactJson(['response' => 'Resposta do turno A']);
    $responseB->assertOk()->assertExactJson(['response' => 'Resposta do turno B']);

    // Nothing in either response distinguishes stale from current: no staleness flag, no
    // supersession marker, no interaction id, no timestamp. The two payloads are structurally
    // identical, which is precisely D-18's unimplemented case.
    expect(array_keys($responseA->json()))->toBe(['response']);
    expect(array_keys($responseB->json()))->toBe(array_keys($responseA->json()));

    // One lead, two independent turns. Nothing serialized them and nothing linked them.
    expect(Lead::query()->where('whatsapp', TENAZ_API_CHARACTERIZATION_PHONE)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 4. Negative evidence — no send boundary, no supersession concept
// ---------------------------------------------------------------------------

test('characterization: the direct api path writes no outbox row and no supersession evidence', function () {
    tenazApiCharacterizationStubAgent(['Resposta do turno A', 'Resposta do turno B']);

    tenazApiCharacterizationCall('api.tenaz', 'Primeira mensagem')->assertOk();
    tenazApiCharacterizationCall('api.tenaz', 'Segunda mensagem')->assertOk();

    $lead = tenazApiCharacterizationLead();

    // NO SEND BOUNDARY ON TENAZ'S SIDE. The calling system performs the send, so there is no
    // outbox row to gate, to cancel, or to correlate a delivery against (P2-F03). D-17 has
    // nothing here to attach to — which is why D-18's future contract must live in the API
    // response instead.
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->doesntExist())->toBeTrue();

    // Neither event type exists anywhere in the codebase. Asserted as a tripwire: the day one of
    // these rows can appear, this path's contract has changed and this file must be rewritten.
    expect(AgentInteractionEvent::query()->where('event_type', 'execution_superseded')->doesntExist())
        ->toBeTrue();
    expect(AgentInteractionEvent::query()->where('event_type', 'response_blocked_stale')->doesntExist())
        ->toBeTrue();
});

// ---------------------------------------------------------------------------
// 5. Golden-trace evidence shape, measured against the real AgentService
// ---------------------------------------------------------------------------

test('characterization: a real agent turn writes an ai_runs row and interaction events but still no outbox row', function () {
    // Stub the GATEWAY, not the service, so the real AgentService::process() runs: this measures
    // the dossier's § 3 claims instead of asserting them. Path 2 shares Path 1's model-side
    // evidence (ai_runs + agent_interaction_events) while having none of its delivery-side
    // evidence.
    CredFlowAgent::fake(['Resposta gerada pelo agente real']);

    tenazApiCharacterizationCall('api.tenaz', 'Quero saber mais')
        ->assertOk()
        ->assertJsonStructure(['response']);

    $lead = tenazApiCharacterizationLead();

    $run = AiRun::query()->where('tenant_id', TENAZ_API_CHARACTERIZATION_TENANT)->first();

    expect($run)->not->toBeNull();
    expect($run->lead_id)->toBe($lead->id);
    expect($run->tenant_id)->toBeString();
    expect($run->started_at)->not->toBeNull();

    // The interaction trail exists and is joined to the same id as the run — but that id is
    // minted inside AgentService and never leaves the application (P2-F04).
    expect(
        AgentInteractionEvent::query()
            ->where('interaction_id', $run->run_id)
            ->where('event_type', 'agent_started')
            ->exists(),
    )->toBeTrue();

    // Still no outbox row, even on a fully real turn. Absence here is structural, not incidental.
    expect(WhatsappOutboxMessage::query()->where('lead_id', $lead->id)->doesntExist())->toBeTrue();
});
