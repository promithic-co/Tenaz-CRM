<?php

use App\Ai\Agents\BlindspotScannerAgent;
use App\Ai\Agents\EvaluatorAgent;
use App\Ai\Agents\ScenarioGeneratorAgent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 */
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
    $user = userWithTenant();
    $lead = Lead::factory()->create(['tenant_id' => (string) $user->tenantId, 'is_sandbox' => false]);

    $response = $this->actingAs($user)->deleteJson(route('playground.destroy', $lead));

    $response->assertForbidden()
        ->assertJsonPath('message', 'Apenas sessões sandbox do seu tenant podem ser deletadas aqui.');
});

test('destroy 404s a cross-tenant lead via the global tenant scope', function () {
    $user = userWithTenant();
    $other = userWithTenant();
    $lead = playgroundSandboxLead($other);

    $this->actingAs($user)->deleteJson(route('playground.destroy', $lead))->assertNotFound();
});

test('reset denies a same-tenant non-sandbox lead with a bare 403 carrying NO custom message', function () {
    $user = userWithTenant();
    $lead = Lead::factory()->create(['tenant_id' => (string) $user->tenantId, 'is_sandbox' => false]);

    $response = $this->actingAs($user)->postJson(route('playground.reset', $lead));

    $response->assertForbidden();
    expect($response->json('message'))->not->toBe('Apenas sessões sandbox do seu tenant podem ser deletadas aqui.');
});

test('reset 404s a cross-tenant lead via the global tenant scope', function () {
    $user = userWithTenant();
    $other = userWithTenant();
    $lead = playgroundSandboxLead($other);

    $this->actingAs($user)->postJson(route('playground.reset', $lead))->assertNotFound();
});

test('same-tenant sandbox lead is authorized for reset', function () {
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);

    $this->actingAs($user)->postJson(route('playground.reset', $lead))->assertOk();
});

// ─── destroy / reset delete seams ───────────────────────────────────────────

test('destroy deletes the conversation and messages rows', function () {
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'oi');

    $this->actingAs($user)->deleteJson(route('playground.destroy', $lead))->assertOk();

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0)
        ->and(Lead::find($lead->id))->toBeNull();
});

test('reset deletes conversation rows and resets lead fields', function () {
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user, [
        'status' => 'qualificado',
        'cpf' => '12345678900',
        'nome' => 'Cliente',
        'credito_json' => ['x' => 1],
        'documentos_coletados' => ['rg' => true],
    ]);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'oi');

    $this->actingAs($user)->postJson(route('playground.reset', $lead))->assertOk();

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
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user, ['sandbox_system_prompt' => 'Seja gentil.']);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'Olá', '2026-01-01 08:15:00');
    playgroundLegacyMessage($conversationId, 'assistant', 'Bom dia!', '2026-01-01 08:16:00');

    $response = $this->actingAs($user)->getJson(route('playground.messages', $lead));

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
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);

    Ai::fakeAgent(EvaluatorAgent::class, ['Relatório sem conversa.']);

    $response = $this->actingAs($user)->postJson(route('playground.evaluate', $lead), [
        'persona_prompt' => 'Cliente desconfiado',
    ]);

    $response->assertOk()->assertJsonPath('report', 'Relatório sem conversa.');
    expect(DB::table('agent_conversation_messages')->count())->toBe(0);
});

test('evaluate appends an assistant row with the AVALIAÇÃO content prefix', function () {
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);
    $conversationId = playgroundSandboxConversation($lead);
    playgroundLegacyMessage($conversationId, 'user', 'meu cpf é 123', '2026-01-01 10:00:00');

    Ai::fakeAgent(EvaluatorAgent::class, ['Relatório detalhado da rodada.']);

    $response = $this->actingAs($user)->postJson(route('playground.evaluate', $lead), [
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
    $user = userWithTenant();

    $response = $this->actingAs($user)->postJson(route('playground.store'), [
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
    $user = userWithTenant();
    $lead = playgroundSandboxLead($user);

    $this->actingAs($user)->postJson(route('playground.chat', $lead), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

test('generateScenario validates objective and cycle via the FormRequest', function () {
    $user = userWithTenant();

    $this->actingAs($user)->postJson(route('playground.generateScenario'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['objective', 'cycle']);
});

// ─── scanBlindspots JSON parse (added in D.1 once the agent is named) ─────────

test('scanBlindspots parses a plain JSON array response into attacks', function () {
    $user = userWithTenant();
    playgroundSandboxLead($user);

    $json = json_encode([['category' => 'Prompt Injection', 'scenario' => 's', 'severity' => 'high', 'target' => 't']]);
    Ai::fakeAgent(BlindspotScannerAgent::class, [$json]);

    $this->actingAs($user)->postJson(route('playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks.0.category', 'Prompt Injection')
        ->assertJsonPath('attacks.0.severity', 'high');
});

test('scanBlindspots strips a fenced json block before parsing', function () {
    $user = userWithTenant();
    playgroundSandboxLead($user);

    $json = json_encode([['category' => 'Tool Abuse', 'scenario' => 's', 'severity' => 'low', 'target' => 't']]);
    Ai::fakeAgent(BlindspotScannerAgent::class, ["```json\n{$json}\n```"]);

    $this->actingAs($user)->postJson(route('playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks.0.category', 'Tool Abuse');
});

test('scanBlindspots returns the parse-error payload for a non-array response', function () {
    $user = userWithTenant();
    playgroundSandboxLead($user);

    Ai::fakeAgent(BlindspotScannerAgent::class, ['not json at all']);

    $this->actingAs($user)->postJson(route('playground.scanBlindspots'), [])
        ->assertOk()
        ->assertJsonPath('attacks', [])
        ->assertJsonPath('error', 'Falha no parse do JSON')
        ->assertJsonPath('raw', 'not json at all');
});

// ─── generateScenario ────────────────────────────────────────────────────────

test('generateScenario returns the agent text as scenario', function () {
    $user = userWithTenant();

    Ai::fakeAgent(ScenarioGeneratorAgent::class, ['Persona agressiva que tenta burlar validações.']);

    $this->actingAs($user)->postJson(route('playground.generateScenario'), [
        'objective' => 'Quebrar validação de CPF',
        'cycle' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('scenario', 'Persona agressiva que tenta burlar validações.');
});

// ─── index session cap ───────────────────────────────────────────────────────

test('index bounds the sandbox session list with a SQL limit (FE-05)', function () {
    $user = userWithTenant();
    playgroundSandboxLead($user);
    playgroundSandboxLead($user);

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('playground.index'))->assertOk();
    $sessionQuery = collect(DB::getQueryLog())
        ->first(fn ($q) => str_contains($q['query'], 'from "leads"') && str_contains($q['query'], 'is_sandbox'));
    DB::disableQueryLog();

    // The sidebar never hydrates the whole sandbox history — the query is capped at the source.
    expect($sessionQuery)->not->toBeNull()
        ->and($sessionQuery['query'])->toContain('limit 100');
});
