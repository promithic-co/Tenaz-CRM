<?php

use App\Ai\AgentFactory;
use App\Ai\Agents\CredFlowBulkAgent;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBulkLead(array $attributes = []): Lead
{
    $user = User::factory()->create();

    return Lead::factory()->create(array_merge([
        'tenant_id' => $user->tenantId,
        'modo' => 'bulk',
        'status' => 'novo',
    ], $attributes));
}

test('CredFlowBulkAgent uses DB prompt template when available', function () {
    $lead = makeBulkLead();

    PromptTemplate::create([
        'tenant_id' => $lead->tenant_id,
        'agent_id' => null,
        'name' => 'Bulk System',
        'slug' => 'bulk-system',
        'type' => 'system_bulk',
        'content' => 'Você é {{agent_name}} da {{company_name}}.',
        'version' => 1,
        'is_active' => true,
    ]);

    $agent = new CredFlowBulkAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('da');
    expect($instructions)->not->toContain('{{agent_name}}');
    expect($instructions)->not->toContain('{{company_name}}');
});

test('CredFlowBulkAgent fallback prompt contains outbound tone', function () {
    $lead = makeBulkLead();

    $agent = new CredFlowBulkAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('você foi até ele');
});

test('CredFlowBulkAgent fallback prompt contains campaign context section', function () {
    $lead = makeBulkLead();

    $agent = new CredFlowBulkAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('CONTEXTO DE CAMPANHA');
});

test('CredFlowBulkAgent includes campaign name when lead has campaign_id', function () {
    $campaign = Campaign::factory()->create(['name' => 'Campanha Agosto 2026']);
    $lead = makeBulkLead([
        'tenant_id' => (string) $campaign->tenant_id,
        'campaign_id' => $campaign->id,
    ]);

    $agent = new CredFlowBulkAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Campanha Agosto 2026');
});

test('CredFlowBulkAgent has exactly 3 tools for fresh lead', function () {
    $lead = makeBulkLead();

    $agent = new CredFlowBulkAgent($lead);
    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($t) => get_class($t), $tools);

    expect($toolClasses)->toContain(ConsultarCreditoInssTool::class);
    expect($toolClasses)->toContain(EscalarParaHumanoTool::class);
    expect($toolClasses)->toContain(AtualizarStatusLeadTool::class);
    expect($tools)->toHaveCount(3);
});

test('CredFlowBulkAgent does not include ConsultarCreditoInss when credito already set', function () {
    $lead = makeBulkLead([
        'credito_json' => ['status' => 'QUALIFICADO'],
    ]);

    $agent = new CredFlowBulkAgent($lead);
    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($t) => get_class($t), $tools);

    expect($toolClasses)->not->toContain(ConsultarCreditoInssTool::class);
});

test('CredFlowBulkAgent maxConversationMessages returns positive integer via reflection', function () {
    $lead = makeBulkLead();

    $agent = new CredFlowBulkAgent($lead);
    $method = new ReflectionMethod($agent, 'maxConversationMessages');
    $method->setAccessible(true);

    expect($method->invoke($agent))->toBeInt()->toBeGreaterThan(0);
});

test('AgentFactory resolves CredFlowBulkAgent for bulk modo', function () {
    $lead = makeBulkLead();

    $factory = app(AgentFactory::class);
    $agent = $factory->make($lead);

    expect($agent)->toBeInstanceOf(CredFlowBulkAgent::class);
});
