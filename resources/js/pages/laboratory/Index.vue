<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart2,
    CheckCircle,
    Clock,
    DollarSign,
    ExternalLink,
    FlaskConical,
    Gauge,
    Megaphone,
    MessageSquare,
    PauseCircle,
    RefreshCw,
    Reply,
    Send,
    ShieldAlert,
    Timer,
    TrendingUp,
    Trophy,
    Users,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { laboratory as laboratoryIndex } from '@/routes';
import campanhas from '@/routes/campanhas';
import conversas from '@/routes/conversas';
import laboratory from '@/routes/laboratory';
import type { BreadcrumbItem } from '@/types';

type Stats = {
    pending_retries: number;
    retrying_now: number;
    resolved_today: number;
    escalated_open: number;
};

type ErrorPattern = {
    error_tag: string;
    error_source: string;
    count: number;
    avg_retries: number;
};

type RecentFailure = {
    id: number;
    error_tag: string;
    error_source: string;
    status: string;
    retry_count: number;
    next_retry_at: string | null;
    lead: { id: number; nome: string; whatsapp: string } | null;
    agent: { id: number; name: string } | null;
    created_at: string;
};

type FollowupStats = {
    active_count: number;
    paused_count: number;
    sent_today: number;
    failed_today: number;
    converted_from_followup: number;
};

type BulkMetrics = {
    campaigns_active: number;
    campaigns_completed_today: number;
    messages_sent_today: number;
    messages_delivered_today: number;
    messages_failed_today: number;
    delivery_rate_today: number;
    replies_from_campaigns_today: number;
    estimated_cost_today_usd: number;
};

type AiRunSummary = {
    runs: number;
    avg_cost_usd: number;
    avg_latency_ms: number;
    p95_latency_ms: number;
    avg_llm_calls: number;
    avg_tool_calls: number;
    success_rate: number;
    fallback_rate: number;
    error_rate: number;
    human_handoff_rate: number;
};

type ArchitectureComparison = {
    architecture_version: string;
    runs: number;
    avg_cost_usd: number;
    avg_latency_ms: number;
    p95_latency_ms: number;
    avg_llm_calls: number;
    avg_tool_calls: number;
    success_rate: number;
};

type OperationalPosture = {
    status: 'attention' | 'stable';
    label: string;
    note: string;
};

type Props = {
    stats: Stats;
    errorPatterns: ErrorPattern[];
    hourlyFailures: Record<number, number>;
    recentFailures: RecentFailure[];
    recoveryRate: number;
    aiRunSummary: AiRunSummary;
    architectureComparison: ArchitectureComparison[];
    followupStats: FollowupStats;
    bulkMetrics: BulkMetrics;
    operationalPosture: OperationalPosture;
    externalLinks: { langfuse: string; horizon: string };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: laboratoryIndex() },
];
const hours = Array.from({ length: 24 }, (_, i) => i);

const maxHourlyCount = computed(() =>
    Math.max(...Object.values(props.hourlyFailures), 1),
);
const openRecoveryWork = computed(
    () =>
        props.stats.pending_retries +
        props.stats.retrying_now +
        props.stats.escalated_open,
);
const operationalFailuresToday = computed(
    () =>
        props.followupStats.failed_today +
        props.bulkMetrics.messages_failed_today,
);
const posture = computed(() => {
    const isAttention = props.operationalPosture.status === 'attention';

    return {
        label: props.operationalPosture.label,
        tone: isAttention
            ? 'text-amber-700 dark:text-amber-400'
            : 'text-emerald-700 dark:text-emerald-400',
        bg: isAttention ? 'bg-amber-500/10' : 'bg-emerald-500/10',
        icon: isAttention ? ShieldAlert : CheckCircle,
        note: props.operationalPosture.note,
    };
});

const navigationItems = [
    { label: 'AI Usage', href: laboratory.aiUsage.url() },
    { label: 'Stress Test', href: laboratory.stressTest.url() },
    { label: 'Datasets', href: laboratory.datasets.url() },
    { label: 'Health', href: laboratory.health.url() },
];

const aiMetrics = computed(() => [
    {
        label: 'AI Runs',
        value: props.aiRunSummary.runs.toLocaleString('pt-BR'),
        detail: 'últimos 30 dias',
        icon: Zap,
    },
    {
        label: 'Sucesso',
        value: `${props.aiRunSummary.success_rate}%`,
        detail: `erro ${props.aiRunSummary.error_rate}%`,
        icon: CheckCircle,
    },
    {
        label: 'Custo médio',
        value: formatUsd(props.aiRunSummary.avg_cost_usd),
        detail: 'por run',
        icon: DollarSign,
    },
    {
        label: 'Latência média',
        value: formatMs(props.aiRunSummary.avg_latency_ms),
        detail: `p95 ${formatMs(props.aiRunSummary.p95_latency_ms)}`,
        icon: Timer,
    },
]);

const recoveryMetrics = computed(() => [
    {
        label: 'Recovery rate',
        value: `${props.recoveryRate}%`,
        detail: 'últimos 7 dias',
        icon: TrendingUp,
    },
    {
        label: 'Pendentes',
        value: props.stats.pending_retries,
        detail: 'aguardando retry',
        icon: Clock,
    },
    {
        label: 'Em retry',
        value: props.stats.retrying_now,
        detail: 'processando agora',
        icon: RefreshCw,
    },
    {
        label: 'Escalados',
        value: props.stats.escalated_open,
        detail: 'abertos',
        icon: AlertTriangle,
    },
]);

const followupMetrics = computed(() => [
    {
        label: 'Ativos',
        value: props.followupStats.active_count,
        detail: 'leads em sequência',
        icon: Users,
    },
    {
        label: 'Pausados',
        value: props.followupStats.paused_count,
        detail: 'temporariamente parados',
        icon: PauseCircle,
    },
    {
        label: 'Enviados hoje',
        value: props.followupStats.sent_today,
        detail: 'mensagens enviadas',
        icon: Send,
    },
    {
        label: 'Falhas hoje',
        value: props.followupStats.failed_today,
        detail: 'jobs com falha',
        icon: XCircle,
    },
    {
        label: 'Convertidos 30d',
        value: props.followupStats.converted_from_followup,
        detail: 'via follow-up',
        icon: Trophy,
    },
]);

const campaignMetrics = computed(() => [
    {
        label: 'Campanhas ativas',
        value: props.bulkMetrics.campaigns_active,
        detail: 'enviando agora',
        icon: Megaphone,
        href: campanhas.index.url({ query: { status: 'sending' } }),
    },
    {
        label: 'Enviadas hoje',
        value: props.bulkMetrics.messages_sent_today,
        detail: 'mensagens',
        icon: MessageSquare,
    },
    {
        label: 'Entrega hoje',
        value: `${props.bulkMetrics.delivery_rate_today}%`,
        detail: `${props.bulkMetrics.messages_delivered_today} entregues`,
        icon: BarChart2,
    },
    {
        label: 'Respostas',
        value: props.bulkMetrics.replies_from_campaigns_today,
        detail: 'de campanhas hoje',
        icon: Reply,
    },
    {
        label: 'Falhas hoje',
        value: props.bulkMetrics.messages_failed_today,
        detail: 'mensagens com falha',
        icon: XCircle,
    },
    {
        label: 'Custo estimado',
        value: formatUsd(props.bulkMetrics.estimated_cost_today_usd),
        detail: 'hoje',
        icon: DollarSign,
    },
]);

function statusColor(status: string): string {
    const map: Record<string, string> = {
        pending: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        retrying: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        resolved: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        escalated: 'bg-red-500/10 text-red-700 dark:text-red-400',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
}

function tagColor(tag: string): string {
    const map: Record<string, string> = {
        timeout: 'bg-orange-500/10 text-orange-700 dark:text-orange-400',
        rate_limit: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        context_overflow:
            'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400',
        connection_error: 'bg-red-500/10 text-red-700 dark:text-red-400',
        server_error: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
    };

    return map[tag] ?? 'bg-muted text-muted-foreground';
}

function formatUsd(value: number): string {
    return `$${value.toFixed(4)}`;
}

function formatMs(value: number): string {
    return `${value.toLocaleString('pt-BR')} ms`;
}
</script>

<template>
    <Head title="Laboratory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 p-3 sm:p-4 lg:p-6">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-sidebar-border bg-card text-muted-foreground"
                    >
                        <FlaskConical class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold text-foreground">
                            Laboratory
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Centro de operação para qualidade, custo,
                            recuperação e sinais recentes do agente.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link
                                v-for="item in navigationItems"
                                :key="item.href"
                                :href="item.href"
                                class="rounded-md border border-sidebar-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="externalLinks.langfuse"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <ExternalLink class="size-3.5" />
                        Langfuse
                    </a>
                    <a
                        :href="externalLinks.horizon"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <ExternalLink class="size-3.5" />
                        Horizon
                    </a>
                </div>
            </header>

            <section class="grid gap-3 lg:grid-cols-[1.25fr_1fr_1fr]">
                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Postura operacional
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-2xl font-semibold',
                                    posture.tone,
                                ]"
                            >
                                {{ posture.label }}
                            </p>
                        </div>
                        <div
                            :class="[
                                'flex size-10 items-center justify-center rounded-lg',
                                posture.bg,
                                posture.tone,
                            ]"
                        >
                            <component :is="posture.icon" class="size-5" />
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{ posture.note }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Trabalho aberto
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground"
                            >
                                {{ openRecoveryWork }}
                            </p>
                        </div>
                        <Gauge class="size-5 text-muted-foreground" />
                    </div>
                    <p class="mt-3 text-sm text-muted-foreground">
                        Retries, processamentos e escalamentos em aberto.
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Falhas hoje
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-2xl font-semibold',
                                    operationalFailuresToday > 0
                                        ? 'text-red-700 dark:text-red-400'
                                        : 'text-foreground',
                                ]"
                            >
                                {{ operationalFailuresToday }}
                            </p>
                        </div>
                        <AlertTriangle class="size-5 text-muted-foreground" />
                    </div>
                    <p class="mt-3 text-sm text-muted-foreground">
                        Soma de follow-up e disparos em massa.
                    </p>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="metric in aiMetrics"
                    :key="metric.label"
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ metric.label }}
                            </p>
                            <p
                                class="mt-1 truncate text-2xl font-semibold text-foreground"
                            >
                                {{ metric.value }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ metric.detail }}
                            </p>
                        </div>
                        <component
                            :is="metric.icon"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border bg-card shadow-sm"
            >
                <div
                    class="flex flex-col gap-1 border-b border-sidebar-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Comparação por arquitetura
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Últimos 30 dias de AI runs registrados.
                        </p>
                    </div>
                    <Link
                        :href="laboratory.aiUsage.url()"
                        class="text-xs font-medium text-primary hover:text-primary/80"
                    >
                        Ver AI Usage
                    </Link>
                </div>
                <div
                    v-if="architectureComparison.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Nenhum AI run registrado nos últimos 30 dias.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead
                            class="border-b border-sidebar-border bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">
                                    Arquitetura
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Runs
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Custo médio
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Latência
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    p95
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    LLM
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Tools
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Sucesso
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="item in architectureComparison"
                                :key="item.architecture_version"
                                class="hover:bg-muted/40"
                            >
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ item.architecture_version }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ item.runs }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ formatUsd(item.avg_cost_usd) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ formatMs(item.avg_latency_ms) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ formatMs(item.p95_latency_ms) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ item.avg_llm_calls }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ item.avg_tool_calls }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold text-foreground"
                                >
                                    {{ item.success_rate }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <RefreshCw class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Recovery engine
                        </h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="metric in recoveryMetrics"
                            :key="metric.label"
                            class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ metric.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-2xl font-semibold text-foreground"
                                    >
                                        {{ metric.value }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ metric.detail }}
                                    </p>
                                </div>
                                <component
                                    :is="metric.icon"
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <Send class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Follow-up engine
                        </h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="metric in followupMetrics"
                            :key="metric.label"
                            class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ metric.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-2xl font-semibold text-foreground"
                                    >
                                        {{ metric.value }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ metric.detail }}
                                    </p>
                                </div>
                                <component
                                    :is="metric.icon"
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Megaphone class="size-4 text-muted-foreground" />
                    <h2 class="text-sm font-semibold text-foreground">
                        Disparos em massa
                    </h2>
                </div>
                <div
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                >
                    <component
                        :is="metric.href ? Link : 'div'"
                        v-for="metric in campaignMetrics"
                        :key="metric.label"
                        :href="metric.href"
                        class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm transition-colors hover:bg-muted/40"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ metric.label }}
                                </p>
                                <p
                                    class="mt-1 text-2xl font-semibold text-foreground"
                                >
                                    {{ metric.value }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ metric.detail }}
                                </p>
                            </div>
                            <component
                                :is="metric.icon"
                                class="size-4 text-muted-foreground"
                            />
                        </div>
                    </component>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div
                    class="overflow-hidden rounded-lg border border-sidebar-border bg-card shadow-sm"
                >
                    <div class="border-b border-sidebar-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Padrões de erro
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Últimos 7 dias por tag e fonte.
                        </p>
                    </div>
                    <div
                        v-if="errorPatterns.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Nenhum erro registrado nos últimos 7 dias.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead
                                class="border-b border-sidebar-border bg-muted/40 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">
                                        Tag
                                    </th>
                                    <th class="px-4 py-2 text-left font-medium">
                                        Fonte
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Ocorrências
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Retries médios
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="pattern in errorPatterns"
                                    :key="`${pattern.error_tag}-${pattern.error_source}`"
                                    class="hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'rounded-md px-2 py-1 text-xs font-medium',
                                                tagColor(pattern.error_tag),
                                            ]"
                                        >
                                            {{ pattern.error_tag }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ pattern.error_source }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold text-foreground"
                                    >
                                        {{ pattern.count }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            Number(pattern.avg_retries).toFixed(
                                                1,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border bg-card shadow-sm"
                >
                    <div class="border-b border-sidebar-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Falhas por hora
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Distribuição dos últimos 7 dias.
                        </p>
                    </div>
                    <div class="p-4">
                        <div class="flex h-36 items-end gap-1">
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="group relative flex h-full flex-1 flex-col justify-end"
                            >
                                <div
                                    class="min-h-0.5 w-full rounded-t bg-muted transition-colors group-hover:bg-muted-foreground/30"
                                    :class="
                                        (hourlyFailures[hour] ?? 0) > 0
                                            ? 'bg-red-500/70 group-hover:bg-red-500'
                                            : ''
                                    "
                                    :style="{
                                        height: `${((hourlyFailures[hour] ?? 0) / maxHourlyCount) * 100}%`,
                                    }"
                                    :title="`${hour}h: ${hourlyFailures[hour] ?? 0} falhas`"
                                />
                                <span
                                    v-if="hour % 6 === 0"
                                    class="mt-1 text-center text-[10px] text-muted-foreground"
                                    >{{ hour }}h</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-sidebar-border bg-card shadow-sm"
            >
                <div class="border-b border-sidebar-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">
                        Feed de falhas recentes
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Últimos registros com retry ou escalamento.
                    </p>
                </div>
                <div
                    v-if="recentFailures.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Nenhuma falha registrada.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="border-b border-sidebar-border bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">
                                    Lead
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Erro
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Retries
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Próximo retry
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="failure in recentFailures"
                                :key="failure.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-4 py-3">
                                    <div v-if="failure.lead">
                                        <Link
                                            :href="
                                                conversas.show(failure.lead.id)
                                                    .url
                                            "
                                            class="font-medium text-foreground hover:text-primary"
                                        >
                                            {{ failure.lead.nome }}
                                        </Link>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ failure.lead.whatsapp }}
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-1 text-xs font-medium',
                                            tagColor(failure.error_tag),
                                        ]"
                                    >
                                        {{ failure.error_tag }}
                                    </span>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ failure.error_source }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-1 text-xs font-medium',
                                            statusColor(failure.status),
                                        ]"
                                    >
                                        {{ failure.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ failure.retry_count }}x
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ failure.next_retry_at ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
