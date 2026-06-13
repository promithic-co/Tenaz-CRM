<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardMetricsService $metrics): Response
    {
        $tenantId = (string) auth()->user()->tenantId;

        if (! $tenantId) {
            return Inertia::render('Dashboard', [
                'snapshot' => null,
                'tenantId' => null,
                'stats' => [
                    'total' => 0, 'hoje' => 0, 'novos_ontem' => 0, 'followups' => 0,
                    'qualificados' => 0, 'escalados' => 0, 'qualificados_semana' => 0,
                    'escalados_semana' => 0, 'por_status' => [],
                    'funnel' => [
                        ['stage' => 'Total', 'label' => 'Leads Totais', 'count' => 0, 'color' => 'bg-blue-500'],
                        ['stage' => 'qualificado', 'label' => 'Qualificados', 'count' => 0, 'color' => 'bg-emerald-500'],
                        ['stage' => 'escalado', 'label' => 'Conversões', 'count' => 0, 'color' => 'bg-purple-500'],
                    ],
                ],
                'leads_recentes' => [],
            ]);
        }

        $snapshot = $metrics->snapshot($tenantId);

        $stats = Cache::remember("dashboard_stats_{$tenantId}", 60, function () use ($tenantId) {
            $baseQuery = fn () => Lead::forTenant($tenantId)->production();

            $aggregate = $baseQuery()
                ->selectRaw('count(*) as total')
                ->selectRaw('count(case when created_at >= ? then 1 end) as hoje', [today()])
                ->selectRaw('count(case when created_at >= ? and created_at < ? then 1 end) as novos_ontem', [
                    now()->subDay()->startOfDay(),
                    today(),
                ])
                ->selectRaw("count(case when followup_status = 'active' then 1 end) as followups")
                ->selectRaw("count(case when status = 'qualificado' and updated_at >= ? then 1 end) as qualificados_semana", [
                    now()->subDays(7),
                ])
                ->selectRaw("count(case when status = 'escalado' and updated_at >= ? then 1 end) as escalados_semana", [
                    now()->subDays(7),
                ])
                ->first();

            $porStatus = $baseQuery()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $total = $aggregate->total;

            return [
                'total' => $total,
                'hoje' => $aggregate->hoje,
                'por_status' => $porStatus,
                'followups' => $aggregate->followups,
                'qualificados' => $porStatus['qualificado'] ?? 0,
                'escalados' => $porStatus['escalado'] ?? 0,
                'novos_ontem' => $aggregate->novos_ontem,
                'qualificados_semana' => $aggregate->qualificados_semana,
                'escalados_semana' => $aggregate->escalados_semana,
                'funnel' => [
                    ['stage' => 'Total', 'label' => 'Leads Totais', 'count' => $total, 'color' => 'bg-blue-500'],
                    ['stage' => 'qualificado', 'label' => 'Qualificados', 'count' => $porStatus['qualificado'] ?? 0, 'color' => 'bg-emerald-500'],
                    ['stage' => 'escalado', 'label' => 'Conversões', 'count' => $porStatus['escalado'] ?? 0, 'color' => 'bg-purple-500'],
                ],
            ];
        });

        $leads_recentes = Lead::forTenant($tenantId)->production()
            ->with('agent:id,name')
            ->latest()
            ->limit(8)
            ->get(['id', 'agent_id', 'nome', 'whatsapp', 'status', 'followup_status', 'followup_count', 'created_at', 'last_interaction_at'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'nome' => $l->nome ?? $l->whatsapp,
                'whatsapp' => $l->whatsapp,
                'status' => $l->status,
                'criado_em' => $l->created_at?->diffForHumans(),
                'ultima_interacao' => $l->last_interaction_at?->diffForHumans(),
                'agent_name' => $l->agent?->name,
                'followup_status' => $l->followup_status,
                'followup_count' => $l->followup_count,
            ]);

        return Inertia::render('Dashboard', compact('snapshot', 'tenantId', 'stats', 'leads_recentes'));
    }
}
