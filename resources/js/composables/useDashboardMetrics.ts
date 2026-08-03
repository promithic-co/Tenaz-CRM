import { ref, onMounted, onBeforeUnmount  } from 'vue';
import type {Ref} from 'vue';
import type { MetaHealthStatus } from '@/composables/useMetaHealth';
import echo from '@/echo';

export type DashboardSnapshot = {
    leads_today: number;
    leads_new_this_week: number;
    messages_sent_24h: number;
    messages_received_24h: number;
    campaigns_active: number;
    campaigns_paused: number;
    conversion_rate_7d: number;
    instance_statuses: Array<{
        id: number;
        label: string;
        provider: string;
        status: string;
        health_status: MetaHealthStatus;
        health_reasons: string[];
        health_checked_at: string | null;
        quality_rating: string | null;
    }>;
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
