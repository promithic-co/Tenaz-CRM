<?php

use App\Ai\Tools\ConsultarCreditoCltTool;
use App\Models\Lead;
use App\Services\PromosysService;
use Laravel\Ai\Tools\Request;
use Mockery\MockInterface;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeCltPromosysResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'Code' => '000',
        'Consulta' => [[
            'Trabalhador' => [
                'Nome' => 'MARIA CLT TESTE',
                'Idade' => 32,
                'DataNascimento' => '1991-01-20',
                'Municipio' => ['Nome' => 'SAO PAULO'],
            ],
            'Empresas' => [[
                'Empresa' => [
                    'RazaoSocial' => 'EMPRESA PRIVADA LTDA',
                    'Cnpj' => '12345678000199',
                    'CNAE' => ['Nome' => 'Atividades de atendimento'],
                    'Vinculo' => [
                        'Ativo' => 1,
                        'Tipo' => ['Nome' => 'Empregado'],
                        'DataAdmissao' => '2020-04-01',
                        'TempoEmpresa' => 50,
                        'CBO' => ['Nome' => 'Assistente administrativo'],
                        'SalarioContratado' => '3500,00',
                        'UltimoSalario' => '3850,90',
                    ],
                ],
            ]],
        ]],
    ], $overrides);
}

test('returns invalid cpf message for short cpf', function () {
    $lead = Lead::factory()->create();
    $tool = new ConsultarCreditoCltTool($lead);

    $result = $tool->handle(new Request(['cpf' => '123']));

    expect((string) $result)->toContain('CPF invalido');
});

test('returns invalid cpf message for wrong check digits', function () {
    $lead = Lead::factory()->create();
    $tool = new ConsultarCreditoCltTool($lead);

    $result = $tool->handle(new Request(['cpf' => '11111111111']));

    expect((string) $result)->toContain('digitos verificadores incorretos');
});

test('consults promosys and qualifies lead with active clt link', function () {
    $this->mock(PromosysService::class, function (MockInterface $mock) {
        $mock->shouldReceive('consultarClt')
            ->once()
            ->with('69747830191')
            ->andReturn(makeCltPromosysResponse());
    });

    $lead = Lead::factory()->create(['modo' => 'receptivo']);
    $tool = new ConsultarCreditoCltTool($lead);

    $result = $tool->handle(new Request(['cpf' => '69747830191']));

    $lead->refresh();

    expect((string) $result)
        ->toContain('CONSULTA CLT: QUALIFICADO')
        ->toContain('EMPRESA PRIVADA LTDA')
        ->and($lead->cpf)->toBe('69747830191')
        ->and($lead->nome)->toBe('MARIA CLT TESTE')
        ->and($lead->credito_json['niche'])->toBe('clt')
        ->and($lead->status)->toBe('qualificado');
});

test('marks lead without active clt link as sem_credito', function () {
    $this->mock(PromosysService::class, function (MockInterface $mock) {
        $mock->shouldReceive('consultarClt')
            ->once()
            ->andReturn(makeCltPromosysResponse([
                'Consulta' => [[
                    'Empresas' => [[
                        'Empresa' => [
                            'RazaoSocial' => 'EMPRESA ANTIGA LTDA',
                            'Vinculo' => ['Ativo' => 0, 'DataDesligamento' => '2023-01-01'],
                        ],
                    ]],
                ]],
            ]));
    });

    $lead = Lead::factory()->create(['modo' => 'receptivo']);
    $result = (new ConsultarCreditoCltTool($lead))->handle(new Request(['cpf' => '69747830191']));

    $lead->refresh();

    expect((string) $result)->toContain('CONSULTA CLT: SEM_VINCULO')
        ->and($lead->status)->toBe('sem_credito')
        ->and($lead->credito_json['resumoGeral']['qualificado'])->toBeFalse();
});

test('returns cached clt data when cpf already consulted', function () {
    $lead = Lead::factory()->create([
        'cpf' => '69747830191',
        'credito_json' => ConsultarCreditoCltTool::normalizePayload(makeCltPromosysResponse(), '69747830191'),
    ]);

    $this->mock(PromosysService::class, function (MockInterface $mock) {
        $mock->shouldReceive('consultarClt')->never();
    });

    $result = (new ConsultarCreditoCltTool($lead))->handle(new Request(['cpf' => '69747830191']));

    expect((string) $result)->toContain('CONSULTA CLT: QUALIFICADO');
});
