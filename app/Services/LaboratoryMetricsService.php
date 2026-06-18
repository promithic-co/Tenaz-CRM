<?php

namespace App\Services;

use App\Http\Controllers\LaboratoryController;
use App\Models\AiRun;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Concerns\BelongsToTenant;
use App\Models\FailedInteraction;
use App\Models\FollowupMessage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LaboratoryMetricsService
{
    /**
     * Base failed-interaction query scoped to the Laboratory tenant string.
     *
     * The {@see Lead} relation bypasses every global scope because the Laboratory
     * tenant key (see {@see LaboratoryController::laboratoryTenantId})
     * differs from the value applied by {@see BelongsToTenant}.
     */
    private function failuresQuery(string $tenantId): Builder
    {
        return FailedInteraction::query()
            ->whereHas('lead', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId));
    }

    /**
     * @return array{pending_retries: int, retrying_now: int, resolved_today: int, escalated_open: int}
     */
    public function stats(string $tenantId): array
    {
        $failuresQuery = $this->failuresQuery($tenantId);

        return [
            'pending_retries' => (clone $failuresQuery)->where('status', 'pending')->count(),
            'retrying_now' => (clone $failuresQuery)->where('status', 'retrying')->count(),
            'resolved_today' => (clone $failuresQuery)->where('status', 'resolved')
                ->whereDate('resolved_at', today())->count(),
            'escalated_open' => (clone $failuresQuery)->where('status', 'escalated')->count(),
        ];
    }

    /**
     * @return Collection<int, FailedInteraction>
     */
    public function errorPatterns(string $tenantId): Collection
    {
        return $this->failuresQuery($tenantId)->select('error_tag', 'error_source')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(retry_count) as avg_retries')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('error_tag', 'error_source')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * @return array<int|string, int>
     */
    public function hourlyFailures(string $tenantId): array
    {
        $hourExpression = match (config('database.default')) {
            'sqlite' => "CAST(strftime('%H', created_at) AS INTEGER)",
            default => 'HOUR(created_at)',
        };

        return $this->failuresQuery($tenantId)->selectRaw("{$hourExpression} as hour")
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupByRaw("{$hourExpression}")
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();
    }

    /**
     * @return Collection<int, FailedInteraction>
     */
    public function recentFailures(string $tenantId): Collection
    {
        return $this->failuresQuery($tenantId)->with(['lead:id,nome,whatsapp', 'agent:id,name'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function recoveryRate(string $tenantId): float
    {
        $failuresQuery = $this->failuresQuery($tenantId);

        $totalFailures = (clone $failuresQuery)->where('created_at', '>=', now()->subDays(7))->count();
        $resolved = (clone $failuresQuery)->where('status', 'resolved')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $totalFailures > 0 ? round(($resolved / $totalFailures) * 100, 1) : 100.0;
    }

    /**
     * @return array{runs: int, avg_cost_usd: float, avg_latency_ms: int, p95_latency_ms: int, avg_llm_calls: float, avg_tool_calls: float, success_rate: float, fallback_rate: float, error_rate: float, human_handoff_rate: float}
     */
    public function aiRunSummary(string $tenantId): array
    {
        $runs = AiRun::query()
            ->where('tenant_id', $tenantId)
            ->where('started_at', '>=', now()->subDays(30))
            ->get();

        $count = $runs->count();
        if ($count === 0) {
            return [
                'runs' => 0,
                'avg_cost_usd' => 0.0,
                'avg_latency_ms' => 0,
                'p95_latency_ms' => 0,
                'avg_llm_calls' => 0.0,
                'avg_tool_calls' => 0.0,
                'success_rate' => 0.0,
                'fallback_rate' => 0.0,
                'error_rate' => 0.0,
                'human_handoff_rate' => 0.0,
            ];
        }

        return [
            'runs' => $count,
            'avg_cost_usd' => round((float) $runs->avg('estimated_cost_usd'), 6),
            'avg_latency_ms' => (int) round((float) $runs->avg('duration_ms')),
            'p95_latency_ms' => $this->percentile($runs->pluck('duration_ms')->filter()->values()->all(), 95),
            'avg_llm_calls' => round((float) $runs->avg('llm_calls'), 2),
            'avg_tool_calls' => round((float) $runs->avg('tool_calls'), 2),
            'success_rate' => $this->rate($runs->where('status', 'success')->count(), $count),
            'fallback_rate' => $this->rate($runs->where('status', 'fallback')->count(), $count),
            'error_rate' => $this->rate($runs->whereIn('status', ['error', 'timeout'])->count(), $count),
            'human_handoff_rate' => $this->rate($runs->where('status', 'human_handoff')->count(), $count),
        ];
    }

    /**
     * @return array<int, array{architecture_version: string, runs: int, avg_cost_usd: float, avg_latency_ms: int, p95_latency_ms: int, avg_llm_calls: float, avg_tool_calls: float, success_rate: float}>
     */
    public function architectureComparison(string $tenantId): array
    {
        return AiRun::query()
            ->where('tenant_id', $tenantId)
            ->where('started_at', '>=', now()->subDays(30))
            ->get()
            ->groupBy('architecture_version')
            ->map(function ($runs, string $architecture): array {
                $count = $runs->count();

                return [
                    'architecture_version' => $architecture,
                    'runs' => $count,
                    'avg_cost_usd' => round((float) $runs->avg('estimated_cost_usd'), 6),
                    'avg_latency_ms' => (int) round((float) $runs->avg('duration_ms')),
                    'p95_latency_ms' => $this->percentile($runs->pluck('duration_ms')->filter()->values()->all(), 95),
                    'avg_llm_calls' => round((float) $runs->avg('llm_calls'), 2),
                    'avg_tool_calls' => round((float) $runs->avg('tool_calls'), 2),
                    'success_rate' => $this->rate($runs->where('status', 'success')->count(), $count),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{active_count: int, paused_count: int, sent_today: int, failed_today: int, converted_from_followup: int}
     */
    public function followupStats(string $tenantId): array
    {
        return [
            'active_count' => Lead::withoutGlobalScope('tenant')
                ->where('is_sandbox', false)
                ->where('followup_status', 'active')
                ->where('tenant_id', $tenantId)
                ->count(),
            'paused_count' => Lead::withoutGlobalScope('tenant')
                ->where('is_sandbox', false)
                ->where('followup_status', 'paused')
                ->where('tenant_id', $tenantId)
                ->count(),
            'sent_today' => FollowupMessage::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereDate('sent_at', today())
                ->count(),
            'failed_today' => DB::table('failed_jobs')
                ->whereDate('failed_at', today())
                ->where('payload', 'like', '%ProcessLeadFollowUpJob%')
                ->count(),
            'converted_from_followup' => Lead::withoutGlobalScope('tenant')
                ->where('is_sandbox', false)
                ->where('tenant_id', $tenantId)
                ->where('status', 'convertido')
                ->whereHas(
                    'followupMessages',
                    fn ($q) => $q->withoutGlobalScope('tenant')->where('sent_at', '>=', now()->subDays(30)),
                )
                ->count(),
        ];
    }

    /**
     * @return array{campaigns_active: int, campaigns_completed_today: int, messages_sent_today: int, messages_delivered_today: int, messages_failed_today: int, delivery_rate_today: float|int, replies_from_campaigns_today: int, estimated_cost_today: float|int}
     */
    public function bulkMetrics(string $tenantId): array
    {
        $campaignForLaboratory = fn ($q) => $q->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);

        $sentToday = CampaignMessage::whereHas('campaign', $campaignForLaboratory)
            ->whereDate('created_at', today())
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->count();

        $deliveredToday = CampaignMessage::whereHas('campaign', $campaignForLaboratory)
            ->whereDate('delivered_at', today())
            ->count();

        $failedToday = CampaignMessage::whereHas('campaign', $campaignForLaboratory)
            ->whereDate('failed_at', today())
            ->count();

        return [
            'campaigns_active' => Campaign::withoutGlobalScope('tenant')->forTenant($tenantId)->where('status', 'sending')->count(),
            'campaigns_completed_today' => Campaign::withoutGlobalScope('tenant')->forTenant($tenantId)->where('status', 'completed')->whereDate('completed_at', today())->count(),
            'messages_sent_today' => $sentToday,
            'messages_delivered_today' => $deliveredToday,
            'messages_failed_today' => $failedToday,
            'delivery_rate_today' => $sentToday > 0 ? round($deliveredToday / $sentToday * 100, 1) : 0,
            'replies_from_campaigns_today' => Lead::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('modo', 'bulk')
                ->whereDate('created_at', today())
                ->count(),
            'estimated_cost_today' => $sentToday > 0 ? round($sentToday * 0.05, 2) : 0,
        ];
    }

    /**
     * @param  array<int, int|null>  $values
     */
    private function percentile(array $values, int $percentile): int
    {
        $values = array_values(array_filter(array_map(fn ($value) => $value === null ? null : (int) $value, $values)));
        sort($values);

        if ($values === []) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min($index, count($values) - 1))];
    }

    private function rate(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }
}
