<?php

namespace App\Console\Commands;

use App\Models\AiUsageDaily;
use App\Services\AlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateAiUsageCommand extends Command
{
    protected $signature = 'credflow:aggregate-usage {--date= : Date to aggregate (Y-m-d), defaults to yesterday}';

    protected $description = 'Aggregate AI token usage from conversation messages into daily summaries.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? $this->option('date')
            : now()->subDay()->toDateString();

        $this->info("Aggregating AI usage for {$date}...");

        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        $messages = DB::table('agent_conversation_messages')
            ->join('leads', 'leads.conversation_id', '=', 'agent_conversation_messages.conversation_id')
            ->where('agent_conversation_messages.created_at', '>=', $dayStart)
            ->where('agent_conversation_messages.created_at', '<', $dayEnd)
            ->where('agent_conversation_messages.role', 'assistant')
            ->where('agent_conversation_messages.usage', '!=', '')
            ->select([
                'leads.tenant_id',
                'leads.agent_id',
                'agent_conversation_messages.agent',
                'agent_conversation_messages.usage',
                'agent_conversation_messages.meta',
            ])
            ->get();

        // Aggregate in PHP to avoid DB-specific JSON functions
        $groups = [];
        foreach ($messages as $msg) {
            $usage = json_decode($msg->usage, true) ?? [];
            $prompt = (int) ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0);

            if ($prompt === 0 && $completion === 0) {
                continue;
            }

            // Read actual model from meta (stored by the framework), fall back to agent class
            $meta = json_decode($msg->meta ?? '{}', true) ?? [];
            $model = $meta['model'] ?? $msg->agent;

            $key = "{$msg->tenant_id}|{$msg->agent_id}|{$model}";
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'tenant_id' => $msg->tenant_id,
                    'agent_id' => $msg->agent_id,
                    'model' => $model,
                    'requests' => 0,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                ];
            }

            $groups[$key]['requests']++;
            $groups[$key]['prompt_tokens'] += $prompt;
            $groups[$key]['completion_tokens'] += $completion;
        }

        $modelCosts = config('credflow.model_costs', []);
        $totalCost = 0.0;

        foreach ($groups as $group) {
            $cost = $this->estimateCost(
                $group['model'],
                $group['prompt_tokens'],
                $group['completion_tokens'],
                $modelCosts
            );

            AiUsageDaily::updateOrCreate(
                [
                    'date' => $date,
                    'tenant_id' => $group['tenant_id'],
                    'agent_id' => $group['agent_id'],
                    'model' => $group['model'],
                ],
                [
                    'total_requests' => $group['requests'],
                    'total_prompt_tokens' => $group['prompt_tokens'],
                    'total_completion_tokens' => $group['completion_tokens'],
                    'estimated_cost_usd' => $cost,
                ]
            );

            $totalCost += $cost;
        }

        $count = count($groups);
        $this->info("Aggregated {$count} groups. Total estimated cost: \${$totalCost}");

        Log::info('credflow:aggregate-usage completed', [
            'date' => $date,
            'groups' => $count,
            'total_cost_usd' => $totalCost,
        ]);

        $threshold = config('credflow.daily_cost_alert_threshold', 10);
        if ($totalCost > $threshold) {
            app(AlertService::class)->sendAlert(
                'AiCostSpike',
                "Alerta: custo de AI em {$date} foi \${$totalCost} (threshold: \${$threshold})",
                ['date' => $date, 'cost' => $totalCost, 'threshold' => $threshold]
            );
        }

        return Command::SUCCESS;
    }

    private function estimateCost(string $model, int $promptTokens, int $completionTokens, array $modelCosts): float
    {
        foreach ($modelCosts as $key => $rates) {
            if (str_contains(strtolower($model), strtolower($key))) {
                return ($promptTokens / 1000 * $rates['prompt']) + ($completionTokens / 1000 * $rates['completion']);
            }
        }

        // Default to Claude Haiku 4.5 rates (current production model)
        $default = $modelCosts['claude-haiku-4-5'] ?? ['prompt' => 0.0008, 'completion' => 0.004];

        return ($promptTokens / 1000 * $default['prompt']) + ($completionTokens / 1000 * $default['completion']);
    }
}
