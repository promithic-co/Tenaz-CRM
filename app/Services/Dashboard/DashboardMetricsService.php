<?php

namespace App\Services\Dashboard;

use App\Jobs\ComputeDashboardMetricsJob;
use App\Models\Campaign;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\VoiceCampaignCall;
use App\Models\WhatsappInstance;
use App\Services\BroadcastDebouncer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(private BroadcastDebouncer $debouncer) {}

    /**
     * Return a KPI snapshot for the given tenant, cached for 5 seconds.
     *
     * @return array<string, mixed>
     */
    public function snapshot(string $tenantId): array
    {
        return Cache::remember("dashboard:snapshot:{$tenantId}", 5, function () use ($tenantId) {
            $leadsToday = Lead::forTenant($tenantId)->production()
                ->whereDate('created_at', today())
                ->count();

            $leadsNewThisWeek = Lead::forTenant($tenantId)->production()
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();

            $messagesSent24h = DB::table('campaign_messages')
                ->join('campaigns', 'campaign_messages.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.tenant_id', $tenantId)
                ->where('campaign_messages.status', 'sent')
                ->where('campaign_messages.sent_at', '>=', now()->subDay())
                ->count();

            $messagesReceived24h = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('is_sandbox', false)
                ->where('last_inbound_at', '>=', now()->subDay())
                ->whereNotNull('last_inbound_at')
                ->count();

            $campaignsActive = Campaign::where('tenant_id', $tenantId)
                ->where('status', 'sending')
                ->count();

            $campaignsPaused = Campaign::where('tenant_id', $tenantId)
                ->where('status', 'paused')
                ->count();

            // Conversion rate: escalados in last 7 days / leads created in last 7 days
            $leadsLast7d = Lead::forTenant($tenantId)->production()
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $escaladosLast7d = Lead::forTenant($tenantId)->production()
                ->where('status', 'escalado')
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();

            $conversionRate7d = $leadsLast7d > 0
                ? round(($escaladosLast7d / $leadsLast7d) * 100, 1)
                : 0.0;

            $instanceStatuses = WhatsappInstance::where('tenant_id', $tenantId)
                ->get(['id', 'provider', 'meta_quality_rating'])
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'provider' => $i->provider->value,
                    'status' => 'unknown',
                    'quality_rating' => $i->meta_quality_rating,
                ])
                ->values()
                ->all();

            $followUpsPending = Lead::forTenant($tenantId)->production()
                ->where('followup_status', 'active')
                ->count();

            $voiceCallsToday = VoiceCampaignCall::whereHas(
                'voiceCampaign',
                fn ($q) => $q->where('tenant_id', $tenantId)
            )
                ->whereDate('created_at', today())
                ->count();

            $atendimentos = $this->sessionCounters($tenantId);

            return [
                'leads_today' => $leadsToday,
                'leads_new_this_week' => $leadsNewThisWeek,
                'messages_sent_24h' => $messagesSent24h,
                'messages_received_24h' => $messagesReceived24h,
                'campaigns_active' => $campaignsActive,
                'campaigns_paused' => $campaignsPaused,
                'conversion_rate_7d' => $conversionRate7d,
                'instance_statuses' => $instanceStatuses,
                'follow_ups_pending' => $followUpsPending,
                'voice_calls_today' => $voiceCallsToday,
                'atendimentos' => $atendimentos,
            ];
        });
    }

    /**
     * Per-session (atendimento) KPIs: how many cycles opened / reengaged today, how
     * many are live now, and — over the last 7 days — how many closed, the outcome
     * breakdown, and the average minutes from open to close.
     *
     * @return array{opened_today: int, reengaged_today: int, open_now: int, closed_7d: int, avg_close_minutes: float, outcomes_7d: array<string, int>}
     */
    private function sessionCounters(string $tenantId): array
    {
        $base = fn () => ConversationSession::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $openedToday = (clone $base())->whereDate('opened_at', today())->count();

        $reengagedToday = (clone $base())
            ->whereDate('opened_at', today())
            ->whereIn('open_reason', ConversationSession::REENGAGEMENT_REASONS)
            ->count();

        $openNow = (clone $base())->where('status', ConversationSession::STATUS_OPEN)->count();

        $closedLast7d = (clone $base())
            ->where('status', ConversationSession::STATUS_CLOSED)
            ->where('closed_at', '>=', now()->subDays(7))
            ->get(['outcome', 'opened_at', 'closed_at']);

        $outcomes = $closedLast7d
            ->groupBy(fn (ConversationSession $s): string => (string) ($s->outcome ?? 'unknown'))
            ->map(fn ($group): int => $group->count())
            ->all();

        // Averaged in PHP (not SQL) for driver portability — epoch-diff syntax differs
        // between Postgres (prod) and SQLite (tests). 7d closed sessions per tenant is bounded.
        $durations = $closedLast7d
            ->filter(fn (ConversationSession $s): bool => $s->opened_at !== null && $s->closed_at !== null)
            ->map(fn (ConversationSession $s): float => $s->opened_at->diffInMinutes($s->closed_at, true));

        $avgCloseMinutes = $durations->isEmpty() ? 0.0 : round((float) $durations->avg(), 1);

        return [
            'opened_today' => $openedToday,
            'reengaged_today' => $reengagedToday,
            'open_now' => $openNow,
            'closed_7d' => $closedLast7d->count(),
            'avg_close_minutes' => $avgCloseMinutes,
            'outcomes_7d' => $outcomes,
        ];
    }

    /**
     * Debounce-offload the KPI broadcast (max once per 5s per tenant).
     *
     * The debounce gate stays on the caller (a cheap atomic Cache::add) so the
     * triggering send/inbound worker only enqueues a job; the ~10 tenant-wide
     * aggregate COUNTs run on the `default` queue, not the hot path (SCALE-3).
     */
    public function dispatchUpdate(string $tenantId): void
    {
        if ($this->debouncer->shouldFire("dashboard:{$tenantId}:metrics", 5)) {
            ComputeDashboardMetricsJob::dispatch($tenantId);
        }
    }
}
