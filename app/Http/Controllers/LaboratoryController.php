<?php

namespace App\Http\Controllers;

use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Models\AgentInteractionEvent;
use App\Models\AiUsageDaily;
use App\Models\CpfDataset;
use App\Models\Lead;
use App\Models\StressTestRun;
use App\Services\LaboratoryMetricsService;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LaboratoryController extends Controller
{
    public function index(LaboratoryMetricsService $metrics): Response
    {
        $tenantId = $this->laboratoryTenantId();

        return Inertia::render('laboratory/Index', [
            'stats' => $metrics->stats($tenantId),
            'errorPatterns' => $metrics->errorPatterns($tenantId),
            'hourlyFailures' => $metrics->hourlyFailures($tenantId),
            'recentFailures' => $metrics->recentFailures($tenantId),
            'recoveryRate' => $metrics->recoveryRate($tenantId),
            'followupStats' => $metrics->followupStats($tenantId),
            'bulkMetrics' => $metrics->bulkMetrics($tenantId),
            'externalLinks' => [
                'langfuse' => config('laboratory.langfuse.dashboard_url')
                    ?: rtrim(config('laboratory.langfuse.host'), '/'),
                'horizon' => url('/horizon'),
            ],
        ]);
    }

    public function datasets(): Response
    {
        $datasets = CpfDataset::where('user_id', Auth::id())
            ->withCount(['entries as preloaded_count' => fn ($q) => $q->whereNotNull('qualified_json')])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'description', 'total_entries', 'created_at'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'total_entries' => $d->total_entries,
                'preloaded_count' => $d->preloaded_count ?? 0,
                'created_at' => $d->created_at->toIso8601String(),
            ]);

        return Inertia::render('laboratory/Datasets', [
            'datasets' => $datasets,
        ]);
    }

    public function stressTest(): Response
    {
        $datasets = CpfDataset::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'total_entries'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'total_entries' => $d->total_entries]);

        $recentRuns = StressTestRun::where('user_id', Auth::id())
            ->with('cpfDataset:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label,
                'cpf_dataset' => $r->cpfDataset ? ['id' => $r->cpfDataset->id, 'name' => $r->cpfDataset->name] : null,
                'total_cycles' => $r->total_cycles,
                'completed_cycles' => $r->completed_cycles,
                'status' => $r->status,
                'results_summary' => $r->results_summary,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return Inertia::render('laboratory/StressTest', [
            'datasets' => $datasets,
            'recentRuns' => $recentRuns,
        ]);
    }

    public function stressTestResults(StressTestRun $run): Response
    {
        if ($run->user_id !== Auth::id()) {
            abort(403, 'Stress test run does not belong to you.');
        }

        $run->load([
            'cpfDataset:id,name',
            'cycles.lead' => fn ($q) => $q->withoutGlobalScope('tenant')->select(['id', 'credito_json']),
        ]);
        $runData = [
            'id' => $run->id,
            'label' => $run->label,
            'objective' => $run->objective,
            'cpf_dataset' => $run->cpfDataset ? ['id' => $run->cpfDataset->id, 'name' => $run->cpfDataset->name] : null,
            'config' => $run->config,
            'status' => $run->status,
            'total_cycles' => $run->total_cycles,
            'completed_cycles' => $run->completed_cycles,
            'results_summary' => $run->results_summary,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at->toIso8601String(),
            'cycles' => $run->cycles->map(function ($c) {
                $creditoJson = $c->lead?->credito_json;
                $payload = null;
                $groundTruth = null;
                if (! empty($creditoJson)) {
                    $payload = ConsultarCreditoInssTool::formatPayloadForAgent($creditoJson);
                    $groundTruth = ConsultarCreditoInssTool::buildGroundTruthSummary($creditoJson);
                }

                return [
                    'id' => $c->id,
                    'cycle_number' => $c->cycle_number,
                    'cpf_used' => $c->cpf_used,
                    'scenario' => $c->scenario,
                    'status' => $c->status,
                    'fidelity_score' => $c->fidelity_score !== null ? (float) $c->fidelity_score : null,
                    'hallucinations' => $c->hallucinations ?? [],
                    'token_metrics' => $c->token_metrics ?? [],
                    'evaluation_report' => $c->evaluation_report,
                    'completed_at' => $c->completed_at?->toIso8601String(),
                    'formatted_payload_for_agent' => $payload,
                    'ground_truth_summary' => $groundTruth,
                ];
            }),
        ];

        return Inertia::render('laboratory/StressTestResults', [
            'run' => $runData,
        ]);
    }

    public function aiUsage(): Response
    {
        $tenantId = $this->laboratoryTenantId();

        $dailyUsage = AiUsageDaily::where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->get();

        $byDay = $dailyUsage->groupBy('date')
            ->map(fn ($group) => [
                'date' => $group->first()->date,
                'requests' => $group->sum('total_requests'),
                'prompt_tokens' => $group->sum('total_prompt_tokens'),
                'completion_tokens' => $group->sum('total_completion_tokens'),
                'cost_usd' => round($group->sum('estimated_cost_usd'), 4),
            ])
            ->values();

        $byModel = $dailyUsage->groupBy('model')
            ->map(fn ($group, $model) => [
                'model' => $model,
                'requests' => $group->sum('total_requests'),
                'prompt_tokens' => $group->sum('total_prompt_tokens'),
                'completion_tokens' => $group->sum('total_completion_tokens'),
                'cost_usd' => round($group->sum('estimated_cost_usd'), 4),
            ])
            ->values();

        $totalMonth = [
            'requests' => $dailyUsage->sum('total_requests'),
            'prompt_tokens' => $dailyUsage->sum('total_prompt_tokens'),
            'completion_tokens' => $dailyUsage->sum('total_completion_tokens'),
            'cost_usd' => round($dailyUsage->sum('estimated_cost_usd'), 4),
        ];

        return Inertia::render('laboratory/AiUsage', [
            'dailyUsage' => $byDay,
            'byModel' => $byModel,
            'totalMonth' => $totalMonth,
        ]);
    }

    public function health(SystemHealthService $health): Response
    {
        return Inertia::render('laboratory/Health', [
            'checks' => [
                'database' => $health->database(),
                'cache' => $health->cache(),
                'queue' => $health->queue(),
                'disk' => $health->disk(),
            ],
            'horizon' => $health->horizon(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'checkedAt' => now()->toISOString(),
        ]);
    }

    public function interactionTimeline(string $interactionId): JsonResponse
    {
        $tenantId = $this->laboratoryTenantId();

        $events = AgentInteractionEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('interaction_id', $interactionId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (AgentInteractionEvent $event): array => [
                'id' => $event->id,
                'interaction_id' => $event->interaction_id,
                'tenant_id' => $event->tenant_id,
                'lead_id' => $event->lead_id,
                'agent_id' => $event->agent_id,
                'event_type' => $event->event_type,
                'event_source' => $event->event_source,
                'severity' => $event->severity,
                'payload' => $event->payload_json,
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'interaction_id' => $interactionId,
            'events' => $events,
        ]);
    }

    public function leadInteractionTimeline(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $events = AgentInteractionEvent::query()
            ->where('tenant_id', (string) $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (AgentInteractionEvent $event): array => [
                'id' => $event->id,
                'interaction_id' => $event->interaction_id,
                'event_type' => $event->event_type,
                'event_source' => $event->event_source,
                'severity' => $event->severity,
                'payload' => $event->payload_json,
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'lead_id' => $lead->id,
            'events' => $events,
        ]);
    }

    /**
     * Tenant key for Laboratory metrics: same string stored on {@see Lead::$tenant_id} / campaign rows.
     * Uses {@see \App\Models\User::tenantId} when the user belongs to a Tenant pivot; otherwise falls back
     * to {@see \App\Models\User::id} for legacy 1:1 data. Queries that mix this value with models using
     * {@see \App\Models\Concerns\BelongsToTenant} must call {@see \Illuminate\Database\Eloquent\Builder::withoutGlobalScope}
     * with `tenant`, because the global scope only applies {@see \App\Models\User::tenantId} (null without a pivot).
     */
    private function laboratoryTenantId(): string
    {
        $user = auth()->user();

        return (string) ($user?->tenantId ?? $user?->id ?? '');
    }
}
