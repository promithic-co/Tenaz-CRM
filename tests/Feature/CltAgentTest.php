<?php

use App\Ai\Agents\CltAgent;
use App\Ai\Tools\ConsultarCreditoCltTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\ConsultarCreditoSiapeTool;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createCltLead(array $leadOverrides = []): Lead
{
    $agent = Agent::factory()
        ->has(AgentConfig::factory()->clt(), 'config')
        ->create();

    return Lead::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'modo' => 'receptivo',
        'status' => 'novo',
    ], $leadOverrides));
}

test('CltAgent instructions contain CLT-specific scope and tool', function () {
    $lead = createCltLead();
    $agent = new CltAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('CLT')
        ->toContain('consultar_credito_clt')
        ->toContain('Nao consulte INSS ou SIAPE');
});

test('CltAgent tools include CLT consultation and not INSS or SIAPE consultations', function () {
    $lead = createCltLead();
    $agent = new CltAgent($lead);

    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($tool) => $tool::class, $tools);

    expect($toolClasses)
        ->toContain(ConsultarCreditoCltTool::class)
        ->not->toContain(ConsultarCreditoInssTool::class)
        ->not->toContain(ConsultarCreditoSiapeTool::class);
});
