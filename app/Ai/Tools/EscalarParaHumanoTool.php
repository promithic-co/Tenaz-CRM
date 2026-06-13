<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Services\HumanHandoffTransferService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class EscalarParaHumanoTool implements Tool
{
    private const REASON_CODES = ['proposta_aceita', 'solicitacao_cliente', 'problema_tecnico', 'outro'];

    public function __construct(private readonly Lead $lead) {}

    public function description(): Stringable|string
    {
        return 'Transfere atendimento para a fila de atendimento humano. Usar quando docs completos, proposta aceita ou cliente solicitar. Um atendente assumirá pela fila.';
    }

    public function handle(Request $request): Stringable|string
    {
        // Idempotency: active escalation ticket means handoff already exists.
        $existing = ServiceTicket::query()->activeEscalation($this->lead->id)->exists();
        if ($existing) {
            return ToolResult::alreadyDone('Atendimento já transferido para a fila. Não repita esta ação — um atendente assumirá em breve.');
        }

        $reason = $this->normalizeReason($request['motivo']);
        $summary = $this->buildSummary($request['motivo'], $request['resumo'] ?? '');

        try {
            app(HumanHandoffTransferService::class)->transferFromAi($this->lead, [
                'reason' => $reason['code'],
                'summary' => $summary,
                'chosen_product' => $request['produto_escolhido'] ?? null,
                'total_value' => $request['valor_total'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('aria.tool.escalar_erro', [
                'lead_id' => $this->lead->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(
                'Erro ao registrar transferência para a fila de atendimento.',
                'Informe ao cliente que um atendente da fila assumirá em breve e não tente acionar esta ferramenta novamente.'
            );
        }

        return ToolResult::success('Atendimento transferido para a fila. Um atendente assumirá em breve.');
    }

    /**
     * Normalize motivo to a valid reason code. If the value starts with a known code, use it; otherwise use 'outro'.
     * Returns ['code' => string, 'extra' => string] so extra text can be merged into summary.
     *
     * @return array{code: string, extra: string}
     */
    private function normalizeReason(string $motivo): array
    {
        $trimmed = trim($motivo);
        foreach (self::REASON_CODES as $code) {
            if (str_starts_with($trimmed, $code)) {
                $extra = trim(mb_substr($trimmed, strlen($code)));
                $extra = preg_replace('/^[\s\-_:]+/u', '', $extra);

                return ['code' => $code, 'extra' => $extra];
            }
        }

        return ['code' => 'outro', 'extra' => $trimmed];
    }

    /**
     * Build summary: resumo from agent, plus any extra text from motivo when motivo was not a plain code.
     */
    private function buildSummary(string $motivo, string $resumo): string
    {
        $normalized = $this->normalizeReason($motivo);
        $resumoTrimmed = trim($resumo);
        if ($normalized['extra'] === '') {
            return $resumoTrimmed;
        }
        if ($resumoTrimmed === '') {
            return $normalized['extra'];
        }

        return $normalized['extra']."\n\n".$resumoTrimmed;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'motivo' => $schema->string()
                ->description('Exatamente um dos valores: proposta_aceita, solicitacao_cliente, problema_tecnico, outro. Todo o contexto descritivo deve ir em resumo.')
                ->required(),
            'resumo' => $schema->string()
                ->description('Texto objetivo: produto escolhido, valor total, parcela, pendências e observações para o próximo atendente.')
                ->required(),
            'produto_escolhido' => $schema->string()
                ->description('Produto escolhido pelo cliente'),
            'valor_total' => $schema->string()
                ->description('Valor total, ex: "8856.00"'),
        ];
    }
}
