<?php

use App\Ai\Agents\SiapeAgent;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoSiapeTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\RegistrarLeadSemCreditoTool;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\Lead;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createSiapeLead(array $leadOverrides = []): Lead
{
    $agent = Agent::factory()
        ->has(AgentConfig::factory()->siape(), 'config')
        ->create();

    return Lead::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'modo' => 'receptivo',
        'status' => 'novo',
    ], $leadOverrides));
}

test('SiapeAgent instructions contain SIAPE-specific content', function () {
    $lead = createSiapeLead();
    $agent = new SiapeAgent($lead);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('SIAPE')
        ->toContain('consultar_credito_siape')
        ->toContain('servidor');
});

test('SiapeAgent tools include ConsultarCreditoSiapeTool', function () {
    $lead = createSiapeLead();
    $agent = new SiapeAgent($lead);

    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($t) => $t::class, $tools);

    expect($toolClasses)
        ->toContain(ConsultarCreditoSiapeTool::class)
        ->toContain(EscalarParaHumanoTool::class)
        ->toContain(RegistrarLeadSemCreditoTool::class)
        ->toContain(AtualizarStatusLeadTool::class);
});

test('SiapeAgent does not include INSS tool', function () {
    $lead = createSiapeLead();
    $agent = new SiapeAgent($lead);

    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($t) => $t::class, $tools);

    expect($toolClasses)->not->toContain(\App\Ai\Tools\ConsultarCreditoInssTool::class);
});

test('SiapeAgent omits escalation tool when lead is already escalated', function () {
    $lead = createSiapeLead(['status' => 'escalado']);
    $agent = new SiapeAgent($lead);

    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($t) => $t::class, $tools);

    expect($toolClasses)->not->toContain(EscalarParaHumanoTool::class);
});

test('SiapeAgent lead context includes orgao and matricula', function () {
    $lead = createSiapeLead([
        'cpf' => '69747830191',
        'nome' => 'ANTONIO HENRIQUE',
        'idade' => 44,
        'credito_json' => [
            'niche' => 'siape',
            'status' => 'QUALIFICADO',
            'matricula' => [
                'codigo' => '2137426',
                'orgao' => 'EMP BRASILEIRA SERV HOSPITALARES',
                'situacaoFuncional' => 'CELETISTA EMPREGADO',
                'regimeJuridico' => 'CLT',
                'rendimentoLiquido' => 8694.86,
            ],
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 189654.4,
                    'refinanciamento' => 0,
                    'portabilidade' => 0,
                    'cartoes' => 1487.12,
                    'totalEstimado' => 191141.52,
                ],
            ],
            'produtos' => [
                'emprestimoNovo' => ['parcelaMensal' => 4741.36, 'valorLiberado' => 189654.4],
                'refinanciamento' => ['contratos' => []],
                'portabilidade' => ['contratos' => []],
                'cartoes' => [
                    ['tipo' => 'Cartão RMC', 'margemMensal' => 743.56],
                    ['tipo' => 'Cartão RCC', 'margemMensal' => 743.56],
                ],
            ],
        ],
    ]);

    $agent = new SiapeAgent($lead);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('EMP BRASILEIRA SERV HOSPITALARES')
        ->toContain('2137426')
        ->toContain('CELETISTA EMPREGADO');
});
