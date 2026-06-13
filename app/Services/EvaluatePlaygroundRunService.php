<?php

namespace App\Services;

use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\EvaluatorAgent;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluatePlaygroundRunService
{
    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly DataFidelityValidator $fidelity,
    ) {}

    /**
     * Run the playground evaluator over a sandbox lead's transcript and append
     * the AVALIAÇÃO summary to the legacy conversation. Returns the report
     * envelope, or the 500 error envelope on failure.
     *
     * @param  array<int, array<string, mixed>>  $tokenMetrics
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function execute(Lead $lead, string $personaPrompt, array $tokenMetrics, ?string $evaluatorModel): array
    {
        $messages = $this->timeline->legacyMessages($lead);
        $transcript = '';
        foreach ($messages as $m) {
            $role = $m['role'] === 'user' ? 'Cliente' : 'Agente';
            $transcript .= "{$role}: {$m['content']}\n";
        }

        $formattedMetrics = $this->formatTokenMetrics($tokenMetrics);

        try {
            $credflow = new CredFlowAgent($lead);
            $ariaInstructions = $lead->sandbox_system_prompt ?: (string) $credflow->instructions();

            $fidelityReportText = null;
            if (! empty($lead->credito_json)) {
                $assistantText = collect($messages)
                    ->where('role', 'assistant')
                    ->pluck('content')
                    ->implode("\n\n");

                if (! empty($assistantText)) {
                    $fidelityReport = $this->fidelity->validate($assistantText, $lead->credito_json);
                    $fidelityReportText = $fidelityReport->toFormattedString();
                }
            }

            $evalProvider = $evaluatorModel && str_contains($evaluatorModel, '/') ? 'openrouter' : null;
            $evaluator = new EvaluatorAgent($transcript, $ariaInstructions, $personaPrompt, $formattedMetrics, $fidelityReportText, null, $evaluatorModel, $evalProvider);
            $response = $evaluator->prompt('Aja como Avaliador e produza o relatório Markdown final.');

            $report = (string) $response;

            $this->timeline->appendLegacyMessage($lead, 'assistant', "📝 **AVALIAÇÃO DA RODADA**\n\n".$report);

            return [
                'status' => 200,
                'payload' => ['report' => $report],
            ];
        } catch (Throwable $e) {
            Log::error('EVALUATE ERROR: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());

            return [
                'status' => 500,
                'payload' => ['error' => 'Erro na avaliação: '.$e->getMessage()],
            ];
        }
    }

    /**
     * Build the human-readable token-metrics block for the evaluator prompt.
     *
     * @param  array<int, array<string, mixed>>  $tokenMetrics
     */
    private function formatTokenMetrics(array $tokenMetrics): string
    {
        if (empty($tokenMetrics)) {
            return 'Nenhuma métrica de token coletada nesta rodada.';
        }

        $formattedMetrics = '';
        $totalIn = 0;
        $totalOut = 0;
        foreach ($tokenMetrics as $metric) {
            $tools = empty($metric['tool_calls']) ? 'Nenhuma' : implode(', ', $metric['tool_calls']);
            $formattedMetrics .= "- Rodada {$metric['round']}: {$metric['tokens_in']} In, {$metric['tokens_out']} Out | Tools: {$tools}\n";
            $totalIn += $metric['tokens_in'];
            $totalOut += $metric['tokens_out'];
        }
        $avgTotal = ($totalIn + $totalOut) / count($tokenMetrics);
        $formattedMetrics .= "\n**Resumo do Custo da Conversa:**\n";
        $formattedMetrics .= '- Total Gasto na Conversa: '.($totalIn + $totalOut)." tokens ({$totalIn} in, {$totalOut} out)\n";
        $formattedMetrics .= '- Média por Rodada: '.round($avgTotal)." tokens\n";

        return $formattedMetrics;
    }
}
