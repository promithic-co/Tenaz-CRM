<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Bot,
    CheckCircle,
    ChevronRight,
    Clock,
    Loader2,
    UserCheck,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import echo from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ticketPriorityClasses,
    ticketPriorityLabel,
    ticketReasonLabel,
    ticketStatusClasses,
    ticketStatusLabel,
} from '@/lib/ticket-status';
import {
    claim,
    close,
    keepManual,
    resolve,
    returnToAi,
} from '@/routes/atendimentos';
import { show as conversaShow } from '@/routes/conversas';
import type { BreadcrumbItem } from '@/types';

type Ticket = {
    id: number;
    lead_id: number;
    lead_nome: string;
    lead_whatsapp: string;
    lead_status: string | null;
    lead_ai_mode: string | null;
    lead_operational_stage: string | null;
    lead_followup_status: string;
    lead_followup_count: number;
    type: string;
    status: string;
    priority: string;
    reason: string | null;
    summary: string | null;
    assigned_user_id: number | null;
    assigned_user_name: string | null;
    sla_due_at: string | null;
    sla_due_at_full: string | null;
    sla_overdue: boolean;
    claimed_at: string | null;
    first_response_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    resolution_reason: string | null;
    resolution_notes: string | null;
    chosen_product: string | null;
    total_value: string | null;
    created_at: string;
    created_at_full: string;
    hours_open: number | null;
    urgency: 'high' | 'medium' | 'low' | null;
};

type AiLead = {
    id: number;
    nome: string;
    whatsapp: string;
    status: string | null;
    operational_stage: string | null;
    ai_mode: string | null;
    followup_status: string | null;
    assigned_user_name: string | null;
    ultima_interacao: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    tenantId: string;
    buckets: {
        waiting: Paginated<Ticket>;
        mine: Paginated<Ticket>;
        ai: AiLead[];
        closed: Paginated<Ticket>;
    };
    counters: {
        waiting: number;
        mine: number;
        ai: number;
        closed: number;
        overdue: number;
    };
    filters: {
        motivo: string;
        data_inicio: string;
        data_fim: string;
    };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Atendimentos', href: '/atendimentos' },
];

type TabKey = 'waiting' | 'mine' | 'ai' | 'closed';
const activeTab = ref<TabKey>('waiting');

const tabs: Array<{ key: TabKey; label: string }> = [
    { key: 'waiting', label: 'Aguardando atendimento' },
    { key: 'mine', label: 'Em atendimento humano' },
    { key: 'ai', label: 'Em atendimento IA' },
    { key: 'closed', label: 'Encerrados' },
];

const filterForm = ref({
    motivo: props.filters.motivo || '',
    data_inicio: props.filters.data_inicio || '',
    data_fim: props.filters.data_fim || '',
});

function applyFilters() {
    router.get('/atendimentos', filterForm.value, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    filterForm.value = { motivo: '', data_inicio: '', data_fim: '' };
    applyFilters();
}

const ticketMotivoModal = ref<Ticket | null>(null);
const ticketActionLoading = ref<number | null>(null);
const reloading = ref(false);

const page = usePage();
const flashMessage = computed(
    () => (page.props.flash as string | null) ?? null,
);
const flashError = computed(
    () => (page.props.flash_error as string | null) ?? null,
);

function postTicketAction(ticketId: number, url: string): void {
    ticketActionLoading.value = ticketId;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                ticketActionLoading.value = null;
            },
        },
    );
}

const stageLabels: Record<string, string> = {
    new_inbound: 'Nova entrada',
    ai_qualifying: 'IA qualificando',
    qualified_opportunity: 'Oportunidade',
    ai_followup: 'Follow-up IA',
    human_pending: 'Aguardando humano',
    human_active: 'Humano ativo',
    waiting_customer: 'Aguardando cliente',
    proposal_sent: 'Proposta',
    won: 'Ganho',
    future_opportunity: 'Futuro',
    lost: 'Perdido',
};

function urgencyBorder(ticket: Ticket): string {
    if (ticket.urgency === 'high') {
        return 'border-l-4 border-l-red-500';
    }
    if (ticket.urgency === 'medium') {
        return 'border-l-4 border-l-amber-500';
    }
    return '';
}

let reloadTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleReload(): void {
    if (reloadTimer) {
        return;
    }
    reloading.value = true;
    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        router.reload({
            only: ['buckets', 'counters'],
            onFinish: () => {
                reloading.value = false;
            },
        });
    }, 600);
}

onMounted(() => {
    const channel = echo.private(`atendimentos.${props.tenantId}`);
    channel
        .listen('.handoff.created', scheduleReload)
        .listen('.handoff.claimed', scheduleReload)
        .listen('.handoff.resolved', scheduleReload)
        .listen('.handoff.returned_to_ai', scheduleReload)
        .listen('.atendimento.counters.updated', scheduleReload);
});

onUnmounted(() => {
    if (reloadTimer) {
        clearTimeout(reloadTimer);
    }
    echo.leave(`atendimentos.${props.tenantId}`);
});
</script>

<template>
    <Head title="Atendimentos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-lg font-semibold text-foreground">
                        Atendimentos
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Fila operacional de handoff humano
                    </p>
                </div>
                <div
                    v-if="reloading"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    Atualizando…
                </div>
            </div>

            <div
                v-if="flashError"
                class="mb-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-400"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ flashError }}</span>
            </div>
            <div
                v-else-if="flashMessage"
                class="mb-4 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-400"
            >
                <CheckCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ flashMessage }}</span>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row">
                <!-- Filter sidebar -->
                <aside class="w-full shrink-0 lg:w-56">
                    <form @submit.prevent="applyFilters" class="space-y-4">
                        <div
                            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                        >
                            <div
                                class="border-b border-sidebar-border/70 bg-muted/40 px-4 py-3 text-xs font-semibold tracking-wide text-foreground uppercase dark:border-sidebar-border"
                            >
                                Filtros
                            </div>
                            <div class="space-y-4 p-4">
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-muted-foreground"
                                        >Motivo ou Resumo</label
                                    >
                                    <input
                                        v-model="filterForm.motivo"
                                        @keyup.enter="applyFilters"
                                        @blur="applyFilters"
                                        type="text"
                                        placeholder="Buscar texto..."
                                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-muted-foreground"
                                        >Data Inicial</label
                                    >
                                    <input
                                        v-model="filterForm.data_inicio"
                                        @change="applyFilters"
                                        type="date"
                                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none [&::-webkit-calendar-picker-indicator]:dark:invert"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-muted-foreground"
                                        >Data Final</label
                                    >
                                    <input
                                        v-model="filterForm.data_fim"
                                        @change="applyFilters"
                                        type="date"
                                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none [&::-webkit-calendar-picker-indicator]:dark:invert"
                                    />
                                </div>
                            </div>
                            <div
                                class="border-t border-sidebar-border/70 bg-muted/20 p-3 dark:border-sidebar-border"
                            >
                                <button
                                    type="button"
                                    @click="clearFilters"
                                    class="w-full rounded-md bg-secondary px-3 py-2 text-xs font-medium text-secondary-foreground transition-colors hover:bg-secondary/80"
                                >
                                    Limpar Filtros
                                </button>
                            </div>
                        </div>

                        <!-- Counters summary -->
                        <div
                            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                        >
                            <div
                                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                            >
                                <div
                                    class="flex items-center justify-between px-4 py-2.5"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >Aguardando</span
                                    >
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                        >{{ counters.waiting }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between px-4 py-2.5"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >Em atendimento</span
                                    >
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                        >{{ counters.mine }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between px-4 py-2.5"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >IA ativa</span
                                    >
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                        >{{ counters.ai }}</span
                                    >
                                </div>
                                <div
                                    v-if="counters.overdue > 0"
                                    class="flex items-center justify-between bg-red-50 px-4 py-2.5 dark:bg-red-950/20"
                                >
                                    <span
                                        class="text-xs font-medium text-red-600 dark:text-red-400"
                                        >SLA vencido</span
                                    >
                                    <span
                                        class="text-xs font-semibold text-red-600 dark:text-red-400"
                                        >{{ counters.overdue }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </form>
                </aside>

                <!-- Main content -->
                <div class="min-w-0 flex-1">
                    <!-- Tabs -->
                    <div
                        class="mb-4 flex gap-1 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card p-1 dark:border-sidebar-border"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:flex-1',
                                activeTab === tab.key
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                            @click="activeTab = tab.key"
                        >
                            <Bot v-if="tab.key === 'ai'" class="h-3.5 w-3.5" />
                            <UserCheck
                                v-else-if="tab.key === 'mine'"
                                class="h-3.5 w-3.5"
                            />
                            <Clock
                                v-else-if="tab.key === 'waiting'"
                                class="h-3.5 w-3.5"
                            />
                            <CheckCircle v-else class="h-3.5 w-3.5" />
                            {{ tab.label }}
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                    activeTab === tab.key
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ counters[tab.key] }}
                            </span>
                        </button>
                    </div>

                    <!-- Waiting bucket -->
                    <div
                        v-if="activeTab === 'waiting'"
                        class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    >
                        <div
                            v-if="buckets.waiting.data.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <CheckCircle
                                class="mb-3 h-12 w-12 text-emerald-500"
                            />
                            <h3 class="mb-1 font-semibold">
                                Nenhum atendimento aguardando
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Todos os atendimentos foram assumidos ou
                                resolvidos.
                            </p>
                        </div>
                        <table v-else class="w-full min-w-[48rem] text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Lead
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Motivo
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Prioridade
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Aberto
                                    </th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                            >
                                <tr
                                    v-for="ticket in buckets.waiting.data"
                                    :key="ticket.id"
                                    :class="[
                                        'transition-colors hover:bg-muted/50',
                                        urgencyBorder(ticket),
                                    ]"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                                            >
                                                {{
                                                    ticket.lead_nome[0]?.toUpperCase() ??
                                                    '?'
                                                }}
                                            </div>
                                            <div>
                                                <p
                                                    class="font-medium text-foreground"
                                                >
                                                    {{ ticket.lead_nome }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ ticket.lead_whatsapp }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">
                                            {{
                                                ticketReasonLabel(ticket.reason)
                                            }}
                                        </p>
                                        <p
                                            class="max-w-48 truncate text-xs text-muted-foreground"
                                            :title="ticket.summary || ''"
                                        >
                                            {{ ticket.summary }}
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-0.5 inline-flex items-center gap-0.5 text-xs text-primary hover:underline"
                                            @click="ticketMotivoModal = ticket"
                                        >
                                            Ver mais
                                            <ChevronRight class="h-3 w-3" />
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                ticketPriorityClasses(
                                                    ticket.priority,
                                                ),
                                            ]"
                                            >{{
                                                ticketPriorityLabel(
                                                    ticket.priority,
                                                )
                                            }}</span
                                        >
                                        <p
                                            v-if="ticket.sla_due_at"
                                            :class="[
                                                'mt-0.5 text-xs',
                                                ticket.sla_overdue
                                                    ? 'font-medium text-red-400'
                                                    : 'text-muted-foreground',
                                            ]"
                                            :title="
                                                ticket.sla_due_at_full || ''
                                            "
                                        >
                                            SLA {{ ticket.sla_due_at }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-xs text-foreground"
                                            :title="ticket.created_at_full"
                                        >
                                            {{ ticket.created_at }}
                                        </p>
                                        <p
                                            v-if="ticket.hours_open !== null"
                                            :class="[
                                                'mt-0.5 text-xs',
                                                ticket.urgency === 'high'
                                                    ? 'font-medium text-red-400'
                                                    : ticket.urgency ===
                                                        'medium'
                                                      ? 'text-amber-400'
                                                      : 'text-muted-foreground',
                                            ]"
                                        >
                                            Aberto há {{ ticket.hours_open }}h
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="flex flex-wrap items-center justify-end gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-md border border-input px-2 py-1 text-xs font-medium text-foreground hover:bg-muted disabled:opacity-50"
                                                :disabled="
                                                    ticketActionLoading ===
                                                    ticket.id
                                                "
                                                @click="
                                                    postTicketAction(
                                                        ticket.id,
                                                        claim.url(ticket.id),
                                                    )
                                                "
                                            >
                                                Assumir
                                            </button>
                                            <Link
                                                :href="
                                                    conversaShow.url(
                                                        ticket.lead_id,
                                                    )
                                                "
                                                class="text-xs font-medium text-primary hover:underline"
                                            >
                                                Ver conversa →
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-if="buckets.waiting.links?.length > 3"
                            class="flex min-w-max items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                        >
                            <template
                                v-for="link in buckets.waiting.links"
                                :key="link.label"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-primary font-medium text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    ]"
                                />
                                <span
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1 text-sm text-muted-foreground/40"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- Mine bucket -->
                    <div
                        v-if="activeTab === 'mine'"
                        class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    >
                        <div
                            v-if="buckets.mine.data.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <UserCheck
                                class="mb-3 h-12 w-12 text-muted-foreground"
                            />
                            <h3 class="mb-1 font-semibold">
                                Nenhum atendimento em andamento
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Assuma um atendimento da fila de espera.
                            </p>
                        </div>
                        <table v-else class="w-full min-w-[52rem] text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Lead
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Motivo
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        SLA
                                    </th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                            >
                                <tr
                                    v-for="ticket in buckets.mine.data"
                                    :key="ticket.id"
                                    :class="[
                                        'transition-colors hover:bg-muted/50',
                                        urgencyBorder(ticket),
                                    ]"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                                            >
                                                {{
                                                    ticket.lead_nome[0]?.toUpperCase() ??
                                                    '?'
                                                }}
                                            </div>
                                            <div>
                                                <p
                                                    class="font-medium text-foreground"
                                                >
                                                    {{ ticket.lead_nome }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ ticket.lead_whatsapp }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">
                                            {{
                                                ticketReasonLabel(ticket.reason)
                                            }}
                                        </p>
                                        <p
                                            class="max-w-48 truncate text-xs text-muted-foreground"
                                            :title="ticket.summary || ''"
                                        >
                                            {{ ticket.summary }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                ticketStatusClasses(
                                                    ticket.status,
                                                ),
                                            ]"
                                            >{{
                                                ticketStatusLabel(ticket.status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            v-if="ticket.sla_due_at"
                                            :class="[
                                                'text-xs',
                                                ticket.sla_overdue
                                                    ? 'font-medium text-red-400'
                                                    : 'text-muted-foreground',
                                            ]"
                                            :title="
                                                ticket.sla_due_at_full || ''
                                            "
                                        >
                                            {{ ticket.sla_due_at }}
                                        </p>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            —
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="flex flex-wrap items-center justify-end gap-2"
                                        >
                                            <Link
                                                :href="
                                                    conversaShow.url(
                                                        ticket.lead_id,
                                                    )
                                                "
                                                class="text-xs font-medium text-primary hover:underline"
                                            >
                                                Ver conversa →
                                            </Link>
                                            <button
                                                type="button"
                                                title="Devolve o lead para a IA continuar o atendimento"
                                                class="rounded-md border border-sky-500/30 px-2 py-1 text-xs font-medium text-sky-600 hover:bg-sky-500/10 disabled:opacity-50 dark:text-sky-400"
                                                :disabled="
                                                    ticketActionLoading ===
                                                    ticket.id
                                                "
                                                @click="
                                                    postTicketAction(
                                                        ticket.id,
                                                        returnToAi.url(
                                                            ticket.id,
                                                        ),
                                                    )
                                                "
                                            >
                                                Devolver para IA
                                            </button>
                                            <button
                                                type="button"
                                                title="Fecha o atendimento mantendo a IA pausada — você continua respondendo manualmente"
                                                class="rounded-md border border-input px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-muted disabled:opacity-50"
                                                :disabled="
                                                    ticketActionLoading ===
                                                    ticket.id
                                                "
                                                @click="
                                                    postTicketAction(
                                                        ticket.id,
                                                        keepManual.url(
                                                            ticket.id,
                                                        ),
                                                    )
                                                "
                                            >
                                                Manter manual
                                            </button>
                                            <button
                                                type="button"
                                                title="Finaliza o atendimento e libera o lead"
                                                class="rounded-md border border-emerald-500/30 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-500/10 disabled:opacity-50 dark:text-emerald-400"
                                                :disabled="
                                                    ticketActionLoading ===
                                                    ticket.id
                                                "
                                                @click="
                                                    postTicketAction(
                                                        ticket.id,
                                                        resolve.url(ticket.id),
                                                    )
                                                "
                                            >
                                                Finalizar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-if="buckets.mine.links?.length > 3"
                            class="flex min-w-max items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                        >
                            <template
                                v-for="link in buckets.mine.links"
                                :key="link.label"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-primary font-medium text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    ]"
                                />
                                <span
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1 text-sm text-muted-foreground/40"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- AI bucket -->
                    <div
                        v-if="activeTab === 'ai'"
                        class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    >
                        <div
                            v-if="buckets.ai.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <Bot class="mb-3 h-12 w-12 text-muted-foreground" />
                            <h3 class="mb-1 font-semibold">
                                Nenhum lead em atendimento IA
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Leads qualificados em fluxo IA aparecerão aqui.
                            </p>
                        </div>
                        <table v-else class="w-full min-w-[44rem] text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Lead
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Etapa
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Responsavel
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Ultima interacao
                                    </th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                            >
                                <tr
                                    v-for="lead in buckets.ai"
                                    :key="lead.id"
                                    class="transition-colors hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-sm font-semibold text-green-600 dark:bg-green-950 dark:text-green-400"
                                            >
                                                <Bot class="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p
                                                    class="font-medium text-foreground"
                                                >
                                                    {{ lead.nome }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ lead.whatsapp }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full border border-muted bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            {{
                                                lead.operational_stage
                                                    ? (stageLabels[
                                                          lead.operational_stage
                                                      ] ??
                                                      lead.operational_stage)
                                                    : '—'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs text-foreground">
                                            {{
                                                lead.assigned_user_name ??
                                                'Sem responsavel'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ lead.ultima_interacao ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="conversaShow.url(lead.id)"
                                            class="text-xs font-medium text-primary hover:underline"
                                        >
                                            Ver conversa →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Closed bucket -->
                    <div
                        v-if="activeTab === 'closed'"
                        class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    >
                        <div
                            v-if="buckets.closed.data.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-center"
                        >
                            <CheckCircle
                                class="mb-3 h-12 w-12 text-muted-foreground"
                            />
                            <h3 class="mb-1 font-semibold">
                                Nenhum atendimento encerrado
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Atendimentos resolvidos ou fechados aparecerão
                                aqui.
                            </p>
                        </div>
                        <table v-else class="w-full min-w-[56rem] text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Lead
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Motivo
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Responsavel
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Encerrado
                                    </th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                            >
                                <tr
                                    v-for="ticket in buckets.closed.data"
                                    :key="ticket.id"
                                    class="transition-colors hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                                            >
                                                {{
                                                    ticket.lead_nome[0]?.toUpperCase() ??
                                                    '?'
                                                }}
                                            </div>
                                            <div>
                                                <p
                                                    class="font-medium text-foreground"
                                                >
                                                    {{ ticket.lead_nome }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ ticket.lead_whatsapp }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">
                                            {{
                                                ticketReasonLabel(ticket.reason)
                                            }}
                                        </p>
                                        <p
                                            class="max-w-40 truncate text-xs text-muted-foreground"
                                            :title="ticket.summary || ''"
                                        >
                                            {{ ticket.summary }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                ticketStatusClasses(
                                                    ticket.status,
                                                ),
                                            ]"
                                            >{{
                                                ticketStatusLabel(ticket.status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs text-foreground">
                                            {{
                                                ticket.assigned_user_name ??
                                                'Sem responsavel'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-xs text-muted-foreground"
                                            :title="
                                                ticket.resolved_at ??
                                                ticket.closed_at ??
                                                ''
                                            "
                                        >
                                            {{
                                                ticket.resolved_at ??
                                                ticket.closed_at ??
                                                ticket.created_at
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="
                                                conversaShow.url(ticket.lead_id)
                                            "
                                            class="text-xs font-medium text-primary hover:underline"
                                        >
                                            Ver conversa →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-if="buckets.closed.links?.length > 3"
                            class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                        >
                            <template
                                v-for="link in buckets.closed.links"
                                :key="link.label"
                            >
                                <!-- eslint-disable vue/no-v-text-v-html-on-component -->
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-primary font-medium text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    ]"
                                />
                                <span
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1 text-sm text-muted-foreground/40"
                                />
                                <!-- eslint-enable vue/no-v-text-v-html-on-component -->
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket detail modal -->
        <Dialog
            :open="ticketMotivoModal !== null"
            @update:open="
                (open) => {
                    if (!open) ticketMotivoModal = null;
                }
            "
        >
            <DialogContent v-if="ticketMotivoModal" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Motivo e resumo do atendimento</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 text-sm">
                    <div>
                        <p class="mb-1 font-medium text-muted-foreground">
                            Lead
                        </p>
                        <p class="text-foreground">
                            {{ ticketMotivoModal.lead_nome }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ ticketMotivoModal.lead_whatsapp }}
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 font-medium text-muted-foreground">
                            Data
                        </p>
                        <p class="text-foreground">
                            {{ ticketMotivoModal.created_at_full }}
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 font-medium text-muted-foreground">
                            Motivo
                        </p>
                        <p
                            class="break-words whitespace-pre-wrap text-foreground"
                        >
                            {{ ticketReasonLabel(ticketMotivoModal.reason) }}
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 font-medium text-muted-foreground">
                            Resumo
                        </p>
                        <p
                            class="break-words whitespace-pre-wrap text-foreground"
                        >
                            {{ ticketMotivoModal.summary || '—' }}
                        </p>
                    </div>
                    <div v-if="ticketMotivoModal.chosen_product">
                        <p class="mb-1 font-medium text-muted-foreground">
                            Produto
                        </p>
                        <p class="text-foreground">
                            {{ ticketMotivoModal.chosen_product }}
                        </p>
                    </div>
                    <div v-if="ticketMotivoModal.total_value">
                        <p class="mb-1 font-medium text-muted-foreground">
                            Valor
                        </p>
                        <p class="text-foreground">
                            R$ {{ ticketMotivoModal.total_value }}
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
