<?php

namespace App\Services;

use App\Models\AiRun;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class AiRunRecorder
{
    public function start(string $runId, Lead $lead, string $agentName, string $architectureVersion): AiRun
    {
        return AiRun::updateOrCreate(
            ['run_id' => $runId],
            [
                'trace_id' => $runId,
                'tenant_id' => (string) $lead->tenant_id,
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation_id,
                'agent_id' => $lead->agent_id,
                'agent_name' => $agentName,
                'architecture_version' => $architectureVersion,
                'started_at' => now(),
                'status' => 'success',
            ],
        );
    }

    public function recordModelCall(string $runId, ?string $model, int $inputTokens, int $outputTokens, string $promptHash): void
    {
        $cost = $this->estimateCost($model ?? 'unknown', $inputTokens, $outputTokens);

        AiRun::query()
            ->where('run_id', $runId)
            ->update([
                'model' => DB::raw($this->mergeModelExpression($model)),
                'prompt_hash' => $promptHash,
                'llm_calls' => DB::raw('llm_calls + 1'),
                'input_tokens' => DB::raw('input_tokens + '.max(0, $inputTokens)),
                'output_tokens' => DB::raw('output_tokens + '.max(0, $outputTokens)),
                'estimated_cost_usd' => DB::raw('estimated_cost_usd + '.$cost),
            ]);
    }

    public function recordToolCalls(string $runId, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        AiRun::query()
            ->where('run_id', $runId)
            ->increment('tool_calls', $count);
    }

    public function finish(string $runId, string $status, ?string $outcome = null, ?string $errorType = null): void
    {
        $run = AiRun::query()->where('run_id', $runId)->first();
        if (! $run) {
            return;
        }

        if ($run->ended_at !== null) {
            return;
        }

        $endedAt = now();

        $run->forceFill([
            'ended_at' => $endedAt,
            'duration_ms' => $run->started_at ? $run->started_at->diffInMilliseconds($endedAt) : null,
            'status' => $status,
            'outcome' => $outcome,
            'error_type' => $errorType,
        ])->save();
    }

    private function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        foreach (config('credflow.model_costs', []) as $key => $rates) {
            if (str_contains(strtolower($model), strtolower($key))) {
                return round(
                    ($inputTokens / 1000 * (float) $rates['prompt'])
                    + ($outputTokens / 1000 * (float) $rates['completion']),
                    6,
                );
            }
        }

        return 0.0;
    }

    private function mergeModelExpression(?string $model): string
    {
        $model = str_replace("'", "''", $model ?: 'unknown');

        return "CASE
            WHEN model IS NULL OR model = '' THEN '{$model}'
            WHEN model = '{$model}' THEN model
            WHEN instr(model, '{$model}') > 0 THEN model
            ELSE model || ',' || '{$model}'
        END";
    }
}
