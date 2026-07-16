<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    Users,
    TrendingUp,
    TrendingDown,
    CheckCircle2,
    UserCheck,
    MessageSquare,
    ArrowRight,
    Activity,
} from 'lucide-vue-next';
import StatusBadge from '@/components/StatusBadge.vue';
import { useDashboardMetrics } from '@/composables/useDashboardMetrics';
import type { DashboardSnapshot } from '@/composables/useDashboardMetrics';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import conversas from '@/routes/conversas';
import type { BreadcrumbItem } from '@/types';

type StatusKey =
    | 'novo'
    | 'qualificado'
    | 'sem_credito'
    | 'desqualificado'
    | 'escalado'
    | 'convertido'
    | 'optou_sair';

type FunnelStage = {
    stage: string;
    label: string;
    count: number;
    color: string;
};

type Props = {
    snapshot: DashboardSnapshot | null;
    tenantId: string | null;
    stats: {
        total: number;
        hoje: number;
        followups: number;
        escalados: number;
        qualificados: number;
        por_status: Record<string, number>;
        novos_ontem: number;
        qualificados_semana: number;
        escalados_semana: number;
        funnel: FunnelStage[];
    };
    leads_recentes: Array<{
        id: number;
        nome: string;
        whatsapp: string;
        status: string;
        criado_em: string | null;
        ultima_interacao: string | null;
        agent_name: string | null;
        followup_status: string | null;
        followup_count: number | null;
    }>;
};

const props = defineProps<Props>();

const emptySnapshot: DashboardSnapshot = {
    leads_today: 0,
    leads_new_this_week: 0,
    messages_sent_24h: 0,
    messages_received_24h: 0,
    campaigns_active: 0,
    campaigns_paused: 0,
    conversion_rate_7d: 0,
    instance_statuses: [],
    follow_ups_pending: 0,
    voice_calls_today: 0,
};
const { metrics, isLive } = useDashboardMetrics(
    props.tenantId ?? '',
    props.snapshot ?? emptySnapshot,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const statusLabels: Partial<Record<StatusKey, string>> = {
    novo: 'Novo',
    qualificado: 'Qualificado',
    sem_credito: 'Sem Crédito',
    desqualificado: 'Desqualificado',
    escalado: 'Escalado',
    optou_sair: 'Optou por Sair',
};

const statusColors: Record<StatusKey, string> = {
    novo: 'bg-slate-400 dark:bg-slate-500',
    qualificado: 'bg-emerald-500',
    sem_credito: 'bg-amber-400',
    desqualificado: 'bg-orange-500',
    escalado: 'bg-blue-500',
    convertido: 'bg-purple-500',
    optou_sair: 'bg-red-500',
};

function barWidth(key: string): string {
    const val = props.stats.por_status[key] ?? 0;
    const max = Math.max(...Object.values(props.stats.por_status ?? {}), 1);
    return `${Math.round((val / max) * 100)}%`;
}

function funnelHeight(count: number, total: number): string {
    if (total === 0) return '8px';
    const pct = Math.max(0.05, count / total);
    return `${Math.round(pct * 80)}px`;
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-3 sm:p-4 lg:p-8">
            <!-- ── KPI Cards ─────────────────────────────────────────────────── -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6"
            >
                <!-- Total Leads -->
                <div
                    class="group relative overflow-hidden rounded-2xl border border-sidebar-border bg-card p-6 shadow-sm transition-all hover:shadow-md dark:border-sidebar-border/60"
                >
                    <div
                        class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-primary/5 transition-transform group-hover:scale-150"
                    ></div>
                    <div class="relative flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <Users class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Total de Leads
                            </p>
                            <p
                                class="mt-0.5 text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ stats.total }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Novos Hoje -->
                <div
                    class="group relative overflow-hidden rounded-2xl border border-sidebar-border bg-card p-6 shadow-sm transition-all hover:shadow-md dark:border-sidebar-border/60"
                >
                    <div
                        class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-blue-500/5 transition-transform group-hover:scale-150"
                    ></div>
                    <div class="relative flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500"
                        >
                            <TrendingUp class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Novos Hoje
                            </p>
                            <p
                                class="mt-0.5 text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ stats.hoje }}
                            </p>
                            <div class="mt-1 flex items-center gap-1 text-xs">
                                <component
                                    :is="
                                        stats.hoje >= stats.novos_ontem
                                            ? TrendingUp
                                            : TrendingDown
                                    "
                                    :class="
                                        stats.hoje >= stats.novos_ontem
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                    class="h-3 w-3"
                                />
                                <span
                                    :class="
                                        stats.hoje >= stats.novos_ontem
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                >
                                    {{
                                        stats.hoje >= stats.novos_ontem
                                            ? '+'
                                            : ''
                                    }}{{ stats.hoje - stats.novos_ontem }} vs
                                    ontem
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Qualificados -->
                <div
                    class="group relative overflow-hidden rounded-2xl border border-sidebar-border bg-card p-6 shadow-sm transition-all hover:shadow-md dark:border-sidebar-border/60"
                >
                    <div
                        class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-emerald-500/5 transition-transform group-hover:scale-150"
                    ></div>
                    <div class="relative flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                        >
                            <UserCheck class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Qualificados
                            </p>
                            <p
                                class="mt-0.5 text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ stats.qualificados }}
                            </p>
                            <div class="mt-1 flex items-center gap-1 text-xs">
                                <component
                                    :is="
                                        stats.qualificados_semana > 0
                                            ? TrendingUp
                                            : TrendingDown
                                    "
                                    :class="
                                        stats.qualificados_semana > 0
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                    class="h-3 w-3"
                                />
                                <span
                                    :class="
                                        stats.qualificados_semana > 0
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                >
                                    {{ stats.qualificados_semana }} esta semana
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Live Metrics Strip ────────────────────────────────────────── -->
            <div
                class="rounded-2xl border border-sidebar-border bg-card px-6 py-4 shadow-sm dark:border-sidebar-border/60"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold tracking-tight text-foreground"
                    >
                        <Activity class="h-4 w-4 text-primary" />
                        Métricas em Tempo Real
                    </h2>
                    <span
                        v-if="isLive"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                        />
                        Live
                    </span>
                </div>
                <div
                    class="grid grid-cols-1 gap-3 min-[400px]:grid-cols-2 sm:grid-cols-3 lg:grid-cols-5"
                >
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground"
                            >Msgs Enviadas 24h</span
                        >
                        <span class="text-lg font-bold tabular-nums">{{
                            metrics.messages_sent_24h
                        }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground"
                            >Msgs Recebidas 24h</span
                        >
                        <span class="text-lg font-bold tabular-nums">{{
                            metrics.messages_received_24h
                        }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground"
                            >Campanhas Ativas</span
                        >
                        <span class="text-lg font-bold tabular-nums">{{
                            metrics.campaigns_active
                        }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground"
                            >Taxa Conv. 7d</span
                        >
                        <span class="text-lg font-bold tabular-nums"
                            >{{ metrics.conversion_rate_7d }}%</span
                        >
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground"
                            >Ligações Hoje</span
                        >
                        <span class="text-lg font-bold tabular-nums">{{
                            metrics.voice_calls_today
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Body Area ──────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Pipeline Visual -->
                <div
                    class="flex flex-col rounded-2xl border border-sidebar-border bg-card shadow-sm lg:col-span-2 dark:border-sidebar-border/60"
                >
                    <div
                        class="border-b border-sidebar-border px-6 py-5 dark:border-sidebar-border/60"
                    >
                        <h2
                            class="text-base font-semibold tracking-tight text-foreground"
                        >
                            Pipeline de Leads
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Distribuição atual dos leads por status
                        </p>
                    </div>
                    <div class="flex-1 p-6">
                        <div class="space-y-5">
                            <div
                                v-for="(label, key) in statusLabels"
                                :key="key"
                                class="flex items-center gap-4"
                            >
                                <span
                                    class="w-36 shrink-0 text-sm font-medium text-muted-foreground"
                                    >{{ label }}</span
                                >
                                <div
                                    class="h-3 flex-1 overflow-hidden rounded-full bg-muted/50 dark:bg-muted/20"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500 ease-out"
                                        :class="statusColors[key as StatusKey]"
                                        :style="{ width: barWidth(key) }"
                                    />
                                </div>
                                <span
                                    class="w-10 text-right text-sm font-semibold text-foreground tabular-nums"
                                >
                                    {{ stats.por_status[key] ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <!-- Conversion Funnel -->
                        <div class="mt-6">
                            <h3 class="mb-1 text-sm font-semibold">
                                Funil de Conversão
                            </h3>
                            <p class="mb-4 text-xs text-muted-foreground">
                                De leads novos até escalamento para vendedor
                            </p>
                            <div class="flex items-end gap-1">
                                <div
                                    v-for="(stage, index) in stats.funnel"
                                    :key="stage.stage"
                                    class="flex flex-1 flex-col items-center gap-2"
                                >
                                    <div
                                        class="flex w-full flex-col items-center"
                                    >
                                        <span class="mb-1 text-sm font-bold">{{
                                            stage.count
                                        }}</span>
                                        <div
                                            class="w-full rounded-sm transition-all"
                                            :class="stage.color"
                                            :style="{
                                                height: funnelHeight(
                                                    stage.count,
                                                    stats.funnel[0].count,
                                                ),
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="text-center text-xs leading-tight text-muted-foreground"
                                        >{{ stage.label }}</span
                                    >
                                    <span
                                        v-if="
                                            index > 0 &&
                                            stats.funnel[0].count > 0
                                        "
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            Math.round(
                                                (stage.count /
                                                    stats.funnel[0].count) *
                                                    100,
                                            )
                                        }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Painel Lateral de Atendimento -->
                <div class="flex flex-col gap-6">
                    <div
                        class="relative overflow-hidden rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 shadow-sm dark:border-amber-500/10"
                    >
                        <div
                            class="absolute -top-6 -right-6 h-32 w-32 rounded-full bg-amber-500/10 blur-2xl"
                        ></div>
                        <div class="relative">
                            <h2
                                class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-500"
                            >
                                <MessageSquare class="h-4 w-4" /> Follow-ups
                                Ativos
                            </h2>
                            <p
                                class="text-5xl font-black tracking-tight text-foreground"
                            >
                                {{ stats.followups }}
                            </p>
                            <p
                                class="mt-2 text-sm font-medium text-muted-foreground"
                            >
                                Leads na fila aguardando retomada de conversa
                            </p>
                        </div>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-2xl border border-purple-500/20 bg-purple-500/5 p-6 shadow-sm dark:border-purple-500/10"
                    >
                        <div
                            class="absolute -top-6 -right-6 h-32 w-32 rounded-full bg-purple-500/10 blur-2xl"
                        ></div>
                        <div class="relative">
                            <h2
                                class="mb-2 flex items-center gap-2 text-sm font-semibold text-purple-700 dark:text-purple-500"
                            >
                                <CheckCircle2 class="h-4 w-4" /> Conversões
                            </h2>
                            <p
                                class="text-5xl font-black tracking-tight text-foreground"
                            >
                                {{ stats.escalados }}
                            </p>
                            <p
                                class="mt-2 text-sm font-medium text-muted-foreground"
                            >
                                Leads transferidos para vendedor humano
                            </p>
                            <div class="mt-2 flex items-center gap-1 text-xs">
                                <component
                                    :is="
                                        stats.escalados_semana > 0
                                            ? TrendingUp
                                            : TrendingDown
                                    "
                                    :class="
                                        stats.escalados_semana > 0
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                    class="h-3 w-3"
                                />
                                <span
                                    :class="
                                        stats.escalados_semana > 0
                                            ? 'text-emerald-400'
                                            : 'text-red-400'
                                    "
                                >
                                    {{ stats.escalados_semana }} esta semana
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Últimas Interações ─────────────────────────────────────────── -->
            <div
                class="rounded-2xl border border-sidebar-border bg-card shadow-sm dark:border-sidebar-border/60"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border px-6 py-5 dark:border-sidebar-border/60"
                >
                    <div>
                        <h2
                            class="text-base font-semibold tracking-tight text-foreground"
                        >
                            Últimas Interações
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Leads que tiveram troca de mensagem recentemente
                        </p>
                    </div>
                    <Link
                        href="/conversas"
                        class="group flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary/80"
                    >
                        Ver todas
                        <ArrowRight
                            class="h-4 w-4 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </div>

                <div
                    class="divide-y divide-sidebar-border/50 dark:divide-sidebar-border/40"
                >
                    <Link
                        v-for="lead in leads_recentes"
                        :key="lead.id"
                        :href="conversas.show(lead.id).url"
                        class="flex cursor-pointer flex-col justify-between gap-4 px-6 py-4 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center"
                    >
                        <div class="flex items-center gap-4">
                            <!-- Avatar Circle com cor baseada na primeira letra -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold text-primary shadow-inner"
                            >
                                {{ lead.nome[0]?.toUpperCase() ?? '?' }}
                            </div>
                            <div>
                                <p class="text-sm font-bold text-foreground">
                                    {{ lead.nome }}
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-sm font-medium text-muted-foreground/80"
                                >
                                    {{ lead.whatsapp }}
                                </p>
                                <p
                                    v-if="lead.agent_name"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ lead.agent_name }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex w-full flex-wrap items-center justify-between gap-4 sm:w-auto sm:justify-end"
                        >
                            <span
                                v-if="lead.followup_status === 'active'"
                                class="text-xs font-medium whitespace-nowrap text-amber-600 dark:text-amber-400"
                            >
                                Follow-up {{ lead.followup_count }}
                            </span>
                            <StatusBadge :status="lead.status" />
                            <span
                                class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                            >
                                {{ lead.ultima_interacao ?? lead.criado_em }}
                            </span>
                        </div>
                    </Link>

                    <div
                        v-if="!leads_recentes.length"
                        class="flex flex-col items-center justify-center px-6 py-12 text-center"
                    >
                        <div
                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted/50 text-muted-foreground/50"
                        >
                            <MessageSquare class="h-6 w-6" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Ainda não há interações.
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            As últimas conversas aparecerão aqui.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
