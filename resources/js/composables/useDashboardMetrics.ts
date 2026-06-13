import { ref, onMounted, onBeforeUnmount, type Ref } from 'vue';
import echo from '@/echo';

export type DashboardSnapshot = {
    leads_today: number;
    leads_new_this_week: number;
    messages_sent_24h: number;
    messages_received_24h: number;
    campaigns_active: number;
    campaigns_paused: number;
    conversion_rate_7d: number;
    instance_statuses: Array<{ id: number; provider: string; status: string; quality_rating: string | null }>;
    follow_ups_pending: number;
    voice_calls_today: number;
};

export function useDashboardMetrics(tenantId: string, initial: DashboardSnapshot) {
    const metrics: Ref<DashboardSnapshot> = ref(initial);
    const isLive = ref(false);

    onMounted(() => {
        echo.private(`dashboard.${tenantId}`)
            .listen('.dashboard.metrics.updated', (e: { snapshot: DashboardSnapshot }) => {
                metrics.value = e.snapshot;
                isLive.value = true;
            });
    });

    onBeforeUnmount(() => {
        echo.leave(`dashboard.${tenantId}`);
    });

    return { metrics, isLive };
}
