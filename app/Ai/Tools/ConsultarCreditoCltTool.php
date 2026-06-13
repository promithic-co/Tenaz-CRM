<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Services\FollowUpWindowService;
use App\Services\PromosysService;
use App\Support\CpfValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ConsultarCreditoCltTool implements Tool
{
    public function __construct(private readonly Lead $lead) {}

    public function description(): Stringable|string
    {
        return 'Consulta vinculo empregaticio CLT pelo CPF. Use apenas para trabalhador com carteira assinada do setor privado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $cpf = preg_replace('/\D/', '', $request['cpf']);

        if (strlen($cpf) !== 11) {
            return ToolResult::blocked('CPF invalido: deve conter exatamente 11 digitos. Peca ao cliente para reenviar.');
        }

        if (! CpfValidator::isValid($cpf)) {
            return ToolResult::blocked('CPF invalido (digitos verificadores incorretos). Peca ao cliente para conferir e reenviar.');
        }

        if ($this->lead->cpf === $cpf && ($this->lead->credito_json['niche'] ?? null) === 'clt') {
            return ToolResult::success(static::formatPayloadForAgent($this->lead->credito_json));
        }

        $circuitKey = "circuit_breaker_clt_{$this->lead->tenant_id}";
        $threshold = config('credflow.circuit_breaker.consultas_falhas_threshold', 5);

        if (Cache::get($circuitKey, 0) >= $threshold) {
            Log::warning('aria.tool.clt_circuit_breaker_open', ['lead_id' => $this->lead->id]);

            return ToolResult::error(
                'Sistema CLT temporariamente indisponivel.',
                'Nao tente chamar esta ferramenta novamente neste turno. Explique a instabilidade e ofereca atendimento humano.'
            );
        }

        try {
            $data = app(PromosysService::class)->consultarClt($cpf);

            if ($knownFailure = static::formatKnownApiFailure($data)) {
                Cache::forget($circuitKey);

                return ToolResult::blocked($knownFailure, 'Nao trate como instabilidade. Confirme os dados antes de tentar outro publico.');
            }

            $normalized = static::normalizePayload($data, $cpf);

            if (empty($normalized['cliente']['nome'])) {
                return ToolResult::blocked(
                    'A consulta CLT nao retornou dados de trabalhador para este CPF.',
                    'Informe que o CPF nao consta na base CLT ou peca para conferir os dados.'
                );
            }

            Cache::forget($circuitKey);

            $newStatus = $normalized['resumoGeral']['qualificado'] ? 'qualificado' : 'sem_credito';
            $updateData = [
                'cpf' => $cpf,
                'nome' => $normalized['cliente']['nome'] ?? $this->lead->nome,
                'idade' => $normalized['cliente']['idade'] ?? $this->lead->idade,
                'credito_json' => $normalized,
                'status' => $newStatus,
                'last_interaction_at' => now(),
            ];

            if ($newStatus === 'qualificado') {
                $updateData['followup_status'] = app(FollowUpWindowService::class)
                    ->canSendFreeFormMessage($this->lead) ? 'active' : 'inactive';
                $updateData['followup_count'] = 0;
            }

            $this->lead->update($updateData);

            return ToolResult::success(static::formatPayloadForAgent($normalized), data: [
                'niche' => 'clt',
                'status' => $normalized['status'],
            ]);
        } catch (Throwable $e) {
            $this->incrementCircuitBreaker($circuitKey);

            Log::error('aria.tool.clt_error', [
                'lead_id' => $this->lead->id,
                'cpf' => substr($cpf, 0, 3).'***',
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(
                'Consulta CLT falhou por instabilidade ou timeout.',
                'Tente chamar consultar_credito_clt mais uma vez. Se falhar novamente, acione escalar_para_humano com motivo problema_tecnico.'
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cpf' => $schema->string()
                ->description('CPF com 11 digitos')
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function formatKnownApiFailure(array $data): ?string
    {
        $code = (string) ($data['Code'] ?? '');
        $message = trim((string) ($data['Msg'] ?? ''));

        if ($code === '' || $code === '000') {
            return null;
        }

        return $message !== ''
            ? "Consulta CLT informou: {$message}."
            : 'A consulta CLT nao retornou dados para este CPF.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $data, string $cpf): array
    {
        $consulta = $data['Consulta'][0] ?? [];
        $trabalhador = $consulta['Trabalhador'] ?? [];
        $empresas = array_values($consulta['Empresas'] ?? []);
        $activeCompanies = array_values(array_filter($empresas, function (array $entry): bool {
            $empresa = $entry['Empresa'] ?? $entry;
            $vinculo = $empresa['Vinculo'] ?? [];

            return (int) ($vinculo['Ativo'] ?? 0) === 1;
        }));

        $status = $activeCompanies !== [] ? 'QUALIFICADO' : 'SEM_VINCULO';
        $nome = $trabalhador['Nome'] ?? null;

        return [
            'niche' => 'clt',
            'status' => $status,
            'cliente' => [
                'nome' => $nome,
                'primeiroNome' => $nome ? explode(' ', $nome)[0] : null,
                'cpf' => $cpf,
                'idade' => isset($trabalhador['Idade']) ? (int) $trabalhador['Idade'] : null,
                'dataNascimento' => $trabalhador['DataNascimento'] ?? null,
                'municipio' => $trabalhador['Municipio']['Nome'] ?? null,
            ],
            'resumoGeral' => [
                'qualificado' => $activeCompanies !== [],
                'totais' => [
                    'vinculos' => count($empresas),
                    'vinculosAtivos' => count($activeCompanies),
                ],
                'textoResumo' => $activeCompanies !== []
                    ? 'Trabalhador CLT com vinculo ativo localizado.'
                    : 'Nenhum vinculo CLT ativo localizado para este CPF.',
            ],
            'vinculos' => array_map(fn (array $entry): array => static::normalizeEmpresa($entry), $empresas),
            'raw' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function formatPayloadForAgent(array $data): string
    {
        $cliente = $data['cliente'] ?? [];
        $lines = ['CONSULTA CLT: '.($data['status'] ?? 'DESCONHECIDO')];

        $nome = $cliente['nome'] ?? 'Cliente';
        $idade = $cliente['idade'] ?? null;
        $lines[] = $idade ? "Trabalhador: {$nome} ({$idade} anos)" : "Trabalhador: {$nome}";

        $infoParts = [];
        if (! empty($cliente['cpf'])) {
            $infoParts[] = 'CPF: '.static::maskCpf((string) $cliente['cpf']);
        }
        if (! empty($cliente['dataNascimento'])) {
            $infoParts[] = "Nascimento: {$cliente['dataNascimento']}";
        }
        if (! empty($cliente['municipio'])) {
            $infoParts[] = "Municipio: {$cliente['municipio']}";
        }
        if ($infoParts !== []) {
            $lines[] = implode(' | ', $infoParts);
        }

        $vinculos = $data['vinculos'] ?? [];
        $lines[] = '';
        if ($vinculos === []) {
            $lines[] = 'VINCULO EMPREGATICIO: nenhum vinculo CLT registrado para este CPF.';

            return implode("\n", $lines);
        }

        $lines[] = 'VINCULOS EMPREGATICIOS ('.count($vinculos).'):';
        foreach ($vinculos as $index => $vinculo) {
            $lines[] = '';
            $lines[] = 'Empresa '.($index + 1).': '.($vinculo['razaoSocial'] ?? 'Empresa');
            if (! empty($vinculo['cnpj'])) {
                $lines[] = "  CNPJ: {$vinculo['cnpj']}";
            }
            if (! empty($vinculo['cnae'])) {
                $lines[] = "  CNAE: {$vinculo['cnae']}";
            }
            $lines[] = '  Vinculo: '.(($vinculo['ativo'] ?? false) ? 'Ativo' : 'Inativo').(! empty($vinculo['tipo']) ? " | {$vinculo['tipo']}" : '');
            if (! empty($vinculo['dataAdmissao']) || ! empty($vinculo['tempoEmpresaMeses'])) {
                $parts = array_filter([
                    ! empty($vinculo['dataAdmissao']) ? "Admissao: {$vinculo['dataAdmissao']}" : null,
                    ! empty($vinculo['dataDesligamento']) ? "Desligamento: {$vinculo['dataDesligamento']}" : null,
                    ! empty($vinculo['tempoEmpresaMeses']) ? "Tempo de empresa: {$vinculo['tempoEmpresaMeses']} meses" : null,
                ]);
                $lines[] = '  '.implode(' | ', $parts);
            }
            if (! empty($vinculo['cargo'])) {
                $lines[] = "  Cargo: {$vinculo['cargo']}";
            }
            $salaryParts = [];
            foreach (['salarioContratado' => 'Contratado', 'ultimoSalario' => 'Ultimo', 'mediaDeclarada' => 'Media declarada'] as $key => $label) {
                if (isset($vinculo[$key]) && $vinculo[$key] !== null) {
                    $salaryParts[] = "{$label}: ".static::brl((float) $vinculo[$key]);
                }
            }
            if ($salaryParts !== []) {
                $lines[] = '  Salario - '.implode(' | ', $salaryParts);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function normalizeEmpresa(array $entry): array
    {
        $empresa = $entry['Empresa'] ?? $entry;
        $vinculo = $empresa['Vinculo'] ?? [];

        return [
            'razaoSocial' => $empresa['RazaoSocial'] ?? null,
            'cnpj' => isset($empresa['Cnpj']) ? (string) $empresa['Cnpj'] : null,
            'cnae' => $empresa['CNAE']['Nome'] ?? null,
            'naturezaJuridica' => $empresa['NaturezaJuridica']['Nome'] ?? null,
            'ativo' => (int) ($vinculo['Ativo'] ?? 0) === 1,
            'tipo' => $vinculo['Tipo']['Nome'] ?? null,
            'dataAdmissao' => $vinculo['DataAdmissao'] ?? null,
            'dataDesligamento' => $vinculo['DataDesligamento'] ?? null,
            'tempoEmpresaMeses' => $vinculo['TempoEmpresa'] ?? null,
            'cargo' => $vinculo['CBO']['Nome'] ?? null,
            'salarioContratado' => static::parseMoney($vinculo['SalarioContratado'] ?? null),
            'ultimoSalario' => static::parseMoney($vinculo['UltimoSalario'] ?? null),
            'mediaDeclarada' => static::parseMoney($vinculo['ValorRemuneracaoMediaDeclarada'] ?? null),
        ];
    }

    private static function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', trim((string) $value));
        if ($normalized === '' || $normalized === null) {
            return null;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $normalized = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif ($lastComma !== false) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, '.') > 1) {
            $lastDot = strrpos($normalized, '.');
            $decimalDigits = strlen($normalized) - $lastDot - 1;
            $normalized = $decimalDigits === 2
                ? str_replace('.', '', substr($normalized, 0, $lastDot)).substr($normalized, $lastDot)
                : str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    private static function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        if (strlen($digits) < 11) {
            return '***';
        }

        return '***'.substr($digits, 3, 3).'***'.substr($digits, 9, 2);
    }

    private function incrementCircuitBreaker(string $circuitKey): void
    {
        $windowMinutes = config('credflow.circuit_breaker.window_minutes', 5);
        if (! Cache::has($circuitKey)) {
            Cache::put($circuitKey, 0, now()->addMinutes($windowMinutes));
        }
        Cache::increment($circuitKey);
    }
}
