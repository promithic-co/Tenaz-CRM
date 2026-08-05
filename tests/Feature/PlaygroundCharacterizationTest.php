<?php

use App\Ai\Agents\BlindspotScannerAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\EvaluatorAgent;
use App\Ai\Agents\ScenarioGeneratorAgent;
use App\Models\AgentInteractionEvent;
use App\Models\AiRun;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappOutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;

uses(RefreshDatabase::class);

/**
 * Characterization oracle for Phase D PlaygroundController refactor.
 *
 * Locks the sandbox authorization contract (custom PT 403 message on destroy vs
 * bare 403 elsewhere), the legacy read/delete/append seams, and store. These
 * tests must stay green through D.1..D.4. The scanBlindspots / generateScenario
 * JSON-parse characterization lands in D.1 once the anonymous agents become
 * named classes that Ai::fakeAgent can target.
 *
 * Deviation noted in STATUS.md: getMessages historically returned
 * ['role','content','hora']; legacyMessages additively appends 'media' => null
 * for sandbox rows (which never carry attachments). Assertions check
 * role/content/hora VALUES exactly and do NOT assert array-exact-equality.
 *
 * PHASE 61 EXTENSION (RUNT-01, canonical path 6, success criterion SC3 in 61-VALIDATION.md).
 * Two concerns now share this file, deliberately: 61-VALIDATION.md's Wave 0 note requires the
 * existing {Subject}CharacterizationTest convention be EXTENDED rather than duplicated, and this
 * file already characterizes the same controller under the same name. The tests appended under
 * "Phase 61 — RUNT-01 path 6 evidence gap" below pin the CURRENT, UNDESIRABLE evidence shape of
 * the Playground path as a receipt for Phase 62: audit finding F16 (AgentFactory bypassed by
 * direct construction, still open) means a Playground turn is invisible to AiRunRecorder and to
 * AgentInteractionEventService. Those assertions must not be "fixed" to describe desired
 * behaviour — see the banner immediately above them. Nothing above this paragraph was changed
 * by Phase 61; the Phase D refactor contract stated above still holds.
 *
 * @see .engineering/runtime-characterization/path-6-playground.md
 */
/**
 * The playground now sits behind the `super_admin` gate, so the operator driving
 * these characterization tests carries that flag on top of a real tenant — the
 * controller still reads `$user->tenantId` to scope sandbox leads.
 */
function playgroundOperator(): User
{
    $user = userWithTenant();
    $user->forceFill(['is_super_admin' => true])->save();

    return $user->fresh();
}

function playgroundSandboxLead(User $user, array $overrides = []): Lead
{
    return Lead::factory()->sandbox()->create(array_merge([
        'tenant_id' => (string) $user->tenantId,
    ], $overrides));
}

function playgroundSandboxConversation(Lead $lead): string
{
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'sandbox',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lead->update(['conversation_id' => $conversationId]);

    return $conversationId;
}

function playgroundLegacyMessage(string $conversationId, string $role, string $content, string $time = '2026-01-01 09:30:00'): void
{
    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'a',
        'role' => $role,
        'content' => $content,
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => $time,
        'updated_at' => $time,
    ]);
}

// ─── Sandbox authorization ──────────────────────────────────────────────────
//
// Cross-tenant leads never reach the controller guard: the Lead global tenant
// scope filters them at route-model-binding, so the response is 404. The 403
// guard is reachable only for a SAME-tenant non-sandbox lead. destroy carries a
// custom PT message; the other guards (reset, etc.) are bare 403.

test('destroy denies a same-tenant non-sandbox lead with the custom PT message body', function () {
    $user = playgroundOperator();
    $lead = Lead::factory()->create(['tenant_id' => (string) $user->tenantId, 'is_sandbox' => false]);

    $response = $this->actingAs($user)->deleteJson(route('backoffice.playground.destroy', $lead));

    $response->assertForbidden()
        ->assertJsonPath('message', 'Apenas sessões sandbox do seu tenant podem ser deletadas aqui.');
});

test('destroy 404s a cross-tenant lead via the global tenant scope', function () {
    $user = playgroundOperator();
    $other = userWithTenant();
    $lead = playgroundSandboxLead($other);

    // A super-admin with no company selected deliberately sees every tenant, so
    // the scope only bites once the backoffice switcher points somewhere — which
    // is how an operator actually drives the playground.
    $this->actingAs($user)
        ->withSession(['active_tenant_id' => (string) $user->tenantId])
        ->deleteJson(route('backoffice.playground.destroy', $lead))
        ->assertNotFound();
});

test('reset denies a same-tenant non-sandbox lead with a bare 403 carrying NO custom message', function () {
    $user = playgroundOperator();
    $lead = Lead::factory()->create(['tenant_id' => (string) $user->tenantId, 'is_sandbox' => false]);

    $response = $this->actingAs($user)->postJson(route('backoffice.playground.reset', $lead));

    $response->assertForbidden();
    expect($response->json('message'))->not->toBe('Apenas sessões sandbox do seu tenant podem ser deletadas aqui.');
});

test('reset 404s a cross-tenant lead via the global tenant scope', function () {
    $user = playgroundOperator();
    $other = userWithTenant();
    $lead = playgroundSandboxLead($other);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => (string) $user->tenantId])
        ->postJson(route('backoffice.playground.reset', $lead))
        ->assertNotFound();
});

test('same-tenant sandbox lead is authorized for reset', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);

    $this->actingAs($user)->postJson(route('backoffice.playground.reset', $lead))->assertOk();
});

// ─── destroy / reset delete seams ───────────────────────────────────────────

test('destroy deletes the conversation and messages rows', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'oi');

    $this->actingAs($user)->deleteJson(route('backoffice.playground.destroy', $lead))->assertOk();

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0)
        ->and(Lead::find($lead->id))->toBeNull();
});

test('reset deletes conversation rows and resets lead fields', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user, [
        'status' => 'qualificado',
        'cpf' => '12345678900',
        'nome' => 'Cliente',
        'credito_json' => ['x' => 1],
        'documentos_coletados' => ['rg' => true],
    ]);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'oi');

    $this->actingAs($user)->postJson(route('backoffice.playground.reset', $lead))->assertOk();

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0);

    $fresh = Lead::find($lead->id);
    expect($fresh->conversation_id)->toBeNull()
        ->and($fresh->status)->toBe('novo')
        ->and($fresh->cpf)->toBeNull()
        ->and($fresh->nome)->toBe('[TESTE]')
        ->and($fresh->credito_json)->toBeNull()
        ->and($fresh->documentos_coletados)->toBeNull();
});

// ─── messages endpoint shape ─────────────────────────────────────────────────

test('messages endpoint returns the legacy message shape plus the system prompt', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user, ['sandbox_system_prompt' => 'Seja gentil.']);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'Olá', '2026-01-01 08:15:00');
    playgroundLegacyMessage($conversationId, 'assistant', 'Bom dia!', '2026-01-01 08:16:00');

    $response = $this->actingAs($user)->getJson(route('backoffice.playground.messages', $lead));

    $response->assertOk()
        ->assertJsonPath('sandbox_system_prompt', 'Seja gentil.')
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'Olá')
        ->assertJsonPath('messages.0.hora', '08:15')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Bom dia!')
        ->assertJsonPath('messages.1.hora', '08:16');
});

// ─── evaluate eval-append (Trap #3) ──────────────────────────────────────────
//
// D.4 routes the eval-append through ConversationTimelineService::appendLegacyMessage
// (fills every NOT NULL column), so appending to a conversation now succeeds.
// Pre-D.4 this 500'd on the malformed raw insert (locked as baseline in commit 1).

test('evaluate without a conversation returns the report and appends nothing', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);

    Ai::fakeAgent(EvaluatorAgent::class, ['Relatório sem conversa.']);

    $response = $this->actingAs($user)->postJson(route('backoffice.playground.evaluate', $lead), [
        'persona_prompt' => 'Cliente desconfiado',
    ]);

    $response->assertOk()->assertJsonPath('report', 'Relatório sem conversa.');
    expect(DB::table('agent_conversation_messages')->count())->toBe(0);
});

test('evaluate appends an assistant row with the AVALIAÇÃO content prefix', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'meu cpf é 123', '2026-01-01 10:00:00');

    Ai::fakeAgent(EvaluatorAgent::class, ['Relatório detalhado da rodada.']);

    $response = $this->actingAs($user)->postJson(route('backoffice.playground.evaluate', $lead), [
        'persona_prompt' => 'Cliente desconfiado',
    ]);

    $response->assertOk()->assertJsonPath('report', 'Relatório detalhado da rodada.');

    $appended = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->where('role', 'assistant')
        ->orderByDesc('created_at')
        ->first();

    expect($appended)->not->toBeNull()
        ->and($appended->role)->toBe('assistant')
        ->and(Str::startsWith($appended->content, '📝 **AVALIAÇÃO DA RODADA**'))->toBeTrue()
        ->and($appended->content)->toContain('Relatório detalhado da rodada.');
});

// ─── store ───────────────────────────────────────────────────────────────────

test('store creates a sandbox lead and returns the session shape', function () {
    $user = playgroundOperator();

    $response = $this->actingAs($user)->postJson(route('backoffice.playground.store'), [
        'label' => 'Minha sessão',
        'system_prompt' => 'Prompt custom',
    ]);

    $response->assertCreated()
        ->assertJsonPath('label', 'Minha sessão')
        ->assertJsonPath('status', 'novo')
        ->assertJsonStructure(['id', 'label', 'status', 'created_at']);

    $lead = Lead::find($response->json('id'));
    expect($lead)->not->toBeNull()
        ->and($lead->is_sandbox)->toBeTrue()
        ->and($lead->tenant_id)->toBe((string) $user->tenantId)
        ->and($lead->sandbox_label)->toBe('Minha sessão')
        ->and($lead->sandbox_system_prompt)->toBe('Prompt custom');
});

// ─── FormRequest validation (D.3) ───────────────────────────────────────────

test('chat validates the required message via the FormRequest', function () {
    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);

    $this->actingAs($user)->postJson(route('backoffice.playground.chat', $lead), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

test('generateScenario validates objective and cycle via the FormRequest', function () {
    $user = playgroundOperator();

    $this->actingAs($user)->postJson(route('backoffice.playground.generateScenario'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['objective', 'cycle']);
});

// ─── scanBlindspots JSON parse (added in D.1 once the agent is named) ─────────

test('scanBlindspots parses a plain JSON array response into attacks', function () {
    $user = playgroundOperator();
    playgroundSandboxLead($user);

    $json = json_encode([['category' => 'Prompt Injection', 'scenario' => 's', 'severity' => 'high', 'target' => 't']]);
    Ai::fakeAgent(BlindspotScannerAgent::class, [$json]);

    $this->actingAs($user)->postJson(route('backoffice.playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks.0.category', 'Prompt Injection')
        ->assertJsonPath('attacks.0.severity', 'high');
});

test('scanBlindspots strips a fenced json block before parsing', function () {
    $user = playgroundOperator();
    playgroundSandboxLead($user);

    $json = json_encode([['category' => 'Tool Abuse', 'scenario' => 's', 'severity' => 'low', 'target' => 't']]);
    Ai::fakeAgent(BlindspotScannerAgent::class, ["```json\n{$json}\n```"]);

    $this->actingAs($user)->postJson(route('backoffice.playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks.0.category', 'Tool Abuse');
});

test('scanBlindspots returns the parse-error payload for a non-array response', function () {
    $user = playgroundOperator();
    playgroundSandboxLead($user);

    Ai::fakeAgent(BlindspotScannerAgent::class, ['not json at all']);

    $this->actingAs($user)->postJson(route('backoffice.playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks', [])
        ->assertJsonPath('error', 'Falha no parse do JSON')
        ->assertJsonPath('raw', 'not json at all');
});

// ─── generateScenario ────────────────────────────────────────────────────────

test('generateScenario returns the agent text as scenario', function () {
    $user = playgroundOperator();

    Ai::fakeAgent(ScenarioGeneratorAgent::class, ['Persona agressiva que tenta burlar validações.']);

    $this->actingAs($user)->postJson(route('backoffice.playground.generateScenario'), [
        'objective' => 'Quebrar validação de CPF',
        'cycle' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('scenario', 'Persona agressiva que tenta burlar validações.');
});

// ─── index session cap ───────────────────────────────────────────────────────

test('index bounds the sandbox session list with a SQL limit (FE-05)', function () {
    $user = playgroundOperator();
    playgroundSandboxLead($user);
    playgroundSandboxLead($user);

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('backoffice.playground.index'))->assertOk();
    $sessionQuery = collect(DB::getQueryLog())
        ->first(fn ($q) => str_contains($q['query'], 'from "leads"') && str_contains($q['query'], 'is_sandbox'));
    DB::disableQueryLog();

    // The sidebar never hydrates the whole sandbox history — the query is capped at the source.
    expect($sessionQuery)->not->toBeNull()
        ->and($sessionQuery['query'])->toContain('limit 100');
});

// ─── Phase 61 — RUNT-01 path 6 evidence gap ──────────────────────────────────
//
// CHARACTERIZATION assertions for canonical path 6 (Phase 61 / RUNT-01, success criterion SC3).
// They pin the CURRENT, UNDESIRABLE behaviour of UNMODIFIED production code as a receipt for
// Phase 62.
//
// AUDIT FINDING F16 — "AgentFactory bypassed by 6+ direct `new`" — is STILL OPEN and is
// deliberately LEFT UNFIXED in this phase. RunPlaygroundChatAction::execute() constructs
// `new CredFlowAgent($lead, ...)` directly at app/Actions/RunPlaygroundChatAction.php:28 and
// never reaches AgentService::process(). Two consequences are pinned below. AiRunRecorder::start()
// is called from exactly one place in the codebase — app/Services/AgentService.php:112-117 — so a
// Playground turn writes NO ai_runs row. And AgentInteractionEventService is never invoked from
// this path at all, while AuditLogMiddleware's own event write is gated on an interaction id
// (app/Ai/Middleware/AuditLogMiddleware.php:48) that only AgentService and ProcessLeadFollowUpJob
// ever set — so NO agent_interaction_events row is written either.
//
// Routing Playground through AgentFactory/AgentService would change runtime behaviour, which a
// characterization phase must not do. That is Phase 62/63's work.
//
// A FUTURE READER MUST NOT "FIX" THESE ASSERTIONS TO DESCRIBE DESIRED BEHAVIOUR. When Phase 62
// closes F16 they are EXPECTED to fail, and must then be rewritten deliberately as part of that
// change, never silently adjusted, which would erase the before/after this phase exists to create.

test('characterization: a playground chat turn writes no ai_runs row and no agent_interaction_events row (F16)', function () {
    Queue::fake();

    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);

    // Stubbed at the narrowest available seam — the agent's own gateway — so the real controller,
    // the real FormRequest, the real RunPlaygroundChatAction and the real `new CredFlowAgent`
    // construction all execute. No production file was modified to make this possible, which is
    // itself part of the receipt: the gap is reachable without introducing a seam for it.
    Ai::fakeAgent(CredFlowAgent::class, ['Claro, posso te ajudar com isso.']);

    $response = $this->actingAs($user)->postJson(route('backoffice.playground.chat', $lead), [
        'message' => 'Bom dia, quero simular.',
    ]);

    // The turn definitely happened: the reply and the debug envelope came back.
    $response->assertOk()
        ->assertJsonPath('reply', 'Claro, posso te ajudar com isso.')
        ->assertJsonStructure(['reply', 'messages', 'debug' => ['tokens_in', 'tokens_out', 'duration', 'steps', 'tool_calls', 'model']]);

    // FINDING F16 (ledger half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // Per-turn duration, cost, token counts, model name and outcome are unrecoverable for every
    // Playground turn: the returned `debug` JSON is the ONLY first-party per-turn evidence, and it
    // is discarded the moment the operator closes the tab.
    expect(AiRun::query()->doesntExist())->toBeTrue();

    // FINDING F16 (trail half). THIS ABSENCE IS THE FINDING, NOT A TEST DEFECT.
    // No interaction id is ever minted on this path, so there is no correlation key and no event
    // trail — not even the `model_called` row that the follow-up path (F19) still gets, because
    // that row is gated on the interaction context being set.
    expect(AgentInteractionEvent::query()->doesntExist())->toBeTrue();
});

test('characterization: a playground chat turn creates no whatsapp_outbox_messages row (the sandbox boundary holds)', function () {
    Queue::fake();

    $user = playgroundOperator();
    $lead = playgroundSandboxLead($user);

    Ai::fakeAgent(CredFlowAgent::class, ['Claro, posso te ajudar com isso.']);

    $this->actingAs($user)->postJson(route('backoffice.playground.chat', $lead), [
        'message' => 'Bom dia, quero simular.',
    ])->assertOk();

    // THIS ABSENCE IS A STRENGTH, pinned so a regression is loud rather than silent.
    // RunPlaygroundChatAction returns the reply as JSON and never touches WhatsappOutboxService,
    // so no real send can escape the sandbox. Phase 62 must preserve this boundary when it wires
    // Playground into the runtime: gaining an AiRun row must not also gain an outbox row.
    expect(WhatsappOutboxMessage::query()->doesntExist())->toBeTrue();
    expect($lead->fresh()->is_sandbox)->toBeTrue();
});

test('characterization: a non-super-admin is rejected before reaching the playground chat endpoint', function () {
    // A perfectly ordinary tenant user — the difference from playgroundOperator() is exactly the
    // is_super_admin flag.
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);

    // The model must never be reached, so no fake is registered: a 200 here would fail loudly.
    $this->actingAs($user)->postJson(route('backoffice.playground.chat', $lead), [
        'message' => 'Bom dia, quero simular.',
    ])->assertForbidden();

    // The access-control boundary that makes this path acceptable at all (ASVS V4, threat
    // T-61-23): every Playground route sits behind auth + super_admin + backoffice.context
    // (routes/backoffice.php:22), and the five LLM-invoking ones additionally behind
    // throttle:30,1 (routes/backoffice.php:90-96). Asserted as a regression guard so the gate
    // cannot erode silently.
    expect(AiRun::query()->doesntExist())->toBeTrue();
    expect(AgentInteractionEvent::query()->doesntExist())->toBeTrue();
});
