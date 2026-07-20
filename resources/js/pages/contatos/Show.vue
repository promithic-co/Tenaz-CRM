<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CollectedInformationEditor from '@/components/CollectedInformationEditor.vue';
import FollowUpStateSummary from '@/components/FollowUpStateSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { update as updateCollectedInformation } from '@/routes/contatos/collected-information';
import type { BreadcrumbItem } from '@/types';
import type { CollectedInformationItem, FollowupState } from '@/types/models';

type LeadFollowup = {
    status: string;
    count: number;
    max: number;
};

type Lead = {
    id: number;
    nome: string | null;
    whatsapp: string;
    status: string;
    operational_stage: string | null;
    updated_at: string;
    followup: LeadFollowup | null;
};

type ListMembership = {
    id: number;
    contact_list_id: number;
    opt_in_status: string;
    contact_list: { id: number; name: string };
    created_at: string;
};

type Contact = {
    id: number;
    name: string | null;
    phone: string;
    email: string | null;
    cpf: string | null;
    source: string;
    opt_in_status: 'pending' | 'opted_in' | 'opted_out';
    extra_data: Record<string, unknown> | null;
    notes: string | null;
    last_seen_at: string | null;
    created_at: string;
};

type ConversationWindow = {
    service_window: {
        status: 'open' | 'closed' | 'unknown';
        remaining_seconds: number | null;
        expires_at: string | null;
    };
    template_required: boolean;
    free_entry_point: {
        status: 'active' | 'expired' | 'unknown';
        remaining_seconds: number | null;
        source: string | null;
        expires_at: string | null;
    };
    coexistence: { enabled: boolean; note: string | null };
};

type Props = {
    contact: Contact;
    collectedInformation: CollectedInformationItem[];
    leads: Lead[];
    listMemberships: ListMembership[];
    conversationWindow?: ConversationWindow | null;
    followupState?: FollowupState | null;
    can: { manage: boolean };
};

const props = defineProps<Props>();

const latestLead = computed(() => props.leads[0] ?? null);

const followupDotStyles: Record<string, string> = {
    active: 'bg-emerald-500',
    paused: 'bg-amber-500',
    inactive: 'bg-muted-foreground/40',
};

const extraDataEntries = computed(() =>
    Object.entries(props.contact.extra_data ?? {}).filter(
        ([key]) => key !== 'collected_information',
    ),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: '/contatos' },
    {
        title: props.contact.name || props.contact.phone,
        href: `/contatos/${props.contact.id}`,
    },
];

const editOpen = ref(false);
const editForm = useForm({
    name: props.contact.name ?? '',
    phone: props.contact.phone,
    email: props.contact.email ?? '',
    cpf: props.contact.cpf ?? '',
    opt_in_status: props.contact.opt_in_status,
    notes: props.contact.notes ?? '',
});

function submitEdit(): void {
    editForm.patch(`/contatos/${props.contact.id}`, {
        onSuccess: () => {
            editOpen.value = false;
        },
    });
}

function statusBadge(s: string): string {
    if (s === 'opted_in') {
        return 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    }
    if (s === 'opted_out') {
        return 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }
    return 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
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

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    const d = new Date(value);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(2);
    return `${hh}:${mm} ${dd}/${mo}/${yy}`;
}
</script>

<template>
    <Head :title="contact.name || contact.phone" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-4 p-3 sm:p-4 lg:grid-cols-3">
            <!-- Profile -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 lg:col-span-1 dark:border-sidebar-border"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            {{ contact.name || '(sem nome)' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ contact.phone }}
                        </p>
                    </div>
                    <span :class="statusBadge(contact.opt_in_status)">{{
                        contact.opt_in_status
                    }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="text-foreground">
                            {{ contact.email || '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Origem</dt>
                        <dd class="text-foreground">{{ contact.source }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Último contato</dt>
                        <dd class="text-foreground">
                            {{ formatDate(contact.last_seen_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Criado</dt>
                        <dd class="text-foreground">
                            {{ formatDate(contact.created_at) }}
                        </dd>
                    </div>
                </dl>

                <button
                    v-if="can.manage"
                    class="mt-4 w-full rounded-md border border-input px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted"
                    @click="editOpen = !editOpen"
                >
                    {{ editOpen ? 'Cancelar edição' : 'Editar' }}
                </button>

                <form
                    v-if="editOpen"
                    @submit.prevent="submitEdit"
                    class="mt-3 flex flex-col gap-2 text-sm"
                >
                    <input
                        v-model="editForm.name"
                        placeholder="Nome"
                        class="rounded-md border border-input bg-background px-2 py-1.5"
                    />
                    <input
                        v-model="editForm.email"
                        placeholder="Email"
                        class="rounded-md border border-input bg-background px-2 py-1.5"
                    />
                    <input
                        v-model="editForm.cpf"
                        placeholder="CPF"
                        class="rounded-md border border-input bg-background px-2 py-1.5"
                    />
                    <select
                        v-model="editForm.opt_in_status"
                        class="rounded-md border border-input bg-background px-2 py-1.5"
                    >
                        <option value="pending">Pendente</option>
                        <option value="opted_in">Opt-in</option>
                        <option value="opted_out">Opt-out</option>
                    </select>
                    <textarea
                        v-model="editForm.notes"
                        placeholder="Observações"
                        rows="4"
                        class="resize-y rounded-md border border-input bg-background px-2 py-1.5"
                    />
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ editForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </form>

                <div v-if="extraDataEntries.length" class="mt-4">
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Dados extras
                    </h3>
                    <dl class="space-y-1 text-xs">
                        <div
                            v-for="[key, value] in extraDataEntries"
                            :key="key"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">{{ key }}</dt>
                            <dd class="truncate text-foreground">
                                {{ value }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Leads + memberships + window -->
            <div class="space-y-4 lg:col-span-2">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <CollectedInformationEditor
                        :items="collectedInformation"
                        :action="
                            updateCollectedInformation({ contact: contact.id })
                        "
                        :can-edit="can.manage"
                    />
                </div>

                <div
                    v-if="conversationWindow"
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <p
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Janela WhatsApp (último lead)
                    </p>
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex flex-col">
                            <dt class="text-xs text-muted-foreground">
                                Sessão 24h
                            </dt>
                            <dd class="text-foreground">
                                {{
                                    conversationWindow.service_window.status ===
                                    'open'
                                        ? `Aberta · ${formatRemaining(conversationWindow.service_window.remaining_seconds)}`
                                        : conversationWindow.service_window
                                                .status === 'closed'
                                          ? 'Fechada'
                                          : 'Sem dados'
                                }}
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-xs text-muted-foreground">
                                Template necessário
                            </dt>
                            <dd class="text-foreground">
                                {{
                                    conversationWindow.template_required
                                        ? 'Sim'
                                        : 'Não'
                                }}
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-xs text-muted-foreground">
                                Free entry 72h
                            </dt>
                            <dd class="text-foreground">
                                {{
                                    conversationWindow.free_entry_point
                                        .status === 'active'
                                        ? `Ativa · ${formatRemaining(conversationWindow.free_entry_point.remaining_seconds)}`
                                        : conversationWindow.free_entry_point
                                                .status === 'expired'
                                          ? 'Expirada'
                                          : 'Sem dados'
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="conversationWindow.free_entry_point.source"
                            class="flex flex-col"
                        >
                            <dt class="text-xs text-muted-foreground">
                                Origem
                            </dt>
                            <dd class="text-foreground">
                                {{
                                    sourceLabels[
                                        conversationWindow.free_entry_point
                                            .source
                                    ] ??
                                    conversationWindow.free_entry_point.source
                                }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="
                            conversationWindow.coexistence.enabled &&
                            conversationWindow.coexistence.note
                        "
                        class="mt-3 rounded border border-amber-300/60 bg-amber-50 px-2 py-1.5 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                    >
                        {{ conversationWindow.coexistence.note }}
                    </p>
                </div>

                <div
                    v-if="followupState && latestLead"
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Follow-up (último lead)
                        </p>
                        <Link
                            :href="`/conversas/${latestLead.id}`"
                            class="text-xs font-medium text-primary hover:underline"
                        >
                            Abrir conversa
                        </Link>
                    </div>
                    <FollowUpStateSummary :state="followupState" />
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div
                        class="border-b border-sidebar-border/70 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase dark:border-sidebar-border"
                    >
                        Observações
                    </div>
                    <p
                        v-if="contact.notes"
                        class="px-4 py-3 text-sm whitespace-pre-wrap text-foreground"
                    >
                        {{ contact.notes }}
                    </p>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        Sem observações. Use "Editar" para adicionar notas sobre
                        este contato.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div
                        class="border-b border-sidebar-border/70 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase dark:border-sidebar-border"
                    >
                        Conversas / Leads ({{ leads.length }})
                    </div>
                    <ul
                        v-if="leads.length"
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <li
                            v-for="lead in leads"
                            :key="lead.id"
                            class="px-4 py-3 text-sm hover:bg-muted/40"
                        >
                            <Link
                                :href="`/conversas/${lead.id}`"
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{ lead.nome || lead.whatsapp }}
                                    </p>
                                    <p
                                        class="flex flex-wrap items-center gap-x-1 text-xs text-muted-foreground"
                                    >
                                        <span>
                                            Status: {{ lead.status }} · Stage:
                                            {{ lead.operational_stage || '—' }}
                                        </span>
                                        <span
                                            v-if="lead.followup"
                                            class="inline-flex items-center gap-1 rounded-full bg-muted px-1.5 py-0.5 font-medium text-foreground"
                                        >
                                            <span
                                                :class="[
                                                    'size-1.5 rounded-full',
                                                    followupDotStyles[
                                                        lead.followup.status
                                                    ] ??
                                                        followupDotStyles.inactive,
                                                ]"
                                            />
                                            {{ lead.followup.count }}/{{
                                                lead.followup.max
                                            }}
                                        </span>
                                    </p>
                                </div>
                                <span class="text-xs text-muted-foreground">{{
                                    formatDate(lead.updated_at)
                                }}</span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        Sem leads vinculados.
                    </p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div
                        class="border-b border-sidebar-border/70 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase dark:border-sidebar-border"
                    >
                        Listas de Contato ({{ listMemberships.length }})
                    </div>
                    <ul
                        v-if="listMemberships.length"
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <li
                            v-for="m in listMemberships"
                            :key="m.id"
                            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-muted/40"
                        >
                            <Link
                                :href="`/listas-contato/${m.contact_list_id}`"
                                class="font-medium text-foreground"
                            >
                                {{
                                    m.contact_list?.name ||
                                    'Lista #' + m.contact_list_id
                                }}
                            </Link>
                            <span :class="statusBadge(m.opt_in_status)">{{
                                m.opt_in_status
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        Não pertence a nenhuma lista.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
