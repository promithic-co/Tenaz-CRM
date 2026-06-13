<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { FlaskConical, RefreshCw, CheckCircle, AlertTriangle, Clock, ExternalLink, TrendingUp, Users, PauseCircle, Send, XCircle, Trophy, Megaphone, MessageSquare, BarChart2, Reply, DollarSign } from 'lucide-vue-next';
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
    estimated_cost_today: number;
};

type Props = {
    stats: Stats;
    errorPatterns: ErrorPattern[];
    hourlyFailures: Record<number, number>;
    recentFailures: RecentFailure[];
    recoveryRate: number;
    followupStats: FollowupStats;
    bulkMetrics: BulkMetrics;
    externalLinks: { langfuse: string; horizon: string };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Laboratory', href: '/laboratory' }];

function statusColor(status: string): string {
    const map: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        retrying: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        escalated: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return map[status] ?? 'bg-muted text-muted-foreground';
}

function tagColor(tag: string): string {
    const map: Record<string, string> = {
        timeout: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        rate_limit: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        context_overflow: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        connection_error: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        server_error: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    };
    return map[tag] ?? 'bg-muted text-muted-foreground';
}

const maxHourlyCount = Math.max(...Object.values(props.hourlyFailures), 1);

const hours = Array.from({ length: 24 }, (_, i) => i);
</script>

<template>
    <Head title="Laboratory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <FlaskConical class="h-5 w-5 text-muted-foreground" />
                    <h1 class="text-lg font-semibold text-foreground">Laboratory</h1>
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">Observability & Recovery</span>
                </div>
                <!-- External links -->
                <div class="flex items-center gap-2">
                    <a
                        :href="externalLinks.langfuse"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1 rounded-md border border-input bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted"
                    >
                        <ExternalLink class="h-3 w-3" />
                        Langfuse
                    </a>
                    <a
                        :href="externalLinks.horizon"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1 rounded-md border border-input bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted"
                    >
                        <ExternalLink class="h-3 w-3" />
                        Horizon
                    </a>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <!-- Recovery rate -->
                <div class="col-span-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recovery Rate</span>
                        <TrendingUp class="h-4 w-4 text-green-500" />
                    </div>
                    <p class="mt-1 text-2xl font-bold" :class="recoveryRate >= 80 ? 'text-green-600 dark:text-green-400' : recoveryRate >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'">
                        {{ recoveryRate }}%
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">últimos 7 dias</p>
                </div>

                <!-- Pending retries -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pendentes</span>
                        <Clock class="h-4 w-4 text-yellow-500" />
                    </div>
                    <p class="mt-1 text-2xl font-bold text-foreground">{{ stats.pending_retries }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">aguardando retry</p>
                </div>

                <!-- Retrying now -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Em Retry</span>
                        <RefreshCw class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="mt-1 text-2xl font-bold text-foreground">{{ stats.retrying_now }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">processando agora</p>
                </div>

                <!-- Resolved today -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Resolvidos</span>
                        <CheckCircle class="h-4 w-4 text-green-500" />
                    </div>
                    <p class="mt-1 text-2xl font-bold text-foreground">{{ stats.resolved_today }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">hoje</p>
                </div>

                <!-- Escalated -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Escalados</span>
                        <AlertTriangle class="h-4 w-4 text-red-500" />
                    </div>
                    <p class="mt-1 text-2xl font-bold" :class="stats.escalated_open > 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                        {{ stats.escalated_open }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">abertos</p>
                </div>
            </div>

            <!-- Follow-Up Engine -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Send class="h-4 w-4 text-muted-foreground" />
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Follow-Up Engine</span>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <!-- Ativos -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Ativos</span>
                            <Users class="h-4 w-4 text-blue-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ followupStats.active_count }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">leads em sequência</p>
                    </div>

                    <!-- Pausados -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pausados</span>
                            <PauseCircle class="h-4 w-4 text-yellow-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ followupStats.paused_count }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">temporariamente parados</p>
                    </div>

                    <!-- Enviados Hoje -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Enviados Hoje</span>
                            <Send class="h-4 w-4 text-blue-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ followupStats.sent_today }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">mensagens enviadas</p>
                    </div>

                    <!-- Falhas Hoje -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Falhas Hoje</span>
                            <XCircle :class="['h-4 w-4', followupStats.failed_today > 0 ? 'text-red-500' : 'text-green-500']" />
                        </div>
                        <p class="mt-1 text-2xl font-bold" :class="followupStats.failed_today > 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                            {{ followupStats.failed_today }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">jobs com falha</p>
                    </div>

                    <!-- Convertidos (30d) -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Convertidos (30d)</span>
                            <Trophy class="h-4 w-4 text-green-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ followupStats.converted_from_followup }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">via follow-up</p>
                    </div>
                </div>
            </div>

            <!-- Disparos em Massa -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Megaphone class="h-4 w-4 text-muted-foreground" />
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Disparos em Massa</span>
                </div>
                <!-- Row 1: 4 cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <!-- Campanhas Ativas -->
                    <Link href="/campanhas?status=sending" class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 transition-colors hover:bg-muted/40 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Campanhas Ativas</span>
                            <Megaphone class="h-4 w-4 text-green-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ bulkMetrics.campaigns_active }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">enviando agora</p>
                    </Link>

                    <!-- Mensagens Enviadas Hoje -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Enviadas Hoje</span>
                            <MessageSquare class="h-4 w-4 text-blue-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ bulkMetrics.messages_sent_today }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">mensagens</p>
                    </div>

                    <!-- Taxa de Entrega -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Taxa Entrega</span>
                            <BarChart2 class="h-4 w-4 text-green-500" />
                        </div>
                        <p
                            class="mt-1 text-2xl font-bold"
                            :class="bulkMetrics.delivery_rate_today >= 90 ? 'text-green-600 dark:text-green-400' : bulkMetrics.delivery_rate_today >= 70 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ bulkMetrics.delivery_rate_today }}%
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">hoje</p>
                    </div>

                    <!-- Respostas de Campanha -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Respostas</span>
                            <Reply class="h-4 w-4 text-orange-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ bulkMetrics.replies_from_campaigns_today }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">de campanhas hoje</p>
                    </div>
                </div>

                <!-- Row 2: 3 cards -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <!-- Concluídas Hoje -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Concluídas Hoje</span>
                            <CheckCircle class="h-4 w-4 text-green-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">{{ bulkMetrics.campaigns_completed_today }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">campanhas finalizadas</p>
                    </div>

                    <!-- Falhas Hoje -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Falhas Hoje</span>
                            <XCircle :class="['h-4 w-4', bulkMetrics.messages_failed_today > 0 ? 'text-red-500' : 'text-green-500']" />
                        </div>
                        <p class="mt-1 text-2xl font-bold" :class="bulkMetrics.messages_failed_today > 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                            {{ bulkMetrics.messages_failed_today }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">mensagens com falha</p>
                    </div>

                    <!-- Custo Estimado -->
                    <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Custo Estimado</span>
                            <DollarSign class="h-4 w-4 text-yellow-500" />
                        </div>
                        <p class="mt-1 text-2xl font-bold text-foreground">
                            R$ {{ bulkMetrics.estimated_cost_today.toFixed(2).replace('.', ',') }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">hoje (R$0,05/msg)</p>
                    </div>
                </div>
            </div>

            <!-- Two-column layout: Error patterns + Hourly heatmap -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                <!-- Error Pattern Map -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Padrões de Erro — últimos 7 dias</span>
                    </div>
                    <div v-if="errorPatterns.length === 0" class="px-4 py-8 text-center text-xs text-muted-foreground">
                        Nenhum erro registrado nos últimos 7 dias.
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Tag</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Fonte</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-muted-foreground">Ocorrências</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-muted-foreground">Avg Retries</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="pattern in errorPatterns" :key="`${pattern.error_tag}-${pattern.error_source}`">
                                <td class="px-4 py-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', tagColor(pattern.error_tag)]">
                                        {{ pattern.error_tag }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">{{ pattern.error_source }}</td>
                                <td class="px-4 py-2 text-right text-xs font-semibold text-foreground">{{ pattern.count }}</td>
                                <td class="px-4 py-2 text-right text-xs text-muted-foreground">{{ Number(pattern.avg_retries).toFixed(1) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Hourly Failure Heatmap -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Falhas por Hora — últimos 7 dias</span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-end gap-1" style="height: 120px;">
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="group relative flex flex-1 flex-col items-center"
                            >
                                <div
                                    class="w-full rounded-t transition-colors"
                                    :class="(hourlyFailures[hour] ?? 0) > 0 ? 'bg-red-400 dark:bg-red-500 group-hover:bg-red-500 dark:group-hover:bg-red-400' : 'bg-muted/40'"
                                    :style="{ height: `${((hourlyFailures[hour] ?? 0) / maxHourlyCount) * 100}px`, minHeight: '2px' }"
                                    :title="`${hour}h: ${hourlyFailures[hour] ?? 0} falhas`"
                                />
                                <span v-if="hour % 6 === 0" class="mt-1 text-[9px] text-muted-foreground">{{ hour }}h</span>
                            </div>
                        </div>
                        <p v-if="Object.keys(hourlyFailures).length === 0" class="mt-2 text-center text-xs text-muted-foreground">
                            Nenhuma falha registrada.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Live Failure Feed -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Feed de Falhas Recentes</span>
                </div>
                <div v-if="recentFailures.length === 0" class="px-4 py-8 text-center text-xs text-muted-foreground">
                    Nenhuma falha registrada.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Lead</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Erro</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Retries</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-muted-foreground">Próx. Retry</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr v-for="failure in recentFailures" :key="failure.id" class="transition-colors hover:bg-muted/40">
                            <td class="px-4 py-2">
                                <div v-if="failure.lead">
                                    <Link :href="`/conversas/${failure.lead.id}`" class="font-medium text-foreground hover:text-primary">
                                        {{ failure.lead.nome }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">{{ failure.lead.whatsapp }}</p>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2">
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', tagColor(failure.error_tag)]">
                                    {{ failure.error_tag }}
                                </span>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">{{ failure.error_source }}</p>
                            </td>
                            <td class="px-4 py-2">
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor(failure.status)]">
                                    {{ failure.status }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ failure.retry_count }}x</td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">
                                {{ failure.next_retry_at ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
