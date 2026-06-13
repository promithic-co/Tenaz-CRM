<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\AgentOperationalRule;
use App\Services\FollowUpWindowService;
use App\Services\PromosysService;
use App\Services\SiapeQualificacaoService;
use App\Support\CpfValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ConsultarCreditoSiapeTool extends AbstractConsultaCreditoTool
{
    public function description(): Stringable|string
    {
        return 'Consulta crédito SIAPE pelo CPF do servidor público federal. Chamada automática ao receber CPF.';
    }

    protected function nicheLabel(): string
    {
        return 'SIAPE';
    }

    protected function audienceWord(): string
    {
        return 'servidor';
    }

    protected function circuitSlug(): string
    {
        return 'siape';
    }

    protected function webhookConfigKey(): string
    {
        return 'services.credflow.webhook_consulta_siape';
    }

    protected function webhookEnvHint(): string
    {
        return 'TENAZ_WEBHOOK_CONSULTA_SIAPE';
    }

    protected function toolName(): string
    {
        return 'consultar_credito_siape';
    }

    public function handle(Request $request): Stringable|string
    {
        $promosys = app(PromosysService::class);

        if (! $promosys->isConfigured()) {
            return parent::handle($request);
        }

        $cpf = preg_replace('/\D/', '', $request['cpf']);

        if (strlen($cpf) !== 11) {
            return ToolResult::blocked('CPF inválido: deve conter exatamente 11 dígitos. Peça ao servidor para reenviar.');
        }

        if (! CpfValidator::isValid($cpf)) {
            return ToolResult::blocked('CPF inválido (dígitos verificadores incorretos). Peça ao servidor para conferir e reenviar.');
        }

        if ($this->lead->cpf === $cpf && ($this->lead->credito_json['niche'] ?? null) === 'siape') {
            return ToolResult::success(static::formatPayloadForAgent($this->lead->credito_json));
        }

        $threshold = config('credflow.circuit_breaker.consultas_falhas_threshold', 5);
        $circuitKey = "circuit_breaker_siape_{$this->lead->tenant_id}";
        if (Cache::get($circuitKey, 0) >= $threshold) {
            Log::warning('aria.tool.siape_circuit_breaker_open', ['lead_id' => $this->lead->id]);

            return ToolResult::error(
                'Sistema SIAPE temporariamente indisponível.',
                'Não tente chamar esta ferramenta novamente neste turno. Conduza a conversa sem valores precisos e ofereça acionar escalar_para_humano.'
            );
        }

        try {
            $rawData = $promosys->consultarSiape($cpf);

            if ($knownFailure = $this->formatKnownPromosysFailure($rawData)) {
                Cache::forget($circuitKey);

                return ToolResult::blocked($knownFailure, 'Não trate como instabilidade. Confirme os dados antes de tentar outro público.');
            }

            $consulta = $rawData['Consulta'][0] ?? [];
            if (empty($consulta) || empty($consulta['MATRICULA'])) {
                return ToolResult::blocked(
                    'A consulta SIAPE não retornou matrícula para este CPF.',
                    'Informe que o CPF não consta como servidor SIAPE ou peça para conferir os dados.'
                );
            }

            $userId = Auth::id();
            $rules = $userId
                ? AgentOperationalRule::forUser($userId)
                : AgentOperationalRule::forTenant($this->lead->tenant_id ?? 'default');

            $data = $this->qualify($consulta, $rules);

            $newStatus = match ($data['status'] ?? '') {
                'QUALIFICADO' => 'qualificado',
                'SEM_CREDITO' => 'sem_credito',
                'DESQUALIFICADO' => 'desqualificado',
                default => $this->lead->status,
            };

            $cliente = $data['cliente'] ?? [];
            $updateData = [
                'cpf' => $cpf,
                'nome' => $cliente['nome'] ?? $this->lead->nome,
                'idade' => $cliente['idade'] ?? $this->lead->idade,
                'credito_json' => $data,
                'status' => $newStatus,
                'last_interaction_at' => now(),
            ];

            if ($newStatus === 'qualificado') {
                $updateData['followup_status'] = app(FollowUpWindowService::class)
                    ->canSendFreeFormMessage($this->lead) ? 'active' : 'inactive';
                $updateData['followup_count'] = 0;
            }

            Cache::forget($circuitKey);
            $this->lead->update($updateData);

            return ToolResult::success(static::formatPayloadForAgent($data), data: [
                'niche' => 'siape',
                'status' => $data['status'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->incrementCircuitBreaker($circuitKey);

            Log::error('aria.tool.siape_promosys_error', [
                'lead_id' => $this->lead->id,
                'cpf' => substr($cpf, 0, 3).'***',
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(
                'Consulta SIAPE falhou por instabilidade ou timeout.',
                'Tente chamar consultar_credito_siape mais uma vez. Se falhar novamente, acione escalar_para_humano com motivo problema_tecnico.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @param  \Illuminate\Support\Collection<int, AgentOperationalRule>  $rules
     * @return array<string, mixed>
     */
    protected function qualify(array $rawData, $rules): array
    {
        return (new SiapeQualificacaoService($rules))->qualificar($rawData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatKnownPromosysFailure(array $data): ?string
    {
        $code = (string) ($data['Code'] ?? '');
        $message = trim((string) ($data['Msg'] ?? ''));

        if ($code === '' || $code === '000') {
            return null;
        }

        return $message !== ''
            ? "Consulta SIAPE informou: {$message}."
            : 'A consulta SIAPE não retornou dados para este CPF.';
    }

    /**
     * Format credito_json (SIAPE schema) as the exact text the agent receives from the tool.
     *
     * Supports two formats returned by n8n:
     * - Format A (top-level matricula): our internal SiapeQualificacaoService schema
     * - Format B (n8n SIAPE): cliente has matricula/orgao/situacao/remuneracao fields + beneficios array
     *
     * @param  array<string, mixed>  $data
     */
    public static function formatPayloadForAgent(array $data): string
    {
        $status = $data['status'] ?? 'DESCONHECIDO';
        $cliente = $data['cliente'] ?? [];
        $nome = $cliente['nome'] ?? 'Servidor';
        $idade = $cliente['idade'] ?? '?';
        $totais = $data['resumoGeral']['totais'] ?? [];

        $lines = [
            "CONSULTA SIAPE: {$status}",
            "Servidor: {$nome} ({$idade} anos)",
        ];

        // Format B: SIAPE fields inside cliente (n8n output)
        $orgao = $cliente['orgao'] ?? $data['matricula']['orgao'] ?? null;
        $matriculaCod = $cliente['matricula'] ?? $data['matricula']['codigo'] ?? null;
        $situacao = $cliente['situacao'] ?? $data['matricula']['situacaoFuncional'] ?? null;
        $remuneracao = (float) ($cliente['remuneracao'] ?? $data['matricula']['rendimentoLiquido'] ?? 0);

        if ($orgao) {
            $info = "Órgão: {$orgao}";
            if ($matriculaCod) {
                $info .= " | Matrícula: {$matriculaCod}";
            }
            if ($situacao) {
                $info .= " | {$situacao}";
            }
            $lines[] = $info;
        }

        if ($remuneracao > 0) {
            $lines[] = 'Remuneração líquida: '.self::brl($remuneracao);
        }

        if ($status === 'QUALIFICADO') {
            // Format B: produtos inside beneficios[0]
            $beneficio = $data['beneficios'][0] ?? [];
            $produtos = $beneficio['produtos'] ?? $data['produtos'] ?? [];

            if (($totais['margemLivre'] ?? 0) > 0) {
                $novo = $produtos['emprestimoNovo'] ?? [];
                $vl = self::brl($novo['valorLiberado'] ?? $totais['margemLivre']);
                $pc = self::brl($novo['parcelaMensal'] ?? 0);
                $lines[] = "Novo: {$vl} liberado — parcela {$pc}/mês";
            }
            if (($totais['refinanciamento'] ?? 0) > 0) {
                $lines[] = 'Refinanciamento: '.self::brl($totais['refinanciamento']).' de troco';
                $contratosRefin = $produtos['refinanciamento']['contratos'] ?? [];
                foreach ($contratosRefin as $c) {
                    $lines[] = '  → '.($c['banco'] ?? '').': parcela '.self::brl($c['valorParcela'] ?? 0).' libera '.self::brl($c['valorLiberado'] ?? 0);
                }
            }
            if (($totais['portabilidade'] ?? 0) > 0) {
                $lines[] = 'Portabilidade: '.self::brl($totais['portabilidade']).'/mês em parcelas elegíveis';
            }
            if (($totais['cartoes'] ?? 0) > 0) {
                $cartoes = $produtos['cartoes'] ?? [];
                $parts = [];
                foreach ($cartoes as $c) {
                    $parts[] = ($c['tipo'] ?? 'Cartão').': margem '.self::brl($c['margemMensal'] ?? 0).'/mês';
                }
                $lines[] = 'Cartões: '.implode(' | ', $parts);
            }

            $lines[] = 'Total estimado: '.self::brl($totais['totalEstimado'] ?? 0);
        } elseif ($status === 'DESQUALIFICADO') {
            // Show specific motivos if available (Format B)
            $motivos = $data['beneficios'][0]['qualificacao']['motivos'] ?? [];
            if (! empty($motivos)) {
                $lines[] = 'Motivo: '.implode('; ', $motivos);
            } else {
                $lines[] = $data['resumoGeral']['textoResumo'] ?? 'Servidor desqualificado.';
            }
        } else {
            $lines[] = 'Nenhum produto atingiu o limiar mínimo para este servidor.';
        }

        return implode("\n", $lines);
    }
}
