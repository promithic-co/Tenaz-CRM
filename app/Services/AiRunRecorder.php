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
        $modelName = trim($model ?: 'unknown');
        $cost = $this->estimateCost($modelName, $inputTokens, $outputTokens);

        DB::transaction(function () use ($runId, $modelName, $inputTokens, $outputTokens, $promptHash, $cost): void {
            $run = AiRun::query()
                ->where('run_id', $runId)
                ->lockForUpdate()
                ->first();

            if (! $run) {
                return;
            }

            $models = array_values(array_filter(array_map('trim', explode(',', (string) $run->model))));
            if (! in_array($modelName, $models, true)) {
                $models[] = $modelName;
            }

            $run->forceFill([
                'model' => implode(',', $models),
                'prompt_hash' => $promptHash,
                'llm_calls' => $run->llm_calls + 1,
                'input_tokens' => $run->input_tokens + max(0, $inputTokens),
                'output_tokens' => $run->output_tokens + max(0, $outputTokens),
                'estimated_cost_usd' => round((float) $run->estimated_cost_usd + $cost, 6),
            ])->save();
        });
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
        $durationMs = $run->started_at
            ? max(0, (int) round($run->started_at->diffInMilliseconds($endedAt)))
            : null;

        $run->forceFill([
            'ended_at' => $endedAt,
            'duration_ms' => $durationMs,
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
}
