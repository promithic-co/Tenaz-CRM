<?php

use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Ai\Middleware\AuditLogMiddleware;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Models\Lead;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFollowUpLead(array $attributes = []): Lead
{
    $user = User::factory()->create();

    return Lead::factory()->create(array_merge([
        'tenant_id' => $user->tenantId,
        'modo' => 'receptivo',
        'status' => 'qualificado',
        'followup_status' => 'active',
        'followup_count' => 0,
    ], $attributes));
}

// ── Tools ─────────────────────────────────────────────────────────────────────

test('CredFlowFollowUpAgent registers ConsultarCreditoInssTool', function () {
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->toContain(ConsultarCreditoInssTool::class);
});

test('CredFlowFollowUpAgent registers EscalarParaHumanoTool when lead is not terminal', function () {
    $lead = makeFollowUpLead(['status' => 'qualificado']);
    $agent = new CredFlowFollowUpAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->toContain(EscalarParaHumanoTool::class);
});

test('CredFlowFollowUpAgent omits EscalarParaHumanoTool when lead already escalated', function () {
    $lead = makeFollowUpLead(['status' => 'escalado']);
    $agent = new CredFlowFollowUpAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->not->toContain(EscalarParaHumanoTool::class);
});

test('CredFlowFollowUpAgent registers AtualizarStatusLeadTool when lead is not opted out', function () {
    $lead = makeFollowUpLead(['status' => 'qualificado']);
    $agent = new CredFlowFollowUpAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->toContain(AtualizarStatusLeadTool::class);
});

test('CredFlowFollowUpAgent omits AtualizarStatusLeadTool when lead already opted out', function () {
    $lead = makeFollowUpLead(['status' => 'optou_sair']);
    $agent = new CredFlowFollowUpAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->not->toContain(AtualizarStatusLeadTool::class);
});

// ── Middleware ─────────────────────────────────────────────────────────────────

test('CredFlowFollowUpAgent includes ToolCallGuardMiddleware', function () {
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    expect($agent->middleware())->toContain(ToolCallGuardMiddleware::class);
});

test('CredFlowFollowUpAgent includes AuditLogMiddleware', function () {
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    expect($agent->middleware())->toContain(AuditLogMiddleware::class);
});

// ── Instructions ──────────────────────────────────────────────────────────────

test('CredFlowFollowUpAgent instructions contain autonomous decision section', function () {
    $lead = makeFollowUpLead(['followup_count' => 0]);
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('DECISÃO AUTÔNOMA');
    expect($instructions)->toContain('escalar_para_humano');
    expect($instructions)->toContain('atualizar_status_lead');
    expect($instructions)->toContain('consultar_credito_inss');
});

test('CredFlowFollowUpAgent instructions reflect first attempt tone', function () {
    $lead = makeFollowUpLead(['followup_count' => 0]);
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('PRIMEIRA TENTATIVA');
});

test('CredFlowFollowUpAgent instructions signal last attempt when at ceiling', function () {
    $lead = makeFollowUpLead(['followup_count' => 3]); // 0-indexed, max=4 → this is last
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('ÚLTIMA TENTATIVA');
});

test('CredFlowFollowUpAgent instructions mention stale credit handling in decision tree', function () {
    // The autonomous decision tree always mentions credit freshness as option 3 —
    // we test that the instruction text is present rather than the runtime hint
    // (which depends on DB timestamp comparison).
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('desatualizados');
    expect($instructions)->toContain('consultar_credito_inss');
});

test('CredFlowFollowUpAgent instructions warn about inactive lead', function () {
    $lead = makeFollowUpLead();
    // Set the property directly on the in-memory model — the agent reads $this->lead
    // without re-querying the DB, so this simulates a stale lead without timezone issues.
    $lead->last_interaction_at = now()->subDays(10);

    $agent = new CredFlowFollowUpAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('LEAD INATIVO HÁ');
});

test('CredFlowFollowUpAgent loads DB prompt template when available', function () {
    $lead = makeFollowUpLead();

    PromptTemplate::create([
        'tenant_id' => $lead->tenant_id,
        'agent_id' => null,
        'name' => 'Follow-up Custom',
        'slug' => 'followup-custom',
        'type' => 'followup',
        'content' => 'Olá! Tentativa {{attempt_number}} de {{max_count}}.',
        'version' => 1,
        'is_active' => true,
    ]);

    $agent = new CredFlowFollowUpAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Tentativa 1 de');
    expect($instructions)->not->toContain('{{attempt_number}}');
    expect($instructions)->not->toContain('{{max_count}}');
});

test('CredFlowFollowUpAgent instructions include tool result semantics', function () {
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('success');
    expect($instructions)->toContain('already_done');
    expect($instructions)->toContain('blocked');
});

test('CredFlowFollowUpAgent instructions do not contain 1-tool-per-turn constraint', function () {
    $lead = makeFollowUpLead();
    $agent = new CredFlowFollowUpAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Máx 1 chamada/ferramenta/turno');
});
