<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bot,
    ExternalLink,
    Megaphone,
    Phone,
    Play,
    RefreshCw,
    Trash2,
    UserCheck,
    UserPlus,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import StatusSelect from '@/components/StatusSelect.vue';
import TagChip from '@/components/TagChip.vue';
import TagInput from '@/components/TagInput.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { keepManual, returnToAi } from '@/routes/atendimentos';
import { show as showContact } from '@/routes/contatos';
import {
    addToContacts,
    aiMode,
    claim,
    clearHistory,
    destroy,
    pause,
    prepareCampaign,
    resume,
} from '@/routes/conversas';
import {
    disable as disableFollowup,
    pause as pauseFollowup,
    reactivate as reactivateFollowup,
    resume as resumeFollowup,
} from '@/routes/conversas/followup';
import { store as autoTagStore } from '@/routes/leads/auto-tag';
import type { ActiveConversation } from '../types';

type Props = {
    conversation: ActiveConversation;
};

const props = defineProps<Props>();

const pauseForm = useForm({});
const resumeForm = useForm({});
const claimForm = useForm({});
const pauseFollowUpForm = useForm({});
const resumeFollowUpForm = useForm({});
const disableFollowUpForm = useForm({});
const reactivateFollowUpForm = useForm({});
const clearHistoryForm = useForm({});
const deleteLeadForm = useForm({});
const prepareCampaignForm = useForm({});
const addContactForm = useForm({});
const aiModeForm = useForm({
    ai_mode: props.conversation.lead.ai_mode ?? '',
});

const returnToAiForm = useForm({});
const keepManualForm = useForm({});
const autoTagForm = useForm({});

const showClearConfirm = ref(false);
const showDeleteConfirm = ref(false);
const deleteConfirmText = ref('');

const aiModeLabels: Record<string, string> = {
    automatic: 'Automatico',
    manual: 'Manual',
    assisted: 'Assistido',
    qualify_then_handoff: 'Qualifica e transfere',
};

const stageLabels: Record<string, string> = {
    new_inbound: 'Nova entrada',
    ai_qualifying: 'IA qualificando',
    qualified_opportunity: 'Oportunidade',
    ai_followup: 'Follow-up IA',
    human_pending: 'Aguardando humano',
    human_active: 'Humano ativo',
    waiting_customer: 'Aguardando cliente',
    proposal_sent: 'Proposta/documentos',
    won: 'Ganho',
    future_opportunity: 'Futuro/sem credito',
    lost: 'Perdido',
};

const eventLabels: Record<string, string> = {
    ai_paused_manual: 'IA pausada',
    ai_resumed_manual: 'IA retomada',
    history_cleared_manual: 'Histórico limpo',
    lead_created_manual: 'Lead criado',
    lead_deleted_manual: 'Lead removido',
    lead_bulk_action: 'Ação em lote',
    followup_skipped: 'Follow-up ignorado',
};

const lead = computed(() => props.conversation.lead);
const followupStatus = computed(() => props.conversation.followupStatus);
const conversationWindow = computed(
    () => props.conversation.conversationWindow ?? null,
);

type LeadTag = {
    id: number;
    name: string;
    slug?: string;
    color?: string | null;
};
const leadTags = ref<LeadTag[]>([...(props.conversation.lead.tags ?? [])]);
const tagsSyncing = ref(false);

function syncLeadTags(payload: {
    tag_ids?: number[];
    tag_names?: string[];
}): void {
    tagsSyncing.value = true;
    router.post(`/leads/${lead.value.id}/tags`, payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            tagsSyncing.value = false;
        },
    });
}

function onLeadTagsUpdate(next: LeadTag[]): void {
    leadTags.value = next;
    syncLeadTags({ tag_ids: next.map((t) => t.id) });
}

function onLeadTagCreate(name: string): void {
    syncLeadTags({
        tag_ids: leadTags.value.map((t) => t.id),
        tag_names: [name],
    });
}

function formatRemaining(seconds: number | null): string {
    if (seconds === null || seconds <= 0) {
        return '—';
    }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) {
        return `${h}h ${m}m`;
    }
    return `${m}m`;
}

const sourceLabels: Record<string, string> = {
    ctwa_ad: 'Anúncio CTWA',
    page_cta: 'CTA da página',
    post: 'Post Facebook',
};

function updateAiMode(): void {
    aiModeForm.patch(aiMode.url({ lead: lead.value.id }), {
        preserveScroll: true,
    });
}

function submitClearHistory(): void {
    clearHistoryForm.post(clearHistory.url({ lead: lead.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showClearConfirm.value = false;
        },
    });
}

function submitDeleteLead(): void {
    if (deleteConfirmText.value.trim() !== 'EXCLUIR') {
        return;
    }
    deleteLeadForm.delete(destroy.url({ lead: lead.value.id }), {
        preserveScroll: false,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            deleteConfirmText.value = '';
            router.visit('/conversas');
        },
    });
}

function submitPrepareCampaign(): void {
    prepareCampaignForm.post(prepareCampaign.url({ lead: lead.value.id }), {
        preserveScroll: false,
    });
}

function submitClaim(): void {
    claimForm.post(claim.url({ lead: lead.value.id }), {
        preserveScroll: true,
    });
}

function formatCpf(cpf: string): string {
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function initials(name: string): string {
    return name.trim().slice(0, 2).toUpperCase() || '?';
}

function formatFollowupDate(value: string): string {
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatEventDate(value: string): string {
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <aside
        class="flex min-h-0 flex-col gap-2 overflow-y-auto border-l border-sidebar-border/70 bg-card p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-sidebar-border"
    >
        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <div class="flex flex-col items-center text-center">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-base font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                >
                    {{ initials(lead.nome) }}
                </div>
                <h2
                    class="mt-2 max-w-full truncate text-sm font-semibold text-foreground"
                >
                    {{ lead.nome }}
                </h2>
                <div
                    class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Phone class="h-3.5 w-3.5" />
                    <span>{{ lead.whatsapp }}</span>
                </div>
                <div class="mt-3">
                    <StatusSelect
                        :current-status="lead.status"
                        :available-transitions="
                            lead.available_transitions ?? []
                        "
                        :lead-id="lead.id"
                    />
                </div>
                <div
                    v-if="leadTags.length > 0"
                    class="mt-3 flex flex-wrap items-center justify-center gap-1"
                >
                    <TagChip v-for="t in leadTags" :key="t.id" :tag="t" />
                </div>
            </div>

            <div class="mt-4">
                <p
                    class="mb-1.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Tags
                </p>
                <TagInput
                    :model-value="leadTags"
                    :disabled="tagsSyncing"
                    placeholder="Adicionar tag…"
                    @update:model-value="onLeadTagsUpdate"
                    @create="onLeadTagCreate"
                />
                <form
                    class="mt-2"
                    @submit.prevent="
                        autoTagForm.post(autoTagStore.url({ lead: lead.id }), {
                            preserveScroll: true,
                        })
                    "
                >
                    <button
                        type="submit"
                        :disabled="autoTagForm.processing"
                        class="flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary/40 bg-primary/5 px-3 text-xs font-medium text-primary transition-colors hover:bg-primary/10 disabled:opacity-50"
                    >
                        <RefreshCw
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': autoTagForm.processing }"
                        />
                        Reavaliar com IA
                    </button>
                </form>
            </div>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">CPF</span>
                    <span class="font-mono text-xs text-foreground">{{
                        lead.cpf ? formatCpf(lead.cpf) : 'Não informado'
                    }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">Idade</span>
                    <span class="text-xs text-foreground">{{
                        lead.idade ? `${lead.idade} anos` : 'Não informada'
                    }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">Atendente</span>
                    <span class="truncate text-right text-xs text-foreground">{{
                        lead.assigned_user_name ?? 'Sem atendente'
                    }}</span>
                </div>
                <form
                    v-if="!lead.assigned_user_id"
                    @submit.prevent="submitClaim"
                >
                    <button
                        type="submit"
                        :disabled="claimForm.processing"
                        class="flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                    >
                        <UserCheck class="h-4 w-4" />
                        Assumir conversa
                    </button>
                </form>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">Modo IA</span>
                    <span
                        class="text-right text-xs font-medium text-foreground"
                        >{{
                            aiModeLabels[lead.effective_ai_mode] ??
                            lead.effective_ai_mode
                        }}</span
                    >
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">Etapa</span>
                    <span class="text-right text-xs text-foreground">{{
                        stageLabels[lead.operational_stage] ??
                        lead.operational_stage
                    }}</span>
                </div>
            </div>
        </section>

        <section
            v-if="conversationWindow"
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <p class="mb-2 text-xs font-semibold text-muted-foreground">
                Janela WhatsApp
            </p>
            <div class="flex flex-col gap-1.5 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">Sessão 24h</span>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                            conversationWindow.service_window.status === 'open'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : conversationWindow.service_window.status ===
                                    'closed'
                                  ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                  : 'bg-muted text-muted-foreground',
                        ]"
                    >
                        {{
                            conversationWindow.service_window.status === 'open'
                                ? 'Aberta'
                                : conversationWindow.service_window.status ===
                                    'closed'
                                  ? 'Fechada'
                                  : 'Sem dados'
                        }}
                    </span>
                </div>
                <div
                    v-if="conversationWindow.service_window.status === 'open'"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="text-muted-foreground">Restante</span>
                    <span class="text-foreground">{{
                        formatRemaining(
                            conversationWindow.service_window.remaining_seconds,
                        )
                    }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground"
                        >Template necessário</span
                    >
                    <span class="text-foreground">{{
                        conversationWindow.template_required ? 'Sim' : 'Não'
                    }}</span>
                </div>
                <div
                    v-if="
                        conversationWindow.free_entry_point.status !== 'unknown'
                    "
                    class="mt-1 flex items-center justify-between gap-3"
                >
                    <span class="text-muted-foreground">Free entry (72h)</span>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                            conversationWindow.free_entry_point.status ===
                            'active'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground',
                        ]"
                    >
                        {{
                            conversationWindow.free_entry_point.status ===
                            'active'
                                ? 'Ativa'
                                : 'Expirada'
                        }}
                    </span>
                </div>
                <div
                    v-if="
                        conversationWindow.free_entry_point.status === 'active'
                    "
                    class="flex items-center justify-between gap-3"
                >
                    <span class="text-muted-foreground">Restante</span>
                    <span class="text-foreground">{{
                        formatRemaining(
                            conversationWindow.free_entry_point
                                .remaining_seconds,
                        )
                    }}</span>
                </div>
                <div
                    v-if="conversationWindow.free_entry_point.source"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="text-muted-foreground">Origem</span>
                    <span class="text-foreground">{{
                        sourceLabels[
                            conversationWindow.free_entry_point.source
                        ] ?? conversationWindow.free_entry_point.source
                    }}</span>
                </div>
                <p
                    v-if="
                        conversationWindow.coexistence.enabled &&
                        conversationWindow.coexistence.note
                    "
                    class="mt-2 rounded border border-amber-300/60 bg-amber-50 px-2 py-1.5 text-[11px] leading-snug text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    {{ conversationWindow.coexistence.note }}
                </p>
            </div>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <p class="mb-2 text-xs font-semibold text-muted-foreground">
                Credito
            </p>
            <p class="text-xs leading-relaxed text-foreground">
                {{ lead.resumo_credito ?? 'Sem resumo de credito' }}
            </p>
        </section>

        <section
            v-if="
                conversation.active_handoff ||
                conversation.handoff_state !== 'ai_active'
            "
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <p class="mb-2 text-xs font-semibold text-muted-foreground">
                Atendimento
            </p>
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">Estado</span>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                            conversation.handoff_state === 'waiting_human'
                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                : conversation.handoff_state === 'human_active'
                                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                  : conversation.handoff_state ===
                                      'waiting_customer'
                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                    : 'bg-muted text-muted-foreground',
                        ]"
                    >
                        {{
                            conversation.handoff_state === 'waiting_human'
                                ? 'Aguardando atendimento'
                                : conversation.handoff_state === 'human_active'
                                  ? 'Em atendimento humano'
                                  : conversation.handoff_state ===
                                      'waiting_customer'
                                    ? 'Aguardando cliente'
                                    : conversation.handoff_state === 'closed'
                                      ? 'Encerrado'
                                      : 'IA ativa'
                        }}
                    </span>
                </div>
                <template v-if="conversation.active_handoff">
                    <div
                        v-if="conversation.active_handoff.reason"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Motivo</span
                        >
                        <span class="text-right text-xs text-foreground">{{
                            conversation.active_handoff.reason
                        }}</span>
                    </div>
                    <div
                        v-if="conversation.active_handoff.summary"
                        class="text-xs text-muted-foreground"
                    >
                        {{ conversation.active_handoff.summary }}
                    </div>
                    <div
                        v-if="conversation.active_handoff.assigned_user_name"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Responsavel</span
                        >
                        <span class="text-right text-xs text-foreground">{{
                            conversation.active_handoff.assigned_user_name
                        }}</span>
                    </div>
                    <div
                        v-if="conversation.active_handoff.sla_due_at"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground">SLA</span>
                        <span
                            :class="[
                                'text-right text-xs',
                                conversation.active_handoff.sla_overdue
                                    ? 'font-medium text-red-500'
                                    : 'text-foreground',
                            ]"
                        >
                            {{
                                new Date(
                                    conversation.active_handoff.sla_due_at,
                                ).toLocaleString('pt-BR', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })
                            }}
                            <span
                                v-if="conversation.active_handoff.sla_overdue"
                            >
                                (vencido)</span
                            >
                        </span>
                    </div>
                </template>
                <div
                    v-if="conversation.handoff_actions.includes('return_to_ai')"
                    class="pt-1"
                >
                    <form
                        @submit.prevent="
                            returnToAiForm.post(
                                returnToAi.url({
                                    ticket: conversation.active_handoff!.id,
                                }),
                                { preserveScroll: true },
                            )
                        "
                    >
                        <button
                            type="submit"
                            :disabled="returnToAiForm.processing"
                            title="Encerra o atendimento humano e devolve o lead para a IA continuar"
                            class="flex h-8 w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-3 text-xs font-medium text-white transition-colors hover:bg-sky-700 disabled:opacity-50"
                        >
                            Devolver para IA
                        </button>
                    </form>
                </div>
                <div
                    v-if="conversation.handoff_actions.includes('keep_manual')"
                >
                    <form
                        @submit.prevent="
                            keepManualForm.post(
                                keepManual.url({
                                    ticket: conversation.active_handoff!.id,
                                }),
                                { preserveScroll: true },
                            )
                        "
                    >
                        <button
                            type="submit"
                            :disabled="keepManualForm.processing"
                            title="Fecha o atendimento mantendo a IA pausada — você continua respondendo manualmente"
                            class="flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                        >
                            Manter manual
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <div class="mb-2 flex items-center gap-2">
                <Bot class="h-4 w-4 text-muted-foreground" />
                <p class="text-xs font-semibold text-muted-foreground">
                    Controle do Agente
                </p>
            </div>
            <select
                v-model="aiModeForm.ai_mode"
                class="mb-3 h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground"
                :disabled="aiModeForm.processing"
                @change="updateAiMode"
            >
                <option value="">Herdar da instancia</option>
                <option value="automatic">Automatico</option>
                <option value="manual">Manual</option>
                <option value="assisted">Assistido</option>
                <option value="qualify_then_handoff">
                    Qualifica e transfere
                </option>
            </select>
            <form
                v-if="!conversation.pausado"
                @submit.prevent="
                    pauseForm.post(pause.url({ lead: lead.id }), {
                        preserveScroll: true,
                    })
                "
            >
                <button
                    type="submit"
                    :disabled="pauseForm.processing"
                    class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-3 text-sm font-medium text-white transition-colors hover:bg-amber-600 disabled:opacity-50"
                >
                    Assumir e pausar IA
                </button>
            </form>
            <form
                v-else
                @submit.prevent="
                    resumeForm.post(resume.url({ lead: lead.id }), {
                        preserveScroll: true,
                    })
                "
            >
                <button
                    type="submit"
                    :disabled="resumeForm.processing"
                    class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-3 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                >
                    Retomar IA
                </button>
                <p class="mt-2 text-center text-xs font-medium text-amber-600">
                    Agente pausado - responda manualmente
                </p>
            </form>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <p class="mb-2 text-xs font-semibold text-muted-foreground">
                Controle do Follow-up
            </p>
            <p
                v-if="followupStatus === 'paused'"
                class="mb-2 text-center text-xs font-medium text-amber-600"
            >
                Follow-up pausado - aguardando retomada
            </p>
            <p
                v-else-if="followupStatus === 'inactive'"
                class="mb-2 text-center text-xs font-medium text-muted-foreground"
            >
                Follow-up desativado
            </p>

            <div class="space-y-2">
                <form
                    v-if="followupStatus === 'active'"
                    @submit.prevent="
                        pauseFollowUpForm.post(
                            pauseFollowup.url({ lead: lead.id }),
                            { preserveScroll: true },
                        )
                    "
                >
                    <button
                        type="submit"
                        :disabled="pauseFollowUpForm.processing"
                        class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-3 text-sm font-medium text-white transition-colors hover:bg-amber-600 disabled:opacity-50"
                    >
                        Pausar Follow-up
                    </button>
                </form>

                <form
                    v-if="followupStatus === 'paused'"
                    @submit.prevent="
                        resumeFollowUpForm.post(
                            resumeFollowup.url({ lead: lead.id }),
                            { preserveScroll: true },
                        )
                    "
                >
                    <button
                        type="submit"
                        :disabled="resumeFollowUpForm.processing"
                        class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-3 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                    >
                        <Play class="h-4 w-4" />
                        Retomar Follow-up
                    </button>
                </form>

                <form
                    v-if="followupStatus === 'inactive'"
                    @submit.prevent="
                        reactivateFollowUpForm.post(
                            reactivateFollowup.url({ lead: lead.id }),
                            { preserveScroll: true },
                        )
                    "
                >
                    <button
                        type="submit"
                        :disabled="reactivateFollowUpForm.processing"
                        class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Play class="h-4 w-4" />
                        Reativar Follow-up
                    </button>
                </form>

                <form
                    v-if="followupStatus !== 'inactive'"
                    @submit.prevent="
                        disableFollowUpForm.post(
                            disableFollowup.url({ lead: lead.id }),
                            { preserveScroll: true },
                        )
                    "
                >
                    <button
                        type="submit"
                        :disabled="disableFollowUpForm.processing"
                        class="flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-rose-500/50 bg-transparent px-3 text-xs font-medium text-rose-500 transition-colors hover:bg-rose-500/10 disabled:opacity-50"
                    >
                        Desativar follow-up
                    </button>
                </form>
            </div>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <div class="mb-2 flex items-center gap-2">
                <UserRound class="h-4 w-4 text-muted-foreground" />
                <p class="text-xs font-semibold text-muted-foreground">
                    Historico de Follow-ups
                </p>
            </div>
            <div
                v-if="conversation.followupHistory.length > 0"
                class="space-y-3"
            >
                <div
                    v-for="item in conversation.followupHistory"
                    :key="item.attempt"
                    class="border-l-2 border-muted pl-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-foreground"
                            >#{{ item.attempt }}</span
                        >
                        <span class="text-xs text-muted-foreground">{{
                            formatFollowupDate(item.sent_at)
                        }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                        {{ item.message_text }}
                    </p>
                </div>
            </div>
            <p v-else class="text-xs text-muted-foreground">
                Sem historico de follow-up
            </p>
        </section>

        <section
            v-if="conversation.recentEvents.length > 0"
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <p class="mb-3 text-xs font-semibold text-muted-foreground">
                Eventos recentes
            </p>
            <ul class="space-y-2 text-xs">
                <li
                    v-for="(event, idx) in conversation.recentEvents"
                    :key="idx"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="text-foreground">{{
                        eventLabels[event.event_type] ?? event.event_type
                    }}</span>
                    <span class="text-muted-foreground">{{
                        formatEventDate(event.created_at)
                    }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="conversation.canStartCampaign"
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <form @submit.prevent="submitPrepareCampaign">
                <button
                    type="submit"
                    :disabled="prepareCampaignForm.processing"
                    class="flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    <Megaphone class="h-4 w-4" />
                    Iniciar via campanha
                </button>
            </form>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <Link
                v-if="lead.contact_id"
                :href="showContact.url({ contact: lead.contact_id })"
                class="flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
            >
                <ExternalLink class="h-3.5 w-3.5" />
                Ver contato na base
            </Link>
            <form
                v-else
                @submit.prevent="
                    addContactForm.post(addToContacts.url({ lead: lead.id }), {
                        preserveScroll: true,
                    })
                "
            >
                <button
                    type="submit"
                    :disabled="addContactForm.processing"
                    class="flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-emerald-500/50 bg-emerald-500/5 px-3 text-xs font-medium text-emerald-600 transition-colors hover:bg-emerald-500/10 disabled:opacity-50 dark:text-emerald-400"
                >
                    <UserPlus class="h-3.5 w-3.5" />
                    Adicionar contato na base
                </button>
            </form>
        </section>

        <section class="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3">
            <div class="mb-2 flex items-center gap-2">
                <AlertTriangle class="h-4 w-4 text-rose-500" />
                <p class="text-xs font-semibold text-rose-500">
                    Zona de perigo
                </p>
            </div>

            <button
                type="button"
                class="mb-2 flex h-8 w-full items-center justify-center gap-2 rounded-lg border border-rose-500/40 px-3 text-xs font-medium text-rose-500 transition-colors hover:bg-rose-500/10"
                @click="showClearConfirm = true"
            >
                Limpar histórico
            </button>

            <button
                type="button"
                class="flex h-8 w-full items-center justify-center gap-2 rounded-lg bg-rose-500 px-3 text-xs font-medium text-white transition-colors hover:bg-rose-600"
                @click="showDeleteConfirm = true"
            >
                <Trash2 class="h-4 w-4" />
                Excluir lead
            </button>
        </section>

        <!-- Clear history confirmation -->
        <Dialog
            :open="showClearConfirm"
            @update:open="
                (open) => {
                    if (!open) showClearConfirm = false;
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Limpar histórico?</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    Mensagens, memória do agente e histórico de follow-up serão
                    apagados. O lead, CPF e status são preservados.
                </p>
                <DialogFooter class="gap-2 sm:gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        @click="showClearConfirm = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="clearHistoryForm.processing"
                        class="flex-1 rounded-lg bg-rose-500 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-600 disabled:opacity-50"
                        @click="submitClearHistory"
                    >
                        Apagar
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete lead confirmation -->
        <Dialog
            :open="showDeleteConfirm"
            @update:open="
                (open) => {
                    if (!open) {
                        showDeleteConfirm = false;
                        deleteConfirmText = '';
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Excluir lead?</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    O lead será removido (soft delete). Timeline, follow-ups e
                    auditoria são preservados. Digite
                    <span class="font-mono font-semibold text-rose-500"
                        >EXCLUIR</span
                    >
                    para confirmar.
                </p>
                <input
                    v-model="deleteConfirmText"
                    type="text"
                    class="h-8 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    placeholder="EXCLUIR"
                />
                <DialogFooter class="gap-2 sm:gap-2">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        @click="
                            showDeleteConfirm = false;
                            deleteConfirmText = '';
                        "
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="
                            deleteLeadForm.processing ||
                            deleteConfirmText.trim() !== 'EXCLUIR'
                        "
                        class="flex-1 rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-700 disabled:opacity-50"
                        @click="submitDeleteLead"
                    >
                        Excluir
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </aside>
</template>
