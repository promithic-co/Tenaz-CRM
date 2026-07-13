<?php

use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Models\Agent;
use App\Models\AgentFollowUpSetting;
use App\Models\AppSetting;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    AppSetting::flushCache();
});

// ---------------------------------------------------------------------------
// buildStatusHint — awareness de documentos coletados
// ---------------------------------------------------------------------------

test('buildStatusHint qualificado sem docs sugere apresentar ofertas', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'documentos_coletados' => [],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('apresente as ofertas disponíveis');
    expect($prompt)->not->toContain('Não reapresente as ofertas');
});

test('buildStatusHint qualificado com docs parciais instrui continuar coleta', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'documentos_coletados' => ['rg' => true, 'residencia' => false],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('continue coletando documentos (já recebidos: 1)');
    expect($prompt)->toContain('Não reapresente as ofertas');
});

test('buildStatusHint qualificado com 3 ou mais docs instrui escalar', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'documentos_coletados' => ['rg' => true, 'residencia' => true, 'banco' => true],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('documentação completa — acione `escalar_para_humano`');
});

test('buildStatusHint escalado instrui não escalar novamente', function () {
    $lead = Lead::factory()->create(['status' => 'escalado']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('LEAD JÁ ESCALADO');
    expect($prompt)->toContain('não acione `escalar_para_humano` novamente');
});

// ---------------------------------------------------------------------------
// Abandono — trigger preciso de [ARIA_NAO_RESPONDER]
// ---------------------------------------------------------------------------

test('prompt de abandono exige intenção clara e definitiva', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('não quero mais');
    expect($prompt)->toContain('me bloqueia');
    expect($prompt)->toContain('NÃO acionar para');
    expect($prompt)->toContain('para mim funciona');
    expect($prompt)->toContain('saiu o resultado');
});

// ---------------------------------------------------------------------------
// CPF de terceiro
// ---------------------------------------------------------------------------

test('prompt instrui verificar CPF de terceiro', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Este CPF é o seu mesmo?');
    expect($prompt)->toContain('Não apresente oferta para CPF de terceiro');
});

// ---------------------------------------------------------------------------
// CPF recusado / medo de fraude
// ---------------------------------------------------------------------------

test('prompt inclui instrução para CPF recusado', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('CPF recusado ou cliente com medo de fraude');
});

// ---------------------------------------------------------------------------
// Portabilidade
// ---------------------------------------------------------------------------

test('prompt explica portabilidade como refinanciamento', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('## PORTABILIDADE');
    expect($prompt)->toContain('Portabilidade pura (só redução de parcela, sem troco) não está disponível');
});

// ---------------------------------------------------------------------------
// Valor de parcela e diferença entre produtos
// ---------------------------------------------------------------------------

test('prompt explica parcelas por produto com framing de vendedor', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('## PARCELA E CONDIÇÕES POR PRODUTO');
    expect($prompt)->toContain('você recebe R$ X em mãos, com uma parcela de R$ Y/mês');
    expect($prompt)->toContain('sem parcela nova');
    expect($prompt)->toContain('fala dos 3');
    expect($prompt)->toContain('Nunca diga "depende da modalidade"');
});

test('prompt restringe mencao de desconto no salario somente quando cliente perguntar', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('APENAS se o cliente perguntar especificamente');
    expect($prompt)->toContain('quanto vai descontar');
});

test('contexto com refin inclui troco e sem parcela nova', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'cpf' => '12345678901',
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 0,
                    'refinanciamento' => 4757.24,
                    'cartoes' => 0,
                    'totalEstimado' => 4757.24,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => [],
                    'refinanciamento' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Refin=troco de R$ 4.757,24 (sem parcela nova)');
});

test('contexto com novo inclui valor liberado e parcela mensal', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'cpf' => '12345678901',
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 2899.82,
                    'refinanciamento' => 0,
                    'cartoes' => 0,
                    'totalEstimado' => 2899.82,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => [
                        'valorLiberado' => 2899.82,
                        'parcelaMensal' => 66.0,
                    ],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [],
                ],
            ]],
        ],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Novo=libera R$ 2.899,82 (parcela R$ 66,00/mês)');
});

test('contexto com cartao inclui tipo e parcela mensal', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'cpf' => '12345678901',
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 0,
                    'refinanciamento' => 0,
                    'cartoes' => 1814.61,
                    'totalEstimado' => 1814.61,
                ],
            ],
            'beneficios' => [[
                'produtos' => [
                    'emprestimoNovo' => [],
                    'refinanciamento' => ['contratos' => []],
                    'portabilidade' => ['contratos' => []],
                    'cartoes' => [
                        ['tipo' => 'Cartão RMC', 'valorSaque' => 1814.61, 'parcelaMensal' => 50.54],
                    ],
                ],
            ]],
        ],
    ]);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Cartão RMC');
    expect($prompt)->toContain('parcela R$ 50,54/mês');
    expect($prompt)->toContain('saque R$ 1.814,61');
});

// ---------------------------------------------------------------------------
// Re-engajamento e casos especiais
// ---------------------------------------------------------------------------

test('prompt instrui re-engajamento sem reiniciar fluxo', function () {
    $lead = Lead::factory()->create(['status' => 'qualificado', 'cpf' => '12345678901']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Re-engajamento');
    expect($prompt)->toContain('não recomece o fluxo do zero');
});

test('prompt inclui instrução para menores de idade', function () {
    $lead = Lead::factory()->create(['status' => 'novo']);

    $agent = new CredFlowAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('menores de idade ou sob curatela');
});

// ---------------------------------------------------------------------------
// CredFlowFollowUpAgent — gradiente por tentativa
// ---------------------------------------------------------------------------

test('followup prompt varia conforme numero da tentativa', function () {
    // Ensure max_attempts_within_window is high enough that count=0..2 are not "last attempt".
    $agent = Agent::factory()->create();
    AgentFollowUpSetting::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $agent->tenant_id,
        'max_attempts_within_window' => 5,
    ]);

    $tenantId = $agent->tenant_id;

    $lead0 = Lead::factory()->create(['tenant_id' => $tenantId, 'agent_id' => $agent->id, 'status' => 'qualificado', 'followup_count' => 0]);
    $lead1 = Lead::factory()->create(['tenant_id' => $tenantId, 'agent_id' => $agent->id, 'status' => 'qualificado', 'followup_count' => 1]);
    $lead2 = Lead::factory()->create(['tenant_id' => $tenantId, 'agent_id' => $agent->id, 'status' => 'qualificado', 'followup_count' => 2]);

    $prompt0 = (string) (new CredFlowFollowUpAgent($lead0))->instructions();
    $prompt1 = (string) (new CredFlowFollowUpAgent($lead1))->instructions();
    $prompt2 = (string) (new CredFlowFollowUpAgent($lead2))->instructions();

    expect($prompt0)->toContain('Tentativa 1 de');
    expect($prompt0)->toContain('Leve recontato');

    expect($prompt1)->toContain('Tentativa 2 de');
    expect($prompt1)->toContain('Reforce a oportunidade');

    expect($prompt2)->toContain('Tentativa 3 de');
    expect($prompt2)->toContain('Urgência moderada');
});

test('followup prompt ultima tentativa instrui despedida', function () {
    $maxCount = (int) AppSetting::get('followup_max_count', 4);

    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'followup_count' => $maxCount - 1,
    ]);

    $agent = new CredFlowFollowUpAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Despedida respeitosa');
});

test('followup prompt menciona valor de credito quando disponivel', function () {
    $lead = Lead::factory()->create([
        'status' => 'qualificado',
        'followup_count' => 0,
        'credito_json' => [
            'status' => 'QUALIFICADO',
            'cliente' => ['nome' => 'João Silva', 'idade' => 65],
            'resumoGeral' => [
                'totais' => [
                    'margemLivre' => 5200.00,
                    'refinanciamento' => 0,
                    'cartoes' => 0,
                    'totalEstimado' => 5200.00,
                ],
            ],
            'beneficios' => [],
        ],
    ]);

    $agent = new CredFlowFollowUpAgent($lead);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('R$ 5.200,00');
});
