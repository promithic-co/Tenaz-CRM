<?php

use App\Ai\Tools\ConsultarCreditoSiapeTool;
use App\Models\Lead;
use App\Services\PromosysService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/** Simulates n8n returning already-qualified SIAPE JSON */
function makeSiapeWebhookResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'niche' => 'siape',
        'status' => 'QUALIFICADO',
        'cliente' => ['nome' => 'ANTONIO HENRIQUE GOMES DOS SANTOS', 'primeiroNome' => 'ANTONIO', 'cpf' => '69747830191', 'idade' => 44],
        'matricula' => [
            'codigo' => '2137426',
            'orgao' => 'EMP BRASILEIRA SERV HOSPITALARES',
            'orgaoCodigo' => '26443',
            'situacaoFuncional' => 'CELETISTA EMPREGADO',
            'regimeJuridico' => 'CLT',
            'rendimentoBruto' => 16173.96,
            'rendimentoLiquido' => 8694.86,
        ],
        'resumoGeral' => [
            'qualificado' => true,
            'totais' => ['margemLivre' => 189654.4, 'refinanciamento' => 0, 'portabilidade' => 0, 'cartoes' => 1487.12, 'totalEstimado' => 191141.52],
            'textoResumo' => 'QUALIFICADO',
        ],
        'produtos' => [
            'emprestimoNovo' => ['disponivel' => true, 'parcelaMensal' => 4741.36, 'valorLiberado' => 189654.4],
            'refinanciamento' => ['contratos' => [], 'totalLiberado' => 0],
            'portabilidade' => ['contratos' => [], 'totalParcelas' => 0],
            'cartoes' => [
                ['tipo' => 'Cartão RMC', 'margemMensal' => 743.56, 'parcelaMensal' => 743.56],
                ['tipo' => 'Cartão RCC', 'margemMensal' => 743.56, 'parcelaMensal' => 743.56],
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    config(['services.credflow.webhook_consulta_siape' => 'https://n8n.test/webhook/siape']);
});

test('returns invalid cpf message for short cpf', function () {
    $lead = Lead::factory()->create();
    $tool = new ConsultarCreditoSiapeTool($lead);

    $result = $tool->handle(new Request(['cpf' => '123']));

    expect((string) $result)->toContain('CPF inválido');
});

test('returns invalid cpf message for wrong check digits', function () {
    $lead = Lead::factory()->create();
    $tool = new ConsultarCreditoSiapeTool($lead);

    $result = $tool->handle(new Request(['cpf' => '11111111111']));

    expect((string) $result)->toContain('dígitos verificadores incorretos');
});

test('returns cached data when cpf already consulted', function () {
    $lead = Lead::factory()->create([
        'cpf' => '69747830191',
        'credito_json' => makeSiapeWebhookResponse(),
    ]);

    Http::shouldReceive('timeout')->never();

    $tool = new ConsultarCreditoSiapeTool($lead);
    $result = $tool->handle(new Request(['cpf' => '69747830191']));

    expect((string) $result)->toContain('CONSULTA SIAPE');
});

test('calls n8n webhook and updates lead on success', function () {
    Http::fake([
        'n8n.test/*' => Http::response(makeSiapeWebhookResponse(), 200),
    ]);

    $lead = Lead::factory()->create(['modo' => 'receptivo']);
    $tool = new ConsultarCreditoSiapeTool($lead);

    $result = $tool->handle(new Request(['cpf' => '69747830191']));

    $lead->refresh();

    expect((string) $result)->toContain('CONSULTA SIAPE')
        ->and($lead->cpf)->toBe('69747830191')
        ->and($lead->nome)->toBe('ANTONIO HENRIQUE GOMES DOS SANTOS')
        ->and($lead->credito_json)->toBeArray()
        ->and($lead->credito_json['niche'])->toBe('siape')
        ->and($lead->status)->toBe('qualificado');
});

test('sends only cpf to webhook (no promosys token)', function () {
    Http::fake([
        'n8n.test/*' => Http::response(makeSiapeWebhookResponse(), 200),
    ]);

    $lead = Lead::factory()->create();
    $tool = new ConsultarCreditoSiapeTool($lead);
    $tool->handle(new Request(['cpf' => '69747830191']));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://n8n.test/webhook/siape'
            && $request['cpf'] === '69747830191'
            && ! isset($request['token']);
    });
});

test('handles bruto promosys response by qualifying via SiapeQualificacaoService', function () {
    $brutoResponse = [
        'NOME' => 'ANTONIO HENRIQUE',
        'FULL_CPF' => '69747830191',
        'IDADE' => 44,
        'MATRICULA' => [
            'Codigo' => '2137426',
            'Orgao' => ['Codigo' => '26443', 'Nome' => 'EMP BRASILEIRA'],
            'SituacaoFunc' => 'CELETISTA EMPREGADO',
            'RJUR' => 'CLT',
            'RendimentoBruto' => '16173.96',
            'RendimentoLiquido' => '8694.86',
            'MargemEmprestimo' => '1000',
            'MargemCartaoRmc' => '0',
            'MargemCartaoRcc' => '0',
            'MargemLiquidaCompulsoria' => '0',
            'MargemCalculada' => 0,
            'margemdispcartao' => 0,
        ],
        'CONTRATO' => [],
    ];

    Http::fake([
        'n8n.test/*' => Http::response($brutoResponse, 200),
    ]);

    $lead = Lead::factory()->create();
    $result = (new ConsultarCreditoSiapeTool($lead))->handle(new Request(['cpf' => '69747830191']));

    expect((string) $result)->toContain('CONSULTA SIAPE: QUALIFICADO');
});

test('uses direct Promosys SIAPE consultation when configured', function () {
    $this->mock(PromosysService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
        $mock->shouldReceive('consultarSiape')
            ->once()
            ->with('69747830191')
            ->andReturn([
                'Code' => '000',
                'Consulta' => [[
                    'NOME' => 'ANTONIO HENRIQUE',
                    'FULL_CPF' => '69747830191',
                    'IDADE' => 44,
                    'MATRICULA' => [
                        'Codigo' => '2137426',
                        'Orgao' => ['Codigo' => '26443', 'Nome' => 'EMP BRASILEIRA'],
                        'SituacaoFunc' => 'ATIVO PERMANENTE',
                        'RJUR' => 'ESTATUTARIO',
                        'RendimentoBruto' => '16173.96',
                        'RendimentoLiquido' => '8694.86',
                        'MargemEmprestimo' => '1000',
                        'MargemCartaoRmc' => '0',
                        'MargemCartaoRcc' => '0',
                        'MargemLiquidaCompulsoria' => '0',
                        'MargemCalculada' => 0,
                        'margemdispcartao' => 0,
                    ],
                    'CONTRATO' => [],
                ]],
            ]);
    });

    $lead = Lead::factory()->create();
    $result = (new ConsultarCreditoSiapeTool($lead))->handle(new Request(['cpf' => '69747830191']));

    $lead->refresh();

    expect((string) $result)->toContain('CONSULTA SIAPE: QUALIFICADO')
        ->and($lead->cpf)->toBe('69747830191')
        ->and($lead->credito_json['niche'])->toBe('siape')
        ->and($lead->status)->toBe('qualificado');
});

test('circuit breaker blocks requests after threshold', function () {
    $lead = Lead::factory()->create(['tenant_id' => 1]);
    // Open state = failure count at threshold AND the cooldown gate active (set together
    // by incrementCircuitBreaker when the threshold is crossed).
    Cache::put('circuit_breaker_siape_1', 5, now()->addMinutes(5));
    Cache::put('circuit_breaker_siape_1_open', 1, now()->addMinutes(5));

    $result = (new ConsultarCreditoSiapeTool($lead))->handle(new Request(['cpf' => '69747830191']));

    expect((string) $result)->toContain('temporariamente indisponível');
});

test('returns config missing message when webhook url not set', function () {
    config(['services.credflow.webhook_consulta_siape' => null]);

    $lead = Lead::factory()->create();
    $result = (new ConsultarCreditoSiapeTool($lead))->handle(new Request(['cpf' => '69747830191']));

    expect((string) $result)->toContain('não configurado');
});
