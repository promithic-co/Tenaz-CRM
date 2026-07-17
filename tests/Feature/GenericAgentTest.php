<?php

use App\Ai\Agents\GenericAgent;
use App\Ai\Agents\GenericFollowUpAgent;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\GenericWebhookTool;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Helpers ---

function genericLead(array $configState = [], array $leadState = []): Lead
{
    $agent = Agent::factory()->has(
        AgentConfig::factory()->state(array_merge([
            'agent_niche' => 'generic',
            'template_slug' => 'clinica-recepcao',
            'agent_name' => 'Sofia',
            'company_name' => 'Clínica Vida',
        ], $configState)),
        'config'
    )->create();

    return Lead::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'modo' => 'receptivo',
        'status' => 'novo',
    ], $leadState));
}

function genericNicheTemplate(array $overrides = []): NicheTemplate
{
    return NicheTemplate::factory()->create(array_merge([
        'slug' => 'clinica-recepcao',
        'niche_sections' => [
            ['title' => 'ESCOPO DO ATENDIMENTO', 'content' => 'Acolher o paciente e agendar consulta.'],
        ],
    ], $overrides));
}

// --- Instructions: PromptComposer path ---

test('instructions compose platform core, niche sections and config variables', function () {
    genericNicheTemplate();
    $lead = genericLead();

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->toContain('Você é Sofia, atendente virtual da Clínica Vida no WhatsApp.')
        ->and($instructions)->toContain('ESCOPO DO ATENDIMENTO')
        ->and($instructions)->toContain('Acolher o paciente e agendar consulta.')
        ->and($instructions)->toContain('FERRAMENTAS — PROTOCOLO DE EXECUÇÃO')
        ->and($instructions)->toContain('SEGURANÇA')
        ->and($instructions)->toContain(AgentService::NO_REPLY_SENTINEL)
        ->and($instructions)->toContain('→ Horário atual (Brasília):')
        ->and($instructions)->not->toContain('{{');
});

test('instructions never mention INSS credit concepts', function () {
    genericNicheTemplate();
    $lead = genericLead();

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->not->toContain('INSS')
        ->and($instructions)->not->toContain('consignado')
        ->and($instructions)->not->toContain('solicite o CPF')
        ->and($instructions)->not->toContain('registrar_lead_sem_credito');
});

test('missing niche template still composes the full platform core', function () {
    $lead = genericLead(['template_slug' => 'slug-inexistente']);

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->toContain('FERRAMENTAS — PROTOCOLO DE EXECUÇÃO')
        ->and($instructions)->toContain('SEGURANÇA')
        ->and($instructions)->toContain(AgentService::NO_REPLY_SENTINEL);
});

test('tenant prompt template of type system overrides the composer', function () {
    genericNicheTemplate();
    $lead = genericLead();

    PromptTemplate::create([
        'tenant_id' => $lead->tenant_id,
        'agent_id' => $lead->agent_id,
        'slug' => 'custom-system',
        'name' => 'Custom',
        'type' => 'system',
        'content' => 'PROMPT CUSTOMIZADO DO TENANT para {{agent_name}}.',
        'is_active' => true,
    ]);

    $instructions = (string) (new GenericAgent($lead))->instructions();

    expect($instructions)->toContain('PROMPT CUSTOMIZADO DO TENANT para Sofia.')
        ->and($instructions)->not->toContain('FERRAMENTAS — PROTOCOLO DE EXECUÇÃO');
});

// --- Tools ---

test('toolset is platform tools plus template webhook tools', function () {
    genericNicheTemplate();
    $lead = genericLead();

    ToolDefinition::create([
        'tenant_id' => $lead->tenant_id,
        'agent_id' => $lead->agent_id,
        'slug' => 'agendar_consulta',
        'name' => 'Agendar Consulta',
        'description' => 'Agenda a consulta do paciente via webhook.',
        'type' => 'webhook',
        'config' => ['url' => 'https://example.test/hook', 'method' => 'POST'],
        'is_active' => true,
    ]);

    $tools = collect((new GenericAgent($lead))->tools());

    expect($tools->first())->toBeInstanceOf(RegistrarInformacaoContatoTool::class)
        ->and($tools->filter(fn ($t) => $t instanceof EscalarParaHumanoTool))->toHaveCount(1)
        ->and($tools->filter(fn ($t) => $t instanceof AtualizarStatusLeadTool))->toHaveCount(1)
        ->and($tools->filter(fn ($t) => $t instanceof GenericWebhookTool))->toHaveCount(1)
        ->and($tools->filter(fn ($t) => str_contains($t::class, 'ConsultarCredito')))->toHaveCount(0);
});

test('terminal lead status strips escalation and status tools', function () {
    genericNicheTemplate();
    $lead = genericLead(leadState: ['status' => 'optou_sair']);

    $tools = collect((new GenericAgent($lead))->tools());

    expect($tools->filter(fn ($t) => $t instanceof EscalarParaHumanoTool))->toHaveCount(0)
        ->and($tools->filter(fn ($t) => $t instanceof AtualizarStatusLeadTool))->toHaveCount(0)
        ->and($tools->first())->toBeInstanceOf(RegistrarInformacaoContatoTool::class);
});

// --- GenericFollowUpAgent (Slice 5) ---

test('generic follow-up instructions carry attempt state and no credit vocabulary', function () {
    genericNicheTemplate();
    $lead = genericLead(leadState: ['followup_count' => 0]);

    $instructions = (string) (new GenericFollowUpAgent($lead))->instructions();

    expect($instructions)->toContain('Tentativa 1 de')
        ->and($instructions)->toContain('agente de reengajamento')
        ->and($instructions)->toContain(AgentService::NO_REPLY_SENTINEL)
        ->and($instructions)->not->toContain('INSS')
        ->and($instructions)->not->toContain('consignado')
        ->and($instructions)->not->toContain('consultar_credito_inss')
        ->and($instructions)->not->toContain('{{');
});

test('generic follow-up toolset has no credit consultation tool', function () {
    genericNicheTemplate();
    $lead = genericLead();

    $tools = collect((new GenericFollowUpAgent($lead))->tools());

    expect($tools->filter(fn ($t) => $t instanceof EscalarParaHumanoTool))->toHaveCount(1)
        ->and($tools->filter(fn ($t) => $t instanceof AtualizarStatusLeadTool))->toHaveCount(1)
        ->and($tools->filter(fn ($t) => str_contains($t::class, 'ConsultarCredito')))->toHaveCount(0);
});
