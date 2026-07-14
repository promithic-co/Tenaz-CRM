<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { AlertTriangle } from 'lucide-vue-next';

type WhatsappInstance = { id: number; name: string; display_name: string | null; meta_quality_rating: string | null };
type ContactList = { id: number; name: string };
type WhatsappTemplate = { id: number; name: string; body: string | null; variables_count: number };

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
    daily_limit: number;
    delay_between_ms: number;
    error_threshold_percent: number;
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
    contact_list_entry: { id: number; name: string; phone: string } | null;
};

type Props = {
    campaign: Campaign;
    messages: {
        data: CampaignMessage[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    repliedCount: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/campanhas' },
    { title: 'Campanhas', href: '/campanhas' },
    { title: props.campaign.name, href: `/campanhas/${props.campaign.id}` },
];

// Polling
let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.campaign.status === 'sending') {
        pollInterval = setInterval(() => {
            router.reload({ only: ['campaign', 'messages'] });
        }, 5000);
    }
});

onUnmounted(() => {
    if (pollInterval) { clearInterval(pollInterval); }
});

watch(() => props.campaign.status, (newStatus) => {
    if (newStatus !== 'sending' && pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
});

// Actions
function startCampaign(): void {
    router.post(CampaignController.start(props.campaign.id).url, {}, { preserveScroll: true });
}

function pauseCampaign(): void {
    router.post(CampaignController.pause(props.campaign.id).url, {}, { preserveScroll: true });
}

function resumeCampaign(): void {
    router.post(CampaignController.resume(props.campaign.id).url, {}, { preserveScroll: true });
}

function keepPausedForQualityRisk(): void {
    router.post(CampaignController.keepPausedForQualityRisk(props.campaign.id).url, {}, { preserveScroll: true });
}

function continueWithQualityRisk(): void {
    router.post(CampaignController.continueWithQualityRisk(props.campaign.id).url, {}, { preserveScroll: true });
}

// Status helpers
function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        draft: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        scheduled: 'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        sending: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        paused: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return map[status] ?? 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        draft: 'Rascunho', scheduled: 'Agendada', sending: 'Enviando',
        paused: 'Pausada', completed: 'Concluída', failed: 'Falha',
    };
    return map[status] ?? status;
}

function msgStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        pending: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        queued: 'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        sent: 'rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-medium text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        delivered: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        read: 'rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        skipped: 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    };
    return map[status] ?? 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
}

function msgStatusLabel(status: string): string {
    const map: Record<string, string> = {
        pending: 'Pendente', queued: 'Na fila', sent: 'Enviado',
        delivered: 'Entregue', read: 'Lido', failed: 'Falha',
        skipped: 'Ignorado (opt-out)',
    };
    return map[status] ?? status;
}

// Metrics calculations
function safePercent(numerator: number, denominator: number): number {
    if (!denominator || denominator === 0) { return 0; }
    return Math.round((numerator / denominator) * 100);
}

const sentPercent = computed(() => safePercent(props.campaign.total_sent, props.campaign.total_recipients));
const deliveryRate = computed(() => safePercent(props.campaign.total_delivered, props.campaign.total_sent));
const readRate = computed(() => safePercent(props.campaign.total_read, props.campaign.total_delivered));
const failureRate = computed(() => safePercent(props.campaign.total_failed, props.campaign.total_sent));
const hasMetaQualityRisk = computed(() => props.campaign.pause_reason_code === 'meta_quality_red_auto_pause');
const qualityRiskNeedsDecision = computed(() => hasMetaQualityRisk.value && props.campaign.status === 'paused' && !props.campaign.risk_acknowledged_at);
const funnelTotal = computed(() => Math.max(props.campaign.total_recipients, 1));
const funnelBars = computed(() => [
    { label: 'Enviados', count: props.campaign.total_sent, color: 'bg-blue-500' },
    { label: 'Entregues', count: props.campaign.total_delivered, color: 'bg-green-500' },
    { label: 'Lidos', count: props.campaign.total_read, color: 'bg-purple-500' },
    { label: 'Responderam', count: props.repliedCount, color: 'bg-orange-500' },
]);

// Template preview collapsible
const templateExpanded = ref(false);

// Filter
const statusFilter = ref('');

function applyFilter(): void {
    router.get(
        CampaignController.show(props.campaign.id).url,
        statusFilter.value ? { status: statusFilter.value } : {},
        { preserveScroll: true, preserveState: true, only: ['messages'] }
    );
}
</script>

<template>
    <Head :title="campaign.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">

            <!-- Header -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <h1 class="text-base font-semibold text-foreground">{{ campaign.name }}</h1>
                        <span :class="[statusBadgeClass(campaign.status), campaign.status === 'sending' ? 'animate-pulse' : '']">
                            {{ statusLabel(campaign.status) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="campaign.status === 'draft' || campaign.status === 'scheduled'"
                            class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-green-700"
                            @click="startCampaign"
                        >
                            Iniciar Envio
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
                    </div>
                </div>

                <!-- Meta quality risk alert -->
                <div
                    v-if="hasMetaQualityRisk"
                    class="mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-300" />
                            <div>
                                <p class="font-semibold">Risco de restricao/banimento</p>
                                <p class="mt-1 leading-5">
                                    A qualidade Meta da instancia
                                    <strong>{{ campaign.whatsapp_instance?.display_name ?? campaign.whatsapp_instance?.name ?? 'Meta' }}</strong>
                                    esta RED. A campanha foi pausada para reduzir risco de restricao ou banimento.
                                </p>
                                <p v-if="campaign.risk_acknowledged_at" class="mt-1 text-xs text-red-700 dark:text-red-300">
                                    Risco confirmado em {{ campaign.risk_acknowledged_at }}.
                                </p>
                            </div>
                        </div>
                        <div v-if="qualityRiskNeedsDecision" class="flex shrink-0 flex-wrap gap-2">
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

                <!-- Failure reason alert -->
                <div
                    v-if="campaign.failure_reason"
                    class="mx-4 mb-3 rounded-lg bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300"
                >
                    <strong>Motivo da falha:</strong> {{ campaign.failure_reason }}
                </div>

                <!-- Meta info -->
                <div class="grid grid-cols-2 gap-3 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border sm:grid-cols-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Lista</p>
                        <Link
                            v-if="campaign.contact_list"
                            :href="`/listas-contato/${campaign.contact_list.id}`"
                            class="text-sm font-medium text-primary hover:underline"
                        >
                            {{ campaign.contact_list.name }}
                        </Link>
                        <span v-else class="text-sm text-muted-foreground">—</span>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Template</p>
                        <p class="text-sm font-medium text-foreground">{{ campaign.whatsapp_template?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Instância</p>
                        <p class="text-sm font-medium text-foreground">{{ campaign.whatsapp_instance?.display_name ?? campaign.whatsapp_instance?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Limite Diário / Atraso</p>
                        <p class="text-sm font-medium text-foreground">{{ campaign.daily_limit }} msgs / {{ campaign.delay_between_ms }}ms</p>
                    </div>
                </div>
            </div>

            <!-- Metrics cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Enviados -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Enviados</p>
                    <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ campaign.total_sent ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">de {{ campaign.total_recipients ?? 0 }} ({{ sentPercent }}%)</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: `${sentPercent}%` }" />
                    </div>
                </div>

                <!-- Entregues -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Entregues</p>
                    <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ campaign.total_delivered ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">Taxa: {{ deliveryRate }}%</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-green-500 transition-all" :style="{ width: `${deliveryRate}%` }" />
                    </div>
                </div>

                <!-- Lidos -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lidos</p>
                    <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ campaign.total_read ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">Taxa: {{ readRate }}%</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-purple-500 transition-all" :style="{ width: `${readRate}%` }" />
                    </div>
                </div>

                <!-- Falhas -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Falhas</p>
                    <p
                        class="mt-1 text-2xl font-bold"
                        :class="failureRate > campaign.error_threshold_percent ? 'text-red-600 dark:text-red-400' : 'text-foreground'"
                    >
                        {{ campaign.total_failed ?? 0 }}
                    </p>
                    <p class="text-xs" :class="failureRate > campaign.error_threshold_percent ? 'text-red-500' : 'text-muted-foreground'">
                        Taxa: {{ failureRate }}% (limiar: {{ campaign.error_threshold_percent }}%)
                    </p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="failureRate > campaign.error_threshold_percent ? 'bg-red-500' : 'bg-muted-foreground'"
                            :style="{ width: `${failureRate}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Delivery Funnel -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Funil de Entrega</span>
                </div>
                <div class="p-4">
                    <div class="flex flex-col gap-3">
                        <div v-for="bar in funnelBars" :key="bar.label" class="flex flex-col gap-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-foreground">{{ bar.label }}</span>
                                <span class="text-muted-foreground">
                                    {{ bar.count }} ({{ safePercent(bar.count, funnelTotal) }}%)
                                </span>
                            </div>
                            <div class="h-5 overflow-hidden rounded bg-muted">
                                <div
                                    :class="['h-full rounded transition-all', bar.color]"
                                    :style="{ width: `${safePercent(bar.count, funnelTotal)}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Preview (collapsible) -->
            <div v-if="campaign.whatsapp_template?.body" class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/40"
                    @click="templateExpanded = !templateExpanded"
                >
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Preview do Template</span>
                    <span class="text-xs text-muted-foreground">{{ templateExpanded ? 'Ocultar' : 'Mostrar' }}</span>
                </button>
                <div v-if="templateExpanded" class="border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <p class="whitespace-pre-wrap text-sm text-foreground">{{ campaign.whatsapp_template.body }}</p>
                </div>
            </div>

            <!-- Per-recipient table -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Destinatários ({{ messages.total }})
                    </span>
                    <select
                        v-model="statusFilter"
                        class="rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        @change="applyFilter"
                    >
                        <option value="">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="queued">Na fila</option>
                        <option value="sent">Enviado</option>
                        <option value="delivered">Entregue</option>
                        <option value="read">Lido</option>
                        <option value="failed">Falha</option>
                        <option value="skipped">Ignorado (opt-out)</option>
                    </select>
                </div>

                <table class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Telefone</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Enviado em</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Entregue em</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Lido em</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Erro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="msg in messages.data"
                            :key="msg.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 text-sm font-medium text-foreground">{{ msg.contact_list_entry?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ msg.contact_list_entry?.phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="msgStatusBadgeClass(msg.status)">{{ msgStatusLabel(msg.status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ msg.sent_at ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ msg.delivered_at ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ msg.read_at ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                <span
                                    v-if="msg.error_code"
                                    :title="msg.error_message ?? undefined"
                                    class="cursor-help"
                                >
                                    {{ msg.error_code }}: {{ msg.error_message ? msg.error_message.substring(0, 30) + (msg.error_message.length > 30 ? '...' : '') : '' }}
                                </span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="messages.data.length === 0" class="py-8 text-center text-xs text-muted-foreground">
                    Nenhum destinatário encontrado.
                </div>

                <!-- Pagination -->
                <div v-if="messages.links?.length > 3" class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <template v-for="link in messages.links" :key="link.label">
                        <a
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
                        <span v-else v-html="link.label" class="px-3 py-1 text-sm text-muted-foreground/40" />
                    </template>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
