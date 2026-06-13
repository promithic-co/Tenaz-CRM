<?php

use App\Ai\Agents\CredFlowAgent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReceptivoLead(array $attributes = []): Lead
{
    $user = User::factory()->create();

    return Lead::factory()->create(array_merge([
        'tenant_id' => $user->tenantId,
        'modo' => 'receptivo',
        'status' => 'novo',
    ], $attributes));
}

test('CredFlowAgent instructions no longer contain 1-tool-per-turn constraint', function () {
    $lead = makeReceptivoLead();
    $agent = new CredFlowAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Máx 1 chamada/ferramenta/turno');
    expect($instructions)->not->toContain('Erro 2× seguidos: informe instabilidade e pare');
});

test('CredFlowAgent instructions include autonomous tool execution section', function () {
    $lead = makeReceptivoLead();
    $agent = new CredFlowAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('EXECUÇÃO AUTÔNOMA');
    expect($instructions)->toContain('encadear chamadas de ferramentas');
});

test('CredFlowAgent instructions explain all ToolResult statuses', function () {
    $lead = makeReceptivoLead();
    $agent = new CredFlowAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('`success`');
    expect($instructions)->toContain('`error`');
    expect($instructions)->toContain('`already_done`');
    expect($instructions)->toContain('`blocked`');
});

test('CredFlowAgent tools are context-filtered based on lead status', function () {
    $escalatedLead = makeReceptivoLead(['status' => 'escalado']);
    $agent = new CredFlowAgent($escalatedLead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->not->toContain(\App\Ai\Tools\EscalarParaHumanoTool::class);
});

test('CredFlowAgent includes escalar tool when lead is qualificado', function () {
    $lead = makeReceptivoLead(['status' => 'qualificado']);
    $agent = new CredFlowAgent($lead);

    $toolClasses = collect($agent->tools())->map(fn ($t) => get_class($t))->all();

    expect($toolClasses)->toContain(\App\Ai\Tools\EscalarParaHumanoTool::class);
});
