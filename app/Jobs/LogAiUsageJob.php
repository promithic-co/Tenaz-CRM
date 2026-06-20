<?php

namespace App\Jobs;

use App\Models\AiUsageDaily;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAiUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // tries=1 (REL-6): the upsert is ADDITIVE (total_requests + 1). A retry of a
    // succeeded-but-unacked job would over-count usage/cost telemetry, so do not retry.
    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $tokensIn,
        public int $tokensOut,
        public string $model,
        public ?int $agentId = null,
        public string|int|null $tenantId = null,
        public ?string $dateOverride = null
    ) {}

    public function handle(): void
    {
        $date = $this->dateOverride ?? Carbon::today()->toDateString();
        $cost = $this->calculateCost($this->model, $this->tokensIn, $this->tokensOut);
        $tenantKey = $this->tenantId !== null ? (string) $this->tenantId : '';

        AiUsageDaily::upsert([
            [
                'date' => $date,
                'tenant_id' => $tenantKey,
                'agent_id' => $this->agentId,
                'model' => $this->model,
                'total_requests' => 1,
                'total_prompt_tokens' => $this->tokensIn,
                'total_completion_tokens' => $this->tokensOut,
                'estimated_cost_usd' => $cost,
            ],
        ], ['date', 'tenant_id', 'agent_id', 'model'], [
            'total_requests' => DB::raw('ai_usage_dailies.total_requests + 1'),
            'total_prompt_tokens' => DB::raw('ai_usage_dailies.total_prompt_tokens + '.(int) $this->tokensIn),
            'total_completion_tokens' => DB::raw('ai_usage_dailies.total_completion_tokens + '.(int) $this->tokensOut),
            'estimated_cost_usd' => DB::raw('ai_usage_dailies.estimated_cost_usd + '.(float) $cost),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('LogAiUsageJob.failed', [
            'tenant_id' => $this->tenantId,
            'model' => $this->model,
            'error' => $e->getMessage(),
        ]);
    }

    private function calculateCost(string $model, int $in, int $out): float
    {
        $prices = [
            'gpt-4o' => ['in' => 0.005, 'out' => 0.015],
            'gpt-4o-mini' => ['in' => 0.00015, 'out' => 0.0006],
            'claude-3-5-sonnet' => ['in' => 0.003, 'out' => 0.015],
            'claude-3-haiku' => ['in' => 0.00025, 'out' => 0.00125],
        ];

        $rate = $prices[$model] ?? ['in' => 0, 'out' => 0];

        if (! isset($prices[$model])) {
            if (str_contains($model, 'gpt-4o-mini')) {
                $rate = $prices['gpt-4o-mini'];
            } elseif (str_contains($model, 'gpt-4o')) {
                $rate = $prices['gpt-4o'];
            } elseif (str_contains($model, 'sonnet')) {
                $rate = $prices['claude-3-5-sonnet'];
            } elseif (str_contains($model, 'haiku')) {
                $rate = $prices['claude-3-haiku'];
            }
        }

        return round((($in / 1000) * $rate['in']) + (($out / 1000) * $rate['out']), 6);
    }
}
