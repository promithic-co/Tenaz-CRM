<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Ban,
    Copy,
    Download,
    RotateCcw,
    Search,
    Send,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import {
    portfolioLimitLabel,
    restrictionLabel,
} from '@/composables/useMetaHealth';
import type {
    MetaHealthReason,
    MetaHealthStatus,
} from '@/composables/useMetaHealth';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';

type WhatsappInstance = {
    id: number;
    name: string;
    display_name: string | null;
    meta_quality_rating: string | null;
};
/** What Meta says about the number this campaign sends from. */
type InstanceHealth = {
    status: MetaHealthStatus;
    reasons: MetaHealthReason[];
    restrictions: string[];
    portfolio_messaging_limit: string | null;
};
type ContactList = { id: number; name: string };
type WhatsappTemplate = {
    id: number;
    name: string;
    body: string | null;
    variables_count: number;
};

type Campaign = {
    id: number;
    name: string;
    status: string;
    failure_reason: string | null;
    pause_reason_code: string | null;
    paused_from_status: string | null;
    risk_acknowledged_at: string | null;
    risk_acknowledged_by: number | null;
    total_recipients: number;
    total_sent: number;
    total_delivered: number;
    total_read: number;
    total_failed: number;
    total_attempted: number;
    daily_limit: number;
    delay_between_ms: number;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    contact_list: ContactList | null;
    whatsapp_template: WhatsappTemplate | null;
    whatsapp_instance: WhatsappInstance | null;
};

type CampaignMessage = {
    id: number;
    status: string;
    error_code: string | null;
    error_message: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    /** Template rendered with this recipient's own parameters; null when it could not be rendered. */
    rendered_message: string | null;
    contact_list_entry: { id: number; name: string; phone: string } | null;
};

type StatusCounts = Record<string, number>;
type DailyBudget = {
    sent_today: number;
    daily_limit: number;
    remaining: number;
};

type Props = {
    campaign: Campaign;
    messages: {
        data: CampaignMessage[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    repliedCount: number;
    statusCounts: StatusCounts;
    dailyBudget: DailyBudget;
    instanceHealth: InstanceHealth | null;
    filters: { status: string | null; search: string | null };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/campanhas' },
    { title: 'Campanhas', href: '/campanhas' },
    { title: props.campaign.name, href: `/campanhas/${props.campaign.id}` },
];

// Polling
let pollInterval: ReturnType<typeof setInterval> | null = null;
const pollKeys = [
    'campaign',
    'messages',
    'statusCounts',
    'dailyBudget',
    'repliedCount',
];

function startPolling(): void {
    if (pollInterval) {
        return;
    }
    pollInterval = setInterval(() => {
        router.reload({ only: pollKeys });
    }, 5000);
}

function stopPolling(): void {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

onMounted(() => {
    if (props.campaign.status === 'sending') {
        startPolling();
    }
});

onUnmounted(stopPolling);

watch(
    () => props.campaign.status,
    (newStatus) => {
        if (newStatus === 'sending') {
            startPolling();
        } else {
            stopPolling();
        }
    },
);

// Lifecycle control actions
function post(url: string): void {
    router.post(url, {}, { preserveScroll: true });
}

function startCampaign(): void {
    post(CampaignController.start(props.campaign.id).url);
}
function pauseCampaign(): void {
    post(CampaignController.pause(props.campaign.id).url);
}
function resumeCampaign(): void {
    post(CampaignController.resume(props.campaign.id).url);
}
function cancelCampaign(): void {
    if (
        !confirm(
            'Cancelar esta campanha? Os envios pendentes não serão realizados.',
        )
    ) {
        return;
    }
    post(CampaignController.cancel(props.campaign.id).url);
}
function duplicateCampaign(): void {
    router.post(CampaignController.duplicate(props.campaign.id).url);
}
function keepPausedForQualityRisk(): void {
    post(CampaignController.keepPausedForQualityRisk(props.campaign.id).url);
}
function continueWithQualityRisk(): void {
    post(CampaignController.continueWithQualityRisk(props.campaign.id).url);
}

function retryMessage(id: number): void {
    router.post(
        CampaignController.retryMessage([props.campaign.id, id]).url,
        {},
        { preserveScroll: true },
    );
}

// Throttle editor
const throttleOpen = ref(false);
const throttleForm = useForm({
    daily_limit: props.campaign.daily_limit,
    delay_between_ms: props.campaign.delay_between_ms,
});
const throttleEditable = computed(
    () => !['completed', 'cancelled'].includes(props.campaign.status),
);

function saveThrottle(): void {
    throttleForm.patch(
        CampaignController.updateThrottle(props.campaign.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                throttleOpen.value = false;
            },
        },
    );
}

// Selection + bulk remove
const selected = ref<Set<number>>(new Set());

function isSelectable(msg: CampaignMessage): boolean {
    return msg.status === 'pending' || msg.status === 'queued';
}

const selectablePageIds = computed(() =>
    props.messages.data.filter(isSelectable).map((m) => m.id),
);
const allPageSelected = computed(
    () =>
        selectablePageIds.value.length > 0 &&
        selectablePageIds.value.every((id) => selected.value.has(id)),
);

function toggleRow(id: number): void {
    const next = new Set(selected.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selected.value = next;
}

function toggleAllPage(): void {
    const next = new Set(selected.value);
    if (allPageSelected.value) {
        selectablePageIds.value.forEach((id) => next.delete(id));
    } else {
        selectablePageIds.value.forEach((id) => next.add(id));
    }
    selected.value = next;
}

function clearSelection(): void {
    selected.value = new Set();
}

function removeSelected(): void {
    const ids = Array.from(selected.value);
    if (ids.length === 0) {
        return;
    }
    if (
        !confirm(
            `Remover ${ids.length} destinatário(s) pendente(s) do disparo?`,
        )
    ) {
        return;
    }
    router.post(
        CampaignController.removeRecipients(props.campaign.id).url,
        { message_ids: ids },
        {
            preserveScroll: true,
            onSuccess: clearSelection,
        },
    );
}

// Filtering + search
const statusFilter = ref(props.filters.status ?? '');
const searchTerm = ref(props.filters.search ?? '');
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function reloadMessages(): void {
    const query: Record<string, string> = {};
    if (statusFilter.value) {
        query.status = statusFilter.value;
    }
    if (searchTerm.value.trim()) {
        query.search = searchTerm.value.trim();
    }
    router.get(CampaignController.show(props.campaign.id).url, query, {
        preserveScroll: true,
        preserveState: true,
        only: ['messages', 'filters'],
        onSuccess: clearSelection,
    });
}

function setStatus(value: string): void {
    statusFilter.value = value;
    reloadMessages();
}

watch(searchTerm, () => {
    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }
    searchDebounce = setTimeout(reloadMessages, 350);
});

const exportUrl = computed(() => {
    const query: Record<string, string> = {};
    if (statusFilter.value) {
        query.status = statusFilter.value;
    }
    if (searchTerm.value.trim()) {
        query.search = searchTerm.value.trim();
    }
    return CampaignController.export(props.campaign.id, { query }).url;
});

// Failures only, ignoring the active chip — the header shortcut has to mean the same thing
// wherever the recipients table happens to be filtered. Each row carries its error code and
// message, which is what tells the operator which number to fix before reloading the list.
const failuresExportUrl = computed(
    () =>
        CampaignController.export(props.campaign.id, {
            query: { status: 'failed' },
        }).url,
);

// Status helpers
function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        draft: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        scheduled:
            'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        sending:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        paused: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled:
            'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        draft: 'Rascunho',
        scheduled: 'Agendada',
        sending: 'Enviando',
        paused: 'Pausada',
        completed: 'Concluída',
        failed: 'Falha',
        cancelled: 'Cancelada',
    };
    return map[status] ?? status;
}

function msgStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        pending:
            'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        queued: 'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        in_doubt:
            'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        sent: 'rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-medium text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        delivered:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        read: 'rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        skipped:
            'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function msgStatusLabel(
    status: string,
    errorCode: string | null = null,
): string {
    if (status === 'skipped' && errorCode === 'REMOVED_MANUAL') {
        return 'Removido';
    }
    const map: Record<string, string> = {
        pending: 'Pendente',
        queued: 'Na fila',
        in_doubt: 'Em dúvida',
        sent: 'Enviado',
        delivered: 'Entregue',
        read: 'Lido',
        failed: 'Falha',
        skipped: 'Ignorado (opt-out)',
    };
    return map[status] ?? status;
}

// Metrics calculations
function safePercent(numerator: number, denominator: number): number {
    if (!denominator || denominator === 0) {
        return 0;
    }
    return Math.round((numerator / denominator) * 100);
}

const sentPercent = computed(() =>
    safePercent(props.campaign.total_sent, props.campaign.total_recipients),
);
const deliveryRate = computed(() =>
    safePercent(props.campaign.total_delivered, props.campaign.total_sent),
);
const readRate = computed(() =>
    safePercent(props.campaign.total_read, props.campaign.total_delivered),
);
// Denominator is total_attempted, matching Campaign::failureRate(). Not total_sent +
// total_failed: those overlap, because a message the provider accepted keeps its sent_at
// when the delivery webhook later reports a failure, so it counts in both terms and
// understates the rate. Dividing by total_sent alone is the opposite error — a send-time
// failure never gets sent_at and would sit outside its own denominator.
const failureRate = computed(() =>
    safePercent(props.campaign.total_failed, props.campaign.total_attempted),
);
// Meta health, as reported by health_status. BLOCKED and an active messaging
// restriction are what CampaignService refuses; LIMITED is not — Meta still
// delivers on a limited number, it only caps the daily volume — so it warns.
const healthBlocksSending = computed(
    () =>
        props.instanceHealth !== null &&
        (props.instanceHealth.status === 'BLOCKED' ||
            props.instanceHealth.restrictions.length > 0),
);
const healthWarnsOnly = computed(
    () =>
        props.instanceHealth?.status === 'LIMITED' &&
        !healthBlocksSending.value,
);
const healthDetail = computed(() => [
    ...(props.instanceHealth?.restrictions ?? []).map(restrictionLabel),
    ...(props.instanceHealth?.reasons ?? []).map((reason) =>
        reason.action ? `${reason.title} — ${reason.action}` : reason.title,
    ),
]);
const healthLimitLabel = computed(() =>
    portfolioLimitLabel(props.instanceHealth?.portfolio_messaging_limit),
);

const hasMetaQualityRisk = computed(
    () => props.campaign.pause_reason_code === 'meta_quality_red_auto_pause',
);
// Account-level Meta blocks (MetaAccountErrorTaxonomy): fatal for the whole WABA, so they
// get their own banner instead of reading as a generic high-failure-rate pause.
const hasMetaAccountBlock = computed(() =>
    ['meta_account_blocked', 'meta_account_payment_issue'].includes(
        props.campaign.pause_reason_code ?? '',
    ),
);
const metaAccountBlockTitle = computed(() =>
    props.campaign.pause_reason_code === 'meta_account_payment_issue'
        ? 'Pagamento da conta Meta com problema'
        : 'Conta Meta bloqueada',
);
const metaAccountBlockDetail = computed(() =>
    props.campaign.pause_reason_code === 'meta_account_payment_issue'
        ? 'A Meta recusou os envios por um problema no metodo de pagamento da conta. A campanha foi pausada; regularize o pagamento no Gerenciador de Negocios antes de retomar.'
        : 'A Meta bloqueou ou restringiu a conta business (verificacao pendente ou acao de politica). A campanha foi pausada; regularize a conta no Gerenciador de Negocios antes de retomar.',
);
const qualityRiskNeedsDecision = computed(
    () =>
        hasMetaQualityRisk.value &&
        props.campaign.status === 'paused' &&
        !props.campaign.risk_acknowledged_at,
);
const funnelTotal = computed(() =>
    Math.max(props.campaign.total_recipients, 1),
);
const funnelBars = computed(() => [
    {
        label: 'Enviados',
        count: props.campaign.total_sent,
        color: 'bg-blue-500',
    },
    {
        label: 'Entregues',
        count: props.campaign.total_delivered,
        color: 'bg-green-500',
    },
    {
        label: 'Lidos',
        count: props.campaign.total_read,
        color: 'bg-purple-500',
    },
    { label: 'Responderam', count: props.repliedCount, color: 'bg-orange-500' },
]);

// Derived control-availability flags
const failedCount = computed(() => props.statusCounts.failed ?? 0);
const pendingCount = computed(
    () => (props.statusCounts.pending ?? 0) + (props.statusCounts.queued ?? 0),
);
// Per-row retry only. There is deliberately no bulk "reprocess every failure": most
// failures are malformed numbers that re-fail identically, so the bulk path is to export
// them, fix the spreadsheet and load a new list.
const canRetryMessages = computed(
    () =>
        failedCount.value > 0 &&
        ['sending', 'paused', 'completed'].includes(props.campaign.status),
);
const canCancel = computed(() =>
    ['sending', 'paused'].includes(props.campaign.status),
);

const budgetPercent = computed(() =>
    safePercent(props.dailyBudget.sent_today, props.dailyBudget.daily_limit),
);
const etaMinutes = computed(() => {
    if (props.campaign.delay_between_ms <= 0 || pendingCount.value === 0) {
        return 0;
    }
    return Math.ceil(
        (pendingCount.value * props.campaign.delay_between_ms) / 60000,
    );
});

// Clickable status chips (counts from statusCounts)
const totalRecipientsCount = computed(() =>
    Object.values(props.statusCounts).reduce((sum, n) => sum + n, 0),
);
const statusChips = computed(() => [
    { value: '', label: 'Todos', count: totalRecipientsCount.value },
    {
        value: 'pending',
        label: 'Pendente',
        count: props.statusCounts.pending ?? 0,
    },
    {
        value: 'queued',
        label: 'Na fila',
        count: props.statusCounts.queued ?? 0,
    },
    { value: 'sent', label: 'Enviado', count: props.statusCounts.sent ?? 0 },
    {
        value: 'delivered',
        label: 'Entregue',
        count: props.statusCounts.delivered ?? 0,
    },
    { value: 'read', label: 'Lido', count: props.statusCounts.read ?? 0 },
    {
        value: 'in_doubt',
        label: 'Em dúvida',
        count: props.statusCounts.in_doubt ?? 0,
    },
    { value: 'failed', label: 'Falha', count: props.statusCounts.failed ?? 0 },
    {
        value: 'skipped',
        label: 'Ignorado',
        count: props.statusCounts.skipped ?? 0,
    },
]);

// Template preview collapsible
const templateExpanded = ref(false);
</script>

<template>
    <Head :title="campaign.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-3 sm:p-4">
            <!-- Header -->
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                >
                    <div class="flex min-w-0 flex-wrap items-center gap-3">
                        <h1 class="text-base font-semibold text-foreground">
                            {{ campaign.name }}
                        </h1>
                        <span
                            :class="[
                                statusBadgeClass(campaign.status),
                                campaign.status === 'sending'
                                    ? 'animate-pulse'
                                    : '',
                            ]"
                        >
                            {{ statusLabel(campaign.status) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="
                                campaign.status === 'draft' ||
                                campaign.status === 'scheduled'
                            "
                            class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-green-700"
                            @click="startCampaign"
                        >
                            <Send class="h-3.5 w-3.5" /> Iniciar Envio
                        </button>
                        <button
                            v-if="campaign.status === 'sending'"
                            class="rounded-md bg-yellow-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-yellow-600"
                            @click="pauseCampaign"
                        >
                            Pausar
                        </button>
                        <button
                            v-if="campaign.status === 'paused'"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                            @click="resumeCampaign"
                        >
                            Retomar
                        </button>
                        <a
                            v-if="failedCount > 0"
                            :href="failuresExportUrl"
                            class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Extrair falhas ({{ failedCount }})
                        </a>
                        <button
                            v-if="canCancel"
                            class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-background px-3 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40"
                            @click="cancelCampaign"
                        >
                            <Ban class="h-3.5 w-3.5" /> Cancelar
                        </button>
                        <button
                            class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            @click="duplicateCampaign"
                        >
                            <Copy class="h-3.5 w-3.5" /> Duplicar
                        </button>
                    </div>
                </div>

                <!-- Meta quality risk alert -->
                <div
                    v-if="hasMetaQualityRisk"
                    class="mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex min-w-0 gap-3">
                            <AlertTriangle
                                class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-300"
                            />
                            <div>
                                <p class="font-semibold">
                                    Risco de restricao/banimento
                                </p>
                                <p class="mt-1 leading-5">
                                    A qualidade Meta da instancia
                                    <strong>{{
                                        campaign.whatsapp_instance
                                            ?.display_name ??
                                        campaign.whatsapp_instance?.name ??
                                        'Meta'
                                    }}</strong>
                                    esta RED. A campanha foi pausada para
                                    reduzir risco de restricao ou banimento.
                                </p>
                                <p
                                    v-if="campaign.risk_acknowledged_at"
                                    class="mt-1 text-xs text-red-700 dark:text-red-300"
                                >
                                    Risco confirmado em
                                    {{
                                        formatDateTime(
                                            campaign.risk_acknowledged_at,
                                        )
                                    }}.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="qualityRiskNeedsDecision"
                            class="flex shrink-0 flex-wrap gap-2"
                        >
                            <button
                                type="button"
                                class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-900 hover:bg-red-100 dark:border-red-800 dark:bg-red-950 dark:text-red-100 dark:hover:bg-red-900/60"
                                @click="keepPausedForQualityRisk"
                            >
                                Manter pausada
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                                @click="continueWithQualityRisk"
                            >
                                Continuar por risco
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Meta account-level block alert -->
                <div
                    v-if="hasMetaAccountBlock"
                    class="mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200"
                >
                    <div class="flex min-w-0 gap-3">
                        <AlertTriangle
                            class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-300"
                        />
                        <div>
                            <p class="font-semibold">
                                {{ metaAccountBlockTitle }}
                            </p>
                            <p class="mt-1 leading-5">
                                {{ metaAccountBlockDetail }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Meta health status of the sending number -->
                <div
                    v-if="healthBlocksSending || healthWarnsOnly"
                    class="mx-4 mb-3 rounded-lg border px-4 py-3 text-sm"
                    :class="
                        healthBlocksSending
                            ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200'
                            : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200'
                    "
                >
                    <div class="flex min-w-0 gap-3">
                        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{
                                    healthBlocksSending
                                        ? 'A Meta bloqueou o envio deste número'
                                        : 'A Meta limitou o envio deste número'
                                }}
                            </p>
                            <p class="mt-1 leading-5">
                                {{
                                    healthBlocksSending
                                        ? 'Iniciar ou retomar a campanha fica bloqueado até a conta ser regularizada no WhatsApp Manager.'
                                        : 'A campanha continua enviando, com volume diário reduzido pela Meta.'
                                }}
                            </p>
                            <ul
                                v-if="healthDetail.length"
                                class="mt-1 list-disc space-y-0.5 pl-4 text-xs"
                            >
                                <li
                                    v-for="(reason, index) in healthDetail"
                                    :key="index"
                                >
                                    {{ reason }}
                                </li>
                            </ul>
                            <p
                                v-if="healthLimitLabel"
                                class="mt-1 text-xs opacity-80"
                            >
                                Teto do portfólio: {{ healthLimitLabel }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Failure reason alert -->
                <div
                    v-if="campaign.failure_reason && !hasMetaAccountBlock"
                    class="mx-4 mb-3 rounded-lg bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300"
                >
                    <strong>Motivo da falha:</strong>
                    {{ campaign.failure_reason }}
                </div>

                <!-- Meta info -->
                <div
                    class="grid grid-cols-2 gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:grid-cols-4 dark:border-sidebar-border"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Lista</p>
                        <Link
                            v-if="campaign.contact_list"
                            :href="`/listas-contato/${campaign.contact_list.id}`"
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ campaign.contact_list.name }}
                        </Link>
                        <span v-else class="text-sm text-muted-foreground"
                            >—</span
                        >
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Template</p>
                        <p class="text-sm font-medium text-foreground">
                            {{ campaign.whatsapp_template?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Instância</p>
                        <p class="text-sm font-medium text-foreground">
                            {{
                                campaign.whatsapp_instance?.display_name ??
                                campaign.whatsapp_instance?.name ??
                                '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs text-muted-foreground">
                                Limite Diário / Atraso
                            </p>
                            <button
                                v-if="throttleEditable"
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                @click="throttleOpen = !throttleOpen"
                            >
                                <SlidersHorizontal class="h-3 w-3" /> Editar
                            </button>
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            {{ campaign.daily_limit }} msgs /
                            {{ campaign.delay_between_ms }}ms
                        </p>
                    </div>
                </div>

                <!-- Throttle editor -->
                <div
                    v-if="throttleOpen && throttleEditable"
                    class="border-t border-sidebar-border/70 bg-muted/30 px-4 py-3 dark:border-sidebar-border"
                >
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Limite diário (msgs)</span
                            >
                            <input
                                v-model.number="throttleForm.daily_limit"
                                type="number"
                                min="1"
                                max="100000"
                                class="w-32 rounded-md border border-input bg-background px-2 py-1 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Atraso entre envios (ms)</span
                            >
                            <input
                                v-model.number="throttleForm.delay_between_ms"
                                type="number"
                                min="0"
                                max="60000"
                                class="w-32 rounded-md border border-input bg-background px-2 py-1 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :disabled="throttleForm.processing"
                                class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                                @click="saveThrottle"
                            >
                                Salvar
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                                @click="throttleOpen = false"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="
                            throttleForm.errors.daily_limit ||
                            throttleForm.errors.delay_between_ms
                        "
                        class="mt-2 text-xs text-red-600 dark:text-red-400"
                    >
                        {{
                            throttleForm.errors.daily_limit ||
                            throttleForm.errors.delay_between_ms
                        }}
                    </p>
                </div>
            </div>

            <!-- Metrics cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Enviados -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Enviados
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400"
                    >
                        {{ campaign.total_sent ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        de {{ campaign.total_recipients ?? 0 }} ({{
                            sentPercent
                        }}%)
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-blue-500 transition-all"
                            :style="{ width: `${sentPercent}%` }"
                        />
                    </div>
                </div>

                <!-- Entregues -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Entregues
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400"
                    >
                        {{ campaign.total_delivered ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Taxa: {{ deliveryRate }}%
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-green-500 transition-all"
                            :style="{ width: `${deliveryRate}%` }"
                        />
                    </div>
                </div>

                <!-- Lidos -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Lidos
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400"
                    >
                        {{ campaign.total_read ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Taxa: {{ readRate }}%
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-purple-500 transition-all"
                            :style="{ width: `${readRate}%` }"
                        />
                    </div>
                </div>

                <!-- Falhas -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Falhas
                    </p>
                    <p class="mt-1 text-2xl font-bold text-foreground">
                        {{ campaign.total_failed ?? 0 }}
                    </p>
                    <!-- Informative only: no failure percentage pauses a campaign. Most
                         failures are malformed numbers that never reach Meta. Use "Extrair
                         falhas" to get them with their error codes. -->
                    <p class="text-xs text-muted-foreground">
                        Taxa: {{ failureRate }}% das tentativas
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-muted-foreground transition-all"
                            :style="{ width: `${failureRate}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Daily budget -->
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 dark:border-sidebar-border"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Orçamento de hoje</span
                    >
                    <span class="text-xs text-muted-foreground">
                        {{ dailyBudget.sent_today }} /
                        {{ dailyBudget.daily_limit }} enviados hoje ·
                        {{ dailyBudget.remaining }} restantes
                        <template v-if="pendingCount > 0 && etaMinutes > 0">
                            · ~{{ etaMinutes }}min para {{ pendingCount }} na
                            fila
                        </template>
                    </span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{ width: `${budgetPercent}%` }"
                    />
                </div>
            </div>

            <!-- Delivery Funnel -->
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Funil de Entrega</span
                    >
                </div>
                <div class="p-4">
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="bar in funnelBars"
                            :key="bar.label"
                            class="flex flex-col gap-1"
                        >
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-medium text-foreground">{{
                                    bar.label
                                }}</span>
                                <span class="text-muted-foreground">
                                    {{ bar.count }} ({{
                                        safePercent(bar.count, funnelTotal)
                                    }}%)
                                </span>
                            </div>
                            <div class="h-5 overflow-hidden rounded bg-muted">
                                <div
                                    :class="[
                                        'h-full rounded transition-all',
                                        bar.color,
                                    ]"
                                    :style="{
                                        width: `${safePercent(bar.count, funnelTotal)}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Preview (collapsible) -->
            <div
                v-if="campaign.whatsapp_template?.body"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/40"
                    @click="templateExpanded = !templateExpanded"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Preview do Template</span
                    >
                    <span class="text-xs text-muted-foreground">{{
                        templateExpanded ? 'Ocultar' : 'Mostrar'
                    }}</span>
                </button>
                <div
                    v-if="templateExpanded"
                    class="border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <p class="text-sm whitespace-pre-wrap text-foreground">
                        {{ campaign.whatsapp_template.body }}
                    </p>
                </div>
            </div>

            <!-- Per-recipient table -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <!-- Toolbar -->
                <div
                    class="flex flex-col gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Destinatários ({{ messages.total }})
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    v-model="searchTerm"
                                    type="search"
                                    placeholder="Buscar nome ou telefone"
                                    class="w-56 rounded-md border border-input bg-background py-1 pr-2 pl-7 text-xs text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                />
                            </div>
                            <a
                                :href="exportUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                <Download class="h-3.5 w-3.5" /> Exportar CSV
                            </a>
                        </div>
                    </div>

                    <!-- Status filter chips -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="chip in statusChips"
                            :key="chip.value || 'all'"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                                statusFilter === chip.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/70',
                            ]"
                            @click="setStatus(chip.value)"
                        >
                            {{ chip.label }}
                            <span
                                :class="[
                                    'rounded-full px-1.5 text-[10px]',
                                    statusFilter === chip.value
                                        ? 'bg-primary-foreground/20'
                                        : 'bg-background/60',
                                ]"
                                >{{ chip.count }}</span
                            >
                        </button>
                    </div>
                </div>

                <!-- Bulk action bar -->
                <div
                    v-if="selected.size > 0"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/70 bg-primary/5 px-4 py-2 dark:border-sidebar-border"
                >
                    <span class="text-xs font-medium text-foreground">
                        {{ selected.size }} selecionado(s)
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700"
                            @click="removeSelected"
                        >
                            <Trash2 class="h-3.5 w-3.5" /> Remover do disparo
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                            @click="clearSelection"
                        >
                            <X class="h-3.5 w-3.5" /> Limpar
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                        >
                            <tr>
                                <th class="w-10 px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        :checked="allPageSelected"
                                        :disabled="
                                            selectablePageIds.length === 0
                                        "
                                        class="h-3.5 w-3.5 rounded border-input"
                                        @change="toggleAllPage"
                                    />
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Nome
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Telefone
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Mensagem
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Enviado em
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Entregue em
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Lido em
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Erro
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <tr
                                v-for="msg in messages.data"
                                :key="msg.id"
                                class="transition-colors hover:bg-muted/40"
                                :class="
                                    selected.has(msg.id) ? 'bg-primary/5' : ''
                                "
                            >
                                <td class="px-4 py-3">
                                    <input
                                        v-if="isSelectable(msg)"
                                        type="checkbox"
                                        :checked="selected.has(msg.id)"
                                        class="h-3.5 w-3.5 rounded border-input"
                                        @change="toggleRow(msg.id)"
                                    />
                                </td>
                                <td
                                    class="px-4 py-3 text-sm font-medium text-foreground"
                                >
                                    {{ msg.contact_list_entry?.name ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ msg.contact_list_entry?.phone ?? '—' }}
                                </td>
                                <!-- Clamped, full text on hover: a template body is far too
                                     long for a table cell, but with per-recipient parameters
                                     this is the only place the exact text sent is readable. -->
                                <td class="max-w-xs px-4 py-3">
                                    <span
                                        v-if="msg.rendered_message"
                                        :title="msg.rendered_message"
                                        class="line-clamp-2 text-xs whitespace-pre-line text-muted-foreground"
                                        >{{ msg.rendered_message }}</span
                                    >
                                    <span v-else class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="msgStatusBadgeClass(msg.status)"
                                        >{{
                                            msgStatusLabel(
                                                msg.status,
                                                msg.error_code,
                                            )
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ formatDateTime(msg.sent_at) ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{
                                        formatDateTime(msg.delivered_at) ?? '—'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ formatDateTime(msg.read_at) ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <span
                                        v-if="msg.error_code"
                                        :title="msg.error_message ?? undefined"
                                        class="cursor-help"
                                    >
                                        {{ msg.error_code }}:
                                        {{
                                            msg.error_message
                                                ? msg.error_message.substring(
                                                      0,
                                                      30,
                                                  ) +
                                                  (msg.error_message.length > 30
                                                      ? '...'
                                                      : '')
                                                : ''
                                        }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="
                                            msg.status === 'failed' &&
                                            !msg.sent_at &&
                                            canRetryMessages
                                        "
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-input bg-background px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                                        @click="retryMessage(msg.id)"
                                    >
                                        <RotateCcw class="h-3 w-3" /> Reenviar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="messages.data.length === 0"
                    class="py-8 text-center text-xs text-muted-foreground"
                >
                    Nenhum destinatário encontrado.
                </div>

                <!-- Pagination -->
                <div
                    v-if="messages.links?.length > 3"
                    class="flex min-w-max items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <template v-for="link in messages.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                link.active
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            ]"
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1 text-sm text-muted-foreground/40"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
