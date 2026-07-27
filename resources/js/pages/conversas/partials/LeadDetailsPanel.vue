<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bot,
    ExternalLink,
    History,
    ListPlus,
    Megaphone,
    Phone,
    Play,
    Plus,
    RefreshCw,
    SlidersHorizontal,
    StickyNote,
    Trash2,
    UserPlus,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CollectedInformationEditor from '@/components/CollectedInformationEditor.vue';
import FollowUpStateSummary from '@/components/FollowUpStateSummary.vue';
import StatusSelect from '@/components/StatusSelect.vue';
import TagInput from '@/components/TagInput.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    formatCents,
    parseCents,
    SELECTABLE_SESSION_OUTCOMES,
    SESSION_OUTCOME_LABELS,
    SESSION_REASON_LABELS,
} from '@/lib/conversation-session';
import type { LeadCustomField } from '@/lib/custom-fields';
import { keepManual, returnToAi } from '@/routes/atendimentos';
import { show as showContact } from '@/routes/contatos';
import {
    addToContacts,
    aiMode,
    clearHistory,
    destroy,
    pause,
    prepareCampaign,
    resume,
} from '@/routes/conversas';
import { update as updateCollectedInformation } from '@/routes/conversas/collected-information';
import { update as updateCustomFields } from '@/routes/conversas/custom-fields';
import {
    disable as disableFollowup,
    pause as pauseFollowup,
    reactivate as reactivateFollowup,
    resume as resumeFollowup,
} from '@/routes/conversas/followup';
import { update as updateNotes } from '@/routes/conversas/notes';
import {
    close as closeSession,
    store as storeSession,
} from '@/routes/conversas/sessions';
import { update as updateSessionValue } from '@/routes/conversas/sessions/value';
import { store as autoTagStore } from '@/routes/leads/auto-tag';
import { store as storeListEntry } from '@/routes/listas-contato/entries';
import type {
    ActiveConversation,
    ConversationHistory,
    ConversationSessionOutcome,
} from '../types';
import PanelSection from './PanelSection.vue';

type Props = {
    conversation: ActiveConversation;
};

const props = defineProps<Props>();

const pauseForm = useForm({});
const resumeForm = useForm({});
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

const newSessionForm = useForm({});
const closeSessionForm = useForm<{ outcome: ConversationSessionOutcome }>({
    outcome: 'manual_close',
});

const showClearConfirm = ref(false);
const showDeleteConfirm = ref(false);
const deleteConfirmText = ref('');

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

/**
 * History entries arrive already labelled by ConversationHistoryBuilder, so the
 * panel only picks the colour. Keeping the wording server-side is what lets the
 * whitelist of visible event types and its labels be the same table.
 */
const HISTORY_SEVERITY_DOT: Record<string, string> = {
    info: 'bg-muted-foreground/40',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    error: 'bg-rose-500',
};

const HISTORY_SEVERITY_TEXT: Record<string, string> = {
    info: 'text-foreground',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    error: 'text-rose-600 dark:text-rose-400',
};

const lead = computed(() => props.conversation.lead);
const followupStatus = computed(() => props.conversation.followupStatus);
const conversationWindow = computed(
    () => props.conversation.conversationWindow ?? null,
);

const sessions = computed(() => props.conversation.sessions ?? []);
const openSession = computed(
    () => sessions.value.find((session) => session.status === 'open') ?? null,
);

const history = computed<ConversationHistory>(
    () =>
        props.conversation.history ?? {
            entries: [],
            truncated: false,
            event_retention_days: 0,
        },
);
const historyEntries = computed(() => history.value.entries);

const notesForm = useForm({ notes: props.conversation.lead.notes ?? '' });
const notesSaved = ref(false);

/**
 * The panel is keyed by lead id, so switching conversations already builds a fresh
 * component. This watch only matters when the same conversation reloads: the textarea
 * must pick up a note saved elsewhere, but an operator mid-sentence must not have
 * their draft yanked out from under them.
 */
watch(
    () => props.conversation.lead.notes,
    (next) => {
        if (!notesForm.isDirty) {
            notesForm.defaults({ notes: next ?? '' });
            notesForm.reset();
        }
    },
);

function submitNotes(): void {
    notesSaved.value = false;

    notesForm.patch(updateNotes.url({ lead: lead.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            notesForm.defaults({ notes: notesForm.notes });
            notesSaved.value = true;
            setTimeout(() => {
                notesSaved.value = false;
            }, 2500);
        },
    });
}

// Static lists only; empty when the tenant has none, and the section hides itself.
const contactLists = computed(() => props.conversation.contact_lists ?? []);
const selectedListId = ref<number | null>(null);
const listEntryForm = useForm({
    phone: props.conversation.lead.whatsapp,
    name: props.conversation.lead.nome,
});

/**
 * Extra lead fields defined by the tenant in configuracoes/campos. The section
 * hides itself when the tenant defined none, so the panel doesn't grow an empty
 * box for the majority who never touch the feature.
 */
const customFields = computed<LeadCustomField[]>(
    () => props.conversation.custom_fields ?? [],
);
const editableCustomFields = computed(() =>
    customFields.value.filter((field) => field.editable),
);
const readOnlyCustomFields = computed(() =>
    customFields.value.filter(
        (field) => !field.editable && field.value !== null,
    ),
);

/**
 * `json` fields are excluded from the form: agent tools own them, and a textarea
 * of raw JSON is not a thing to hand an atendente mid-conversation.
 */
function customFieldFormValues(): Record<string, string | boolean | null> {
    return Object.fromEntries(
        editableCustomFields.value.map((field) => [
            field.slug,
            field.type === 'boolean'
                ? field.value === true
                : field.value === null || field.value === undefined
                  ? ''
                  : String(field.value),
        ]),
    );
}

const customFieldsForm = useForm<{
    values: Record<string, string | boolean | null>;
}>({
    values: customFieldFormValues(),
});
const customFieldsSaved = ref(false);

/**
 * Same contract as the notes textarea: pick up values written elsewhere, but never
 * discard what the operator is halfway through typing.
 */
watch(
    () => props.conversation.custom_fields,
    () => {
        if (!customFieldsForm.isDirty) {
            customFieldsForm.defaults({ values: customFieldFormValues() });
            customFieldsForm.reset();
        }
    },
);

function submitCustomFields(): void {
    customFieldsSaved.value = false;

    customFieldsForm.patch(updateCustomFields.url({ lead: lead.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            // Spread, don't hand over the live object: `defaults()` merges shallowly,
            // so sharing the reference would make every later edit invisible to isDirty.
            customFieldsForm.defaults({
                values: { ...customFieldsForm.values },
            });
            customFieldsSaved.value = true;
            setTimeout(() => {
                customFieldsSaved.value = false;
            }, 2500);
        },
    });
}

function customFieldError(slug: string): string | undefined {
    return (customFieldsForm.errors as Record<string, string | undefined>)[
        `values.${slug}`
    ];
}

function readOnlyCustomFieldText(field: LeadCustomField): string {
    return typeof field.value === 'object' && field.value !== null
        ? JSON.stringify(field.value, null, 2)
        : String(field.value);
}

function submitAddToList(): void {
    if (!selectedListId.value) {
        return;
    }

    listEntryForm.phone = lead.value.whatsapp;
    listEntryForm.name = lead.value.nome;

    listEntryForm.post(storeListEntry.url({ list: selectedListId.value }), {
        preserveScroll: true,
        onSuccess: () => {
            selectedListId.value = null;
        },
    });
}

function submitNewSession(): void {
    newSessionForm.post(storeSession.url({ lead: lead.value.id }), {
        preserveScroll: true,
    });
}

function submitCloseSession(): void {
    if (!openSession.value) {
        return;
    }
    closeSessionForm.post(
        closeSession.url({
            lead: lead.value.id,
            session: openSession.value.id,
        }),
        { preserveScroll: true },
    );
}

/**
 * Amount and forecast on the open atendimento. The text field holds whatever the operator
 * typed; only `parseCents` output crosses the wire, so the server never guesses a locale.
 */
const valueInput = ref(
    openSession.value?.value_cents != null
        ? (openSession.value.value_cents / 100).toFixed(2).replace('.', ',')
        : '',
);
const valueForm = useForm<{
    value_cents: number | null;
    expected_close_at: string | null;
}>({
    value_cents: openSession.value?.value_cents ?? null,
    expected_close_at: openSession.value?.expected_close_at ?? null,
});
const valueSaved = ref(false);

/**
 * A close (or a new atendimento) swaps the session under the form. Reset from the incoming
 * row unless the operator has unsaved edits — their typing outranks a background refresh.
 */
watch(
    () => openSession.value?.id,
    () => {
        if (valueForm.isDirty) {
            return;
        }
        const cents = openSession.value?.value_cents ?? null;
        valueInput.value =
            cents != null ? (cents / 100).toFixed(2).replace('.', ',') : '';
        valueForm.defaults({
            value_cents: cents,
            expected_close_at: openSession.value?.expected_close_at ?? null,
        });
        valueForm.reset();
    },
);

function onValueInput(): void {
    valueForm.value_cents = parseCents(valueInput.value);
}

/** Reformat to pt-BR on blur so the field shows the number that was actually stored. */
function onValueBlur(): void {
    valueInput.value =
        valueForm.value_cents != null
            ? (valueForm.value_cents / 100).toFixed(2).replace('.', ',')
            : '';
}

function submitSessionValue(): void {
    if (!openSession.value) {
        return;
    }
    valueSaved.value = false;

    valueForm.patch(
        updateSessionValue.url({
            lead: lead.value.id,
            session: openSession.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                // Spread: `defaults()` merges shallowly, and handing over the live form
                // object would make isDirty blind to every later edit.
                valueForm.defaults({
                    value_cents: valueForm.value_cents,
                    expected_close_at: valueForm.expected_close_at,
                });
                valueSaved.value = true;
                setTimeout(() => {
                    valueSaved.value = false;
                }, 2500);
            },
        },
    );
}

/** Shared by the atendimento list and the history: dd/mm hh:mm, no year. */
function formatShortDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

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

function initials(name: string): string {
    return name.trim().slice(0, 2).toUpperCase() || '?';
}
</script>

<template>
    <aside
        class="flex min-h-0 flex-col gap-2 overflow-y-auto border-l border-sidebar-border/70 bg-card p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-sidebar-border"
    >
        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <!-- Identity row. Kept to two lines: the panel is a working surface, so
                 vertical space here is space taken from the sections below. The tag
                 chips are not repeated above the input — TagInput renders them itself. -->
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                >
                    {{ initials(lead.nome) }}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-sm font-semibold text-foreground">
                        {{ lead.nome }}
                    </h2>
                    <div
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Phone class="h-3 w-3 shrink-0" />
                        <span class="truncate">{{ lead.whatsapp }}</span>
                    </div>
                </div>
                <div class="w-28 shrink-0">
                    <StatusSelect
                        :current-status="lead.status"
                        :available-transitions="
                            lead.available_transitions ?? []
                        "
                        :lead-id="lead.id"
                    />
                </div>
            </div>

            <div class="mt-2.5 flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <TagInput
                        :model-value="leadTags"
                        :disabled="tagsSyncing"
                        placeholder="Adicionar tag…"
                        @update:model-value="onLeadTagsUpdate"
                        @create="onLeadTagCreate"
                    />
                </div>
                <form
                    @submit.prevent="
                        autoTagForm.post(autoTagStore.url({ lead: lead.id }), {
                            preserveScroll: true,
                        })
                    "
                >
                    <button
                        type="submit"
                        :disabled="autoTagForm.processing"
                        title="Reavaliar as tags deste lead com IA"
                        aria-label="Reavaliar tags com IA"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-dashed border-primary/40 bg-primary/5 text-primary transition-colors hover:bg-primary/10 disabled:opacity-50"
                    >
                        <RefreshCw
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': autoTagForm.processing }"
                        />
                    </button>
                </form>
            </div>
        </section>

        <section
            class="rounded-lg border border-sidebar-border/70 bg-background/40 p-3 dark:border-sidebar-border"
        >
            <CollectedInformationEditor
                :items="lead.collected_information"
                :action="updateCollectedInformation({ lead: lead.id })"
                can-edit
                compact
            />

            <!-- Atendente and Modo IA are not repeated here: the thread header
                 states them and offers the action, and the AI mode is editable
                 under "Controle do Agente". -->
            <div
                class="mt-3 flex items-center justify-between gap-3 border-t border-sidebar-border/70 pt-3 text-sm dark:border-sidebar-border"
            >
                <span class="text-muted-foreground">Etapa</span>
                <span class="text-right text-xs text-foreground">{{
                    stageLabels[lead.operational_stage] ??
                    lead.operational_stage
                }}</span>
            </div>
        </section>

        <PanelSection section-key="notas" title="Notas" default-open>
            <template #icon>
                <StickyNote class="h-4 w-4 text-muted-foreground" />
            </template>
            <template #meta>
                <span
                    v-if="notesSaved"
                    class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                >
                    Salvo
                </span>
                <span
                    v-else-if="notesForm.isDirty"
                    class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-500"
                >
                    Não salvo
                </span>
            </template>

            <form @submit.prevent="submitNotes">
                <textarea
                    v-model="notesForm.notes"
                    rows="4"
                    maxlength="5000"
                    placeholder="Anotações da equipe sobre este contato…"
                    class="w-full resize-y rounded-md border border-input bg-background px-2.5 py-2 text-xs leading-relaxed text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                />
                <p
                    v-if="notesForm.errors.notes"
                    class="mt-1 text-xs text-rose-500"
                >
                    {{ notesForm.errors.notes }}
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <button
                        type="submit"
                        :disabled="notesForm.processing || !notesForm.isDirty"
                        class="h-8 flex-1 rounded-lg bg-primary px-3 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        {{
                            notesForm.processing ? 'Salvando…' : 'Salvar notas'
                        }}
                    </button>
                    <button
                        v-if="notesForm.isDirty"
                        type="button"
                        class="h-8 rounded-lg border border-input px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        @click="notesForm.reset()"
                    >
                        Descartar
                    </button>
                </div>
            </form>
        </PanelSection>

        <PanelSection
            v-if="customFields.length > 0"
            section-key="campos-adicionais"
            title="Campos adicionais"
            default-open
        >
            <template #icon>
                <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
            </template>
            <template #meta>
                <span
                    v-if="customFieldsSaved"
                    class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                >
                    Salvo
                </span>
                <span
                    v-else-if="customFieldsForm.isDirty"
                    class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-500"
                >
                    Não salvo
                </span>
            </template>

            <form class="space-y-2.5" @submit.prevent="submitCustomFields">
                <div
                    v-for="field in editableCustomFields"
                    :key="field.id"
                    class="space-y-1"
                >
                    <label
                        :for="`custom-field-${field.id}`"
                        class="flex items-center gap-1 text-[11px] text-muted-foreground"
                    >
                        {{ field.label }}
                        <span v-if="field.is_required" class="text-amber-500"
                            >*</span
                        >
                    </label>

                    <label
                        v-if="field.type === 'boolean'"
                        class="flex cursor-pointer items-center gap-2 text-xs text-foreground"
                    >
                        <input
                            :id="`custom-field-${field.id}`"
                            v-model="customFieldsForm.values[field.slug]"
                            type="checkbox"
                            class="h-3.5 w-3.5 rounded border-input"
                        />
                        {{
                            customFieldsForm.values[field.slug] ? 'Sim' : 'Não'
                        }}
                    </label>

                    <select
                        v-else-if="field.type === 'select'"
                        :id="`custom-field-${field.id}`"
                        v-model="customFieldsForm.values[field.slug]"
                        class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    >
                        <option value="">—</option>
                        <option
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <input
                        v-else
                        :id="`custom-field-${field.id}`"
                        v-model="customFieldsForm.values[field.slug]"
                        :type="
                            field.type === 'number'
                                ? 'number'
                                : field.type === 'date'
                                  ? 'date'
                                  : 'text'
                        "
                        :step="field.type === 'number' ? 'any' : undefined"
                        class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />

                    <p
                        v-if="customFieldError(field.slug)"
                        class="text-xs text-rose-500"
                    >
                        {{ customFieldError(field.slug) }}
                    </p>
                </div>

                <div
                    v-if="editableCustomFields.length > 0"
                    class="flex items-center gap-2 pt-0.5"
                >
                    <button
                        type="submit"
                        :disabled="
                            customFieldsForm.processing ||
                            !customFieldsForm.isDirty
                        "
                        class="h-8 flex-1 rounded-lg bg-primary px-3 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        {{
                            customFieldsForm.processing
                                ? 'Salvando…'
                                : 'Salvar campos'
                        }}
                    </button>
                    <button
                        v-if="customFieldsForm.isDirty"
                        type="button"
                        class="h-8 rounded-lg border border-input px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        @click="customFieldsForm.reset()"
                    >
                        Descartar
                    </button>
                </div>
            </form>

            <!-- Fields the agent's own tools fill in. Shown for context, not edited. -->
            <div
                v-if="readOnlyCustomFields.length > 0"
                class="mt-3 space-y-1.5 border-t border-border pt-2.5"
            >
                <div v-for="field in readOnlyCustomFields" :key="field.id">
                    <p class="text-[11px] text-muted-foreground">
                        {{ field.label }}
                    </p>
                    <pre
                        class="mt-0.5 max-h-32 overflow-auto rounded-md bg-muted/40 p-2 font-mono text-[10px] leading-relaxed text-foreground"
                        >{{ readOnlyCustomFieldText(field) }}</pre
                    >
                </div>
            </div>
        </PanelSection>

        <PanelSection
            v-if="contactLists.length > 0"
            section-key="listas"
            title="Listas de contato"
        >
            <template #icon>
                <ListPlus class="h-4 w-4 text-muted-foreground" />
            </template>

            <form
                class="flex items-center gap-2"
                @submit.prevent="submitAddToList"
            >
                <select
                    v-model="selectedListId"
                    class="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                    :disabled="listEntryForm.processing"
                >
                    <option :value="null" disabled>Adicionar à lista…</option>
                    <option
                        v-for="list in contactLists"
                        :key="list.id"
                        :value="list.id"
                    >
                        {{ list.name }} ({{ list.entries_count }})
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="listEntryForm.processing || !selectedListId"
                    class="h-8 shrink-0 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Adicionar
                </button>
            </form>
            <p
                v-if="listEntryForm.errors.phone"
                class="mt-1 text-xs text-rose-500"
            >
                {{ listEntryForm.errors.phone }}
            </p>
            <p class="mt-2 text-[11px] text-muted-foreground">
                Somente listas estáticas. Listas dinâmicas são recalculadas
                pelos filtros.
            </p>
        </PanelSection>

        <PanelSection
            v-if="conversationWindow"
            section-key="janela"
            title="Janela WhatsApp"
        >
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
        </PanelSection>

        <PanelSection
            v-if="lead.agent_niche !== 'generic'"
            section-key="credito"
            title="Credito"
            default-open
        >
            <p class="text-xs leading-relaxed text-foreground">
                {{ lead.resumo_credito ?? 'Sem resumo de credito' }}
            </p>
        </PanelSection>

        <PanelSection
            v-if="
                conversation.active_handoff ||
                conversation.handoff_state !== 'ai_active'
            "
            section-key="atendimento"
            title="Atendimento"
            default-open
        >
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
        </PanelSection>

        <PanelSection section-key="sessoes" title="Atendimentos">
            <template #icon>
                <UserRound class="h-4 w-4 text-muted-foreground" />
            </template>

            <div
                v-if="openSession"
                class="mb-3 space-y-2 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-2.5"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-foreground"
                        >Atendimento #{{ openSession.number }} · aberto</span
                    >
                    <span
                        v-if="openSession.is_returning"
                        class="rounded-full bg-violet-500/10 px-2 py-0.5 text-[10px] font-medium text-violet-500 dark:text-violet-400"
                        >Retornante</span
                    >
                </div>
                <p class="text-[11px] text-muted-foreground">
                    {{ SESSION_REASON_LABELS[openSession.open_reason] }} ·
                    {{ formatShortDateTime(openSession.opened_at) }}
                </p>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <label class="space-y-1">
                        <span
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                            >Valor</span
                        >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-xs text-muted-foreground"
                                >R$</span
                            >
                            <input
                                v-model="valueInput"
                                type="text"
                                inputmode="decimal"
                                placeholder="0,00"
                                class="h-8 w-full rounded-md border border-input bg-background pr-2 pl-7 text-xs text-foreground"
                                :disabled="valueForm.processing"
                                @input="onValueInput"
                                @blur="onValueBlur"
                            />
                        </div>
                    </label>
                    <label class="space-y-1">
                        <span
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                            >Previsão</span
                        >
                        <input
                            v-model="valueForm.expected_close_at"
                            type="date"
                            class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground"
                            :disabled="valueForm.processing"
                        />
                    </label>
                </div>
                <p
                    v-if="valueForm.errors.value_cents"
                    class="text-[11px] text-rose-500"
                >
                    {{ valueForm.errors.value_cents }}
                </p>
                <p
                    v-else-if="valueForm.errors.expected_close_at"
                    class="text-[11px] text-rose-500"
                >
                    {{ valueForm.errors.expected_close_at }}
                </p>
                <div class="flex items-center justify-end gap-2">
                    <span
                        v-if="valueSaved"
                        class="text-[11px] text-emerald-600 dark:text-emerald-400"
                        >Salvo</span
                    >
                    <button
                        type="button"
                        :disabled="valueForm.processing || !valueForm.isDirty"
                        class="h-7 rounded-md border border-input px-2.5 text-[11px] font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                        @click="submitSessionValue"
                    >
                        Salvar valor
                    </button>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <select
                        v-model="closeSessionForm.outcome"
                        class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                        :disabled="closeSessionForm.processing"
                    >
                        <option
                            v-for="outcome in SELECTABLE_SESSION_OUTCOMES"
                            :key="outcome"
                            :value="outcome"
                        >
                            {{ SESSION_OUTCOME_LABELS[outcome] }}
                        </option>
                    </select>
                    <button
                        type="button"
                        :disabled="closeSessionForm.processing"
                        class="h-8 shrink-0 rounded-md bg-emerald-600 px-3 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
                        @click="submitCloseSession"
                    >
                        Encerrar
                    </button>
                </div>
            </div>

            <form v-else class="mb-3" @submit.prevent="submitNewSession">
                <button
                    type="submit"
                    :disabled="newSessionForm.processing"
                    class="flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary/40 bg-primary/5 px-3 text-xs font-medium text-primary transition-colors hover:bg-primary/10 disabled:opacity-50"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Novo atendimento
                </button>
            </form>

            <div v-if="sessions.length > 0" class="space-y-2">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="border-l-2 border-muted pl-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-foreground"
                            >#{{ session.number }}</span
                        >
                        <div class="flex items-center gap-1.5">
                            <span
                                v-if="session.value_cents !== null"
                                class="text-[11px] font-medium text-foreground tabular-nums"
                                >{{ formatCents(session.value_cents) }}</span
                            >
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                    session.status === 'open'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{
                                    session.status === 'open'
                                        ? 'Aberto'
                                        : 'Fechado'
                                }}
                            </span>
                        </div>
                    </div>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        {{ SESSION_REASON_LABELS[session.open_reason] }}
                        <span v-if="session.outcome">
                            ·
                            {{ SESSION_OUTCOME_LABELS[session.outcome] }}</span
                        >
                    </p>
                    <p class="text-[11px] text-muted-foreground/70">
                        {{ formatShortDateTime(session.opened_at) }}
                        <span v-if="session.closed_at">
                            → {{ formatShortDateTime(session.closed_at) }}</span
                        >
                    </p>
                </div>
            </div>
            <p v-else class="text-xs text-muted-foreground">
                Nenhum atendimento registrado.
            </p>
        </PanelSection>

        <PanelSection section-key="agente" title="Controle do Agente">
            <template #icon>
                <Bot class="h-4 w-4 text-muted-foreground" />
            </template>
            <template #meta>
                <span
                    v-if="conversation.pausado"
                    class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-500"
                >
                    Pausada
                </span>
            </template>
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
        </PanelSection>

        <PanelSection section-key="followup" title="Controle do Follow-up">
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

            <div
                class="mt-3 border-t border-sidebar-border/70 pt-3 dark:border-sidebar-border"
            >
                <FollowUpStateSummary :state="conversation.followupState" />
            </div>
        </PanelSection>

        <!-- One timeline instead of three boxes: agent events, atendimento cycles,
             human tickets and follow-ups all land here in the order they happened. -->
        <PanelSection section-key="historico" title="Histórico">
            <template #icon>
                <History class="h-4 w-4 text-muted-foreground" />
            </template>
            <template #meta>
                <span
                    v-if="historyEntries.length > 0"
                    class="text-[10px] text-muted-foreground"
                >
                    {{ historyEntries.length
                    }}{{ history.truncated ? '+' : '' }}
                </span>
            </template>

            <ol v-if="historyEntries.length > 0" class="space-y-2">
                <li
                    v-for="entry in historyEntries"
                    :key="entry.id"
                    class="flex gap-2"
                >
                    <span
                        :class="[
                            'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                            HISTORY_SEVERITY_DOT[entry.severity],
                        ]"
                    />
                    <div class="min-w-0 flex-1">
                        <div
                            class="flex items-baseline justify-between gap-2 text-xs"
                        >
                            <span
                                :class="[
                                    'min-w-0 truncate',
                                    HISTORY_SEVERITY_TEXT[entry.severity],
                                ]"
                            >
                                {{ entry.label }}
                            </span>
                            <span
                                class="shrink-0 text-[10px] text-muted-foreground"
                            >
                                {{ formatShortDateTime(entry.at) }}
                            </span>
                        </div>
                        <p
                            v-if="entry.detail"
                            class="line-clamp-2 text-[11px] text-muted-foreground"
                        >
                            {{ entry.detail }}
                        </p>
                    </div>
                </li>
            </ol>
            <p v-else class="text-xs text-muted-foreground">
                Nada registrado ainda.
            </p>

            <p
                v-if="history.event_retention_days > 0"
                class="mt-2.5 border-t border-border pt-2 text-[10px] text-muted-foreground"
            >
                Eventos do agente ficam
                {{ history.event_retention_days }} dias. Atendimentos,
                escalações e follow-ups não expiram.
            </p>
        </PanelSection>

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

        <PanelSection section-key="perigo" title="Zona de perigo" tone="danger">
            <template #icon>
                <AlertTriangle class="h-4 w-4 text-rose-500" />
            </template>

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
        </PanelSection>

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
