<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import VoiceCampaignController from '@/actions/App/Http/Controllers/VoiceCampaignController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type VoiceInstance = { id: number; name: string; display_name: string | null };
type ContactList = { id: number; name: string };
type ContactListEntry = { id: number; name: string; phone: string } | null;

type VoiceCampaign = {
    id: number;
    name: string;
    status: string;
    total_calls: number;
    total_answered: number;
    total_interested: number;
    total_no_answer: number;
    total_failed: number;
    delay_between_calls_ms: number;
    answer_rate: number;
    interest_rate: number;
    started_at: string | null;
    completed_at: string | null;
    paused_at: string | null;
    created_at: string;
    voice_instance: VoiceInstance | null;
    contact_list: ContactList | null;
};

type VoiceCampaignCall = {
    id: number;
    phone: string;
    contact_name: string | null;
    status: string;
    called_at: string | null;
    answered_at: string | null;
    completed_at: string | null;
    contact_list_entry: ContactListEntry;
};

type PaginatedCalls = {
    data: VoiceCampaignCall[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    voiceCampaign: VoiceCampaign;
    interestedCalls: VoiceCampaignCall[];
    allCalls: PaginatedCalls;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campanhas de Voz', href: '/campanhas-voz' },
    {
        title: props.voiceCampaign.name,
        href: `/campanhas-voz/${props.voiceCampaign.id}`,
    },
];

// Polling when sending
let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.voiceCampaign.status === 'sending') {
        pollInterval = setInterval(() => {
            router.reload({
                only: ['voiceCampaign', 'interestedCalls', 'allCalls'],
            });
        }, 5000);
    }
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

watch(
    () => props.voiceCampaign.status,
    (newStatus) => {
        if (newStatus !== 'sending' && pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        } else if (newStatus === 'sending' && !pollInterval) {
            pollInterval = setInterval(() => {
                router.reload({
                    only: ['voiceCampaign', 'interestedCalls', 'allCalls'],
                });
            }, 5000);
        }
    },
);

// Action forms
const startForm = useForm({});
const pauseForm = useForm({});
const resumeForm = useForm({});

function startCampaign(): void {
    startForm.post(VoiceCampaignController.start(props.voiceCampaign.id).url, {
        preserveScroll: true,
    });
}

function pauseCampaign(): void {
    pauseForm.post(VoiceCampaignController.pause(props.voiceCampaign.id).url, {
        preserveScroll: true,
    });
}

function resumeCampaign(): void {
    resumeForm.post(
        VoiceCampaignController.resume(props.voiceCampaign.id).url,
        { preserveScroll: true },
    );
}

// All calls tab / collapsible
const showAllCalls = ref(false);

// Status helpers
function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        draft: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        sending:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        paused: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed:
            'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        draft: 'Rascunho',
        sending: 'Ligando',
        paused: 'Pausada',
        completed: 'Concluída',
        failed: 'Falha',
    };
    return map[status] ?? status;
}

function callStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        pending:
            'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        calling:
            'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        answered:
            'rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-medium text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        interested:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        no_answer:
            'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        busy: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function callStatusLabel(status: string): string {
    const map: Record<string, string> = {
        pending: 'Pendente',
        calling: 'Ligando',
        answered: 'Atendida',
        interested: 'Interessado',
        no_answer: 'Sem resposta',
        busy: 'Ocupado',
        failed: 'Falha',
    };
    return map[status] ?? status;
}

// Metrics progress bar widths
const answerRateWidth = computed(() =>
    Math.min(props.voiceCampaign.answer_rate, 100),
);
const interestRateWidth = computed(() =>
    Math.min(props.voiceCampaign.interest_rate, 100),
);
</script>

<template>
    <Head :title="voiceCampaign.name" />

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
                            {{ voiceCampaign.name }}
                        </h1>
                        <span
                            :class="[
                                statusBadgeClass(voiceCampaign.status),
                                voiceCampaign.status === 'sending'
                                    ? 'animate-pulse'
                                    : '',
                            ]"
                        >
                            {{ statusLabel(voiceCampaign.status) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="voiceCampaign.status === 'draft'"
                            :disabled="startForm.processing"
                            class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                            @click="startCampaign"
                        >
                            {{
                                startForm.processing
                                    ? 'Iniciando...'
                                    : 'Iniciar Campanha'
                            }}
                        </button>
                        <button
                            v-if="voiceCampaign.status === 'sending'"
                            :disabled="pauseForm.processing"
                            class="rounded-md bg-yellow-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-yellow-600 disabled:opacity-50"
                            @click="pauseCampaign"
                        >
                            {{
                                pauseForm.processing ? 'Pausando...' : 'Pausar'
                            }}
                        </button>
                        <button
                            v-if="voiceCampaign.status === 'paused'"
                            :disabled="resumeForm.processing"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                            @click="resumeCampaign"
                        >
                            {{
                                resumeForm.processing
                                    ? 'Retomando...'
                                    : 'Retomar'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Meta info -->
                <div
                    class="grid grid-cols-2 gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:grid-cols-4 dark:border-sidebar-border"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Lista de Contatos
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{ voiceCampaign.contact_list?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Instância de Voz
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{
                                voiceCampaign.voice_instance?.display_name ??
                                voiceCampaign.voice_instance?.name ??
                                '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Iniciada em</p>
                        <p class="text-sm font-medium text-foreground">
                            {{ voiceCampaign.started_at ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Atraso entre ligações
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            {{
                                (voiceCampaign.delay_between_calls_ms ?? 3000) /
                                1000
                            }}s
                        </p>
                    </div>
                </div>
            </div>

            <!-- Metrics cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Total ligações -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Total de Ligações
                    </p>
                    <p class="mt-1 text-2xl font-bold text-foreground">
                        {{ voiceCampaign.total_calls ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        contatos na lista
                    </p>
                </div>

                <!-- Atendidas -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Atendidas
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-cyan-600 dark:text-cyan-400"
                    >
                        {{ voiceCampaign.total_answered ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Taxa: {{ voiceCampaign.answer_rate }}%
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-cyan-500 transition-all"
                            :style="{ width: `${answerRateWidth}%` }"
                        />
                    </div>
                </div>

                <!-- Interessados -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Interessados
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400"
                    >
                        {{ voiceCampaign.total_interested ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Taxa: {{ voiceCampaign.interest_rate }}%
                    </p>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-green-500 transition-all"
                            :style="{ width: `${interestRateWidth}%` }"
                        />
                    </div>
                </div>

                <!-- Sem resposta -->
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Sem Resposta
                    </p>
                    <p class="mt-1 text-2xl font-bold text-muted-foreground">
                        {{ voiceCampaign.total_no_answer ?? 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">não atenderam</p>
                </div>
            </div>

            <!-- Converted leads table -->
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >Leads Convertidos</span
                        >
                        <span
                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        >
                            {{ interestedCalls.length }}
                        </span>
                    </div>
                </div>

                <table class="w-full min-w-[48rem] text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
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
                                Horário da Ligação
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Conversa WhatsApp
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="call in interestedCalls"
                            :key="call.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{
                                    call.contact_name ??
                                    call.contact_list_entry?.name ??
                                    '—'
                                }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-muted-foreground"
                            >
                                {{ call.phone }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ call.called_at ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    v-if="interestedCalls.length === 0"
                    class="py-8 text-center text-xs text-muted-foreground"
                >
                    Nenhum lead convertido ainda.
                </div>
            </div>

            <!-- All calls (collapsible) -->
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/40"
                    @click="showAllCalls = !showAllCalls"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >Todas as Ligações</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ allCalls.total }}</span
                        >
                    </div>
                    <span class="text-xs text-muted-foreground">{{
                        showAllCalls ? 'Ocultar' : 'Mostrar'
                    }}</span>
                </button>

                <div
                    v-if="showAllCalls"
                    class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <table class="w-full min-w-[48rem] text-sm">
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                        >
                            <tr>
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
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Ligado em
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    Atendida em
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <tr
                                v-for="call in allCalls.data"
                                :key="call.id"
                                class="transition-colors hover:bg-muted/40"
                            >
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{
                                        call.contact_name ??
                                        call.contact_list_entry?.name ??
                                        '—'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{ call.phone }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            callStatusBadgeClass(call.status)
                                        "
                                        >{{
                                            callStatusLabel(call.status)
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ call.called_at ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ call.answered_at ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="allCalls.data.length === 0"
                        class="py-8 text-center text-xs text-muted-foreground"
                    >
                        Nenhuma ligação registrada ainda.
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="allCalls.links?.length > 3"
                        class="flex min-w-max items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                    >
                        <template
                            v-for="link in allCalls.links"
                            :key="link.label"
                        >
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
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 text-sm text-muted-foreground/40"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
