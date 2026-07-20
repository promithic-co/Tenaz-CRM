<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { MessageSquare, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { bulkAction, index, show, store, transfer } from '@/routes/conversas';
import type { QueryParams } from '@/wayfinder';
import type {
    ConversationFilters,
    LeadPaginator,
    InboxLead,
    TransferTarget,
} from '../types';

type Props = {
    leads: LeadPaginator;
    filters: ConversationFilters;
    instances: Array<{ name: string; label: string }>;
    activeLeadId: number | null;
    transferTargets: TransferTarget[];
};

const props = defineProps<Props>();

const statusFilters = [
    { key: 'todos', label: 'Todas' },
    { key: 'novo', label: 'Novos' },
    { key: 'qualificado', label: 'Qualificados' },
    { key: 'followup', label: 'Follow-up' },
    { key: 'escalado', label: 'Escalados' },
    { key: 'optou_sair', label: 'Opt-out' },
];

const bulkActionLabels: Record<string, string> = {
    'pause-ai': 'Pausar IA',
    'resume-ai': 'Retomar IA',
    'pause-followup': 'Pausar follow-up',
    'resume-followup': 'Retomar follow-up',
    'disable-followup': 'Desativar follow-up',
    delete: 'Excluir',
};

const searchQuery = ref(props.filters.search ?? '');
const showNewContactModal = ref(false);
const selectedLeadIds = ref<Set<number>>(new Set());
const bulkActionChoice = ref<string>('pause-ai');

const newContactForm = useForm({
    nome: '',
    whatsapp: '',
    cpf: '',
    evolution_instance: props.filters.instance ?? '',
});

const bulkForm = useForm({
    lead_ids: [] as number[],
    action: 'pause-ai',
});

const transferTargetId = ref<number | null>(null);
const transferForm = useForm({
    lead_ids: [] as number[],
    target_type: 'user',
    target_id: 0,
});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.filters.search,
    (value) => {
        searchQuery.value = value ?? '';
    },
);

watch(searchQuery, (value) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        visitInbox({ search: value });
    }, 300);
});

watch(
    () => props.filters.instance,
    (value) => {
        newContactForm.evolution_instance = value ?? '';
    },
);

const selectedCount = computed(() => selectedLeadIds.value.size);

function filterQuery(overrides: QueryParams = {}): QueryParams {
    return {
        status: props.filters.status,
        instance: props.filters.instance || undefined,
        search: searchQuery.value || undefined,
        sort: props.filters.sort || 'last_interaction_at',
        direction: props.filters.direction || 'desc',
        ...overrides,
    };
}

function visitInbox(overrides: QueryParams = {}): void {
    router.get(index.url(), filterQuery(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function filterHref(status: string): string {
    return index.url({ query: filterQuery({ status }) });
}

function leadHref(lead: InboxLead): string {
    return show.url({ lead: lead.id }, { query: filterQuery() });
}

function onInstanceChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    visitInbox({ instance: target.value || null });
}

function automationLabel(lead: InboxLead): string | null {
    if (lead.pausado) {
        return 'Pausado';
    }

    if (lead.effective_ai_mode === 'manual') {
        return 'Manual';
    }

    if (lead.effective_ai_mode === 'assisted') {
        return 'Assistido';
    }

    if (lead.effective_ai_mode === 'qualify_then_handoff') {
        return 'IA + humano';
    }

    if (lead.followup_status === 'active') {
        return `Follow-up ${lead.followup_count}x`;
    }

    return null;
}

function initials(name: string): string {
    return name.trim().charAt(0).toUpperCase() || '?';
}

function toggleLeadSelection(leadId: number, checked: boolean): void {
    const next = new Set(selectedLeadIds.value);
    if (checked) {
        next.add(leadId);
    } else {
        next.delete(leadId);
    }
    selectedLeadIds.value = next;
}

function clearSelection(): void {
    selectedLeadIds.value = new Set();
}

function submitBulkAction(): void {
    if (selectedLeadIds.value.size === 0) {
        return;
    }

    bulkForm.lead_ids = Array.from(selectedLeadIds.value);
    bulkForm.action = bulkActionChoice.value;

    bulkForm.post(bulkAction.url(), {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
        },
    });
}

function submitTransfer(): void {
    if (selectedLeadIds.value.size === 0 || !transferTargetId.value) {
        return;
    }

    transferForm.lead_ids = Array.from(selectedLeadIds.value);
    transferForm.target_type = 'user';
    transferForm.target_id = transferTargetId.value;

    transferForm.post(transfer.url(), {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
            transferTargetId.value = null;
        },
    });
}

function submitNewContact(): void {
    newContactForm.post(store.url(), {
        preserveScroll: false,
        onSuccess: () => {
            showNewContactModal.value = false;
            newContactForm.reset('nome', 'whatsapp', 'cpf');
        },
    });
}

function openNewContactModal(): void {
    newContactForm.clearErrors();
    showNewContactModal.value = true;
}
</script>

<template>
    <aside
        class="flex min-h-0 min-w-0 flex-col border-sidebar-border/70 bg-card lg:border-r dark:border-sidebar-border"
    >
        <div
            class="shrink-0 border-b border-sidebar-border/70 p-3 dark:border-sidebar-border"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-sm font-semibold text-foreground">
                        Conversas
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        {{ leads.total }} contatos
                    </p>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md bg-primary px-2.5 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90"
                    @click="openNewContactModal"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Novo
                </button>
            </div>

            <div class="relative">
                <Search
                    class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar..."
                    class="h-9 w-full rounded-md border border-input bg-background py-1.5 pr-3 pl-8 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                />
            </div>
        </div>

        <div
            class="shrink-0 border-b border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
        >
            <label
                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                >Instancia</label
            >
            <select
                class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs text-foreground"
                :value="filters.instance ?? ''"
                @change="onInstanceChange"
            >
                <option value="">Todas</option>
                <option
                    v-for="instance in instances"
                    :key="instance.name"
                    :value="instance.name"
                >
                    {{ instance.label }}
                </option>
            </select>
        </div>

        <nav
            class="flex shrink-0 flex-wrap gap-1.5 border-b border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
        >
            <Link
                v-for="filter in statusFilters"
                :key="filter.key"
                :href="filterHref(filter.key)"
                preserve-scroll
                preserve-state
                :class="[
                    'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                    filters.status === filter.key
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                ]"
            >
                {{ filter.label }}
            </Link>
        </nav>

        <!-- Bulk action bar -->
        <div
            v-if="selectedCount > 0"
            class="flex shrink-0 flex-col gap-2 border-b border-sidebar-border/70 bg-primary/5 px-3 py-2 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-foreground">
                    {{ selectedCount }} selecionado(s)
                </p>
                <button
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="clearSelection"
                >
                    Limpar
                </button>
            </div>
            <div class="flex items-center gap-2">
                <select
                    v-model="bulkActionChoice"
                    class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                >
                    <option
                        v-for="(label, key) in bulkActionLabels"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="bulkForm.processing"
                    class="h-8 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                    @click="submitBulkAction"
                >
                    Aplicar
                </button>
            </div>
            <div
                v-if="transferTargets.length > 0"
                class="flex items-center gap-2 border-t border-primary/20 pt-2"
            >
                <select
                    v-model="transferTargetId"
                    class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                >
                    <option :value="null" disabled>Transferir para...</option>
                    <option
                        v-for="t in transferTargets"
                        :key="t.id"
                        :value="t.id"
                    >
                        {{ t.name }}
                    </option>
                </select>
                <button
                    type="button"
                    :disabled="transferForm.processing || !transferTargetId"
                    class="h-8 rounded-md bg-blue-600 px-3 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    @click="submitTransfer"
                >
                    Transferir
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div
                v-for="lead in leads.data"
                :key="lead.id"
                :class="[
                    'flex items-start gap-3 border-b border-sidebar-border/70 px-4 py-3 transition-colors dark:border-sidebar-border',
                    activeLeadId === lead.id
                        ? 'bg-muted/80'
                        : 'hover:bg-muted/50',
                ]"
            >
                <input
                    type="checkbox"
                    class="mt-3 h-3.5 w-3.5 shrink-0 cursor-pointer rounded border-input text-primary focus:ring-1 focus:ring-ring"
                    :aria-label="`Selecionar ${lead.nome}`"
                    :checked="selectedLeadIds.has(lead.id)"
                    @change="
                        (event) =>
                            toggleLeadSelection(
                                lead.id,
                                (event.target as HTMLInputElement).checked,
                            )
                    "
                />
                <Link
                    :href="leadHref(lead)"
                    preserve-scroll
                    class="flex min-w-0 flex-1 gap-3"
                >
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                    >
                        {{ initials(lead.nome) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ lead.nome }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ lead.whatsapp }}
                                </p>
                            </div>
                            <p class="shrink-0 text-xs text-muted-foreground">
                                {{ lead.ultima_interacao ?? 'Sem historico' }}
                            </p>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <StatusBadge :status="lead.status" />
                            <span
                                v-if="lead.is_returning"
                                class="rounded-full bg-violet-500/10 px-2 py-0.5 text-xs font-medium text-violet-500 dark:text-violet-400"
                            >
                                Retornante
                            </span>
                            <span
                                v-if="automationLabel(lead)"
                                class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ automationLabel(lead) }}
                            </span>
                            <span
                                v-if="lead.assigned_user_name"
                                class="rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-400"
                            >
                                {{ lead.assigned_user_name }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <div
                v-if="leads.data.length === 0"
                class="flex flex-col items-center justify-center gap-2 px-8 py-16 text-center"
            >
                <MessageSquare class="h-10 w-10 text-muted-foreground" />
                <p class="text-sm font-medium text-foreground">
                    Nenhuma conversa encontrada
                </p>
                <p class="text-xs text-muted-foreground">
                    Ajuste a busca ou adicione um novo contato.
                </p>
            </div>
        </div>

        <div
            v-if="leads.links?.length > 3"
            class="flex shrink-0 items-center gap-1 border-t border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
        >
            <template v-for="link in leads.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    preserve-scroll
                    :class="[
                        'rounded px-2.5 py-1 text-xs',
                        link.active
                            ? 'bg-primary font-medium text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted',
                    ]"
                />
                <span
                    v-else
                    v-html="link.label"
                    class="px-2.5 py-1 text-xs text-muted-foreground/40"
                />
            </template>
        </div>

        <!-- New contact modal -->
        <div
            v-if="showNewContactModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="showNewContactModal = false"
        >
            <form
                class="w-full max-w-md space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                @submit.prevent="submitNewContact"
            >
                <div>
                    <h3 class="text-lg font-semibold text-foreground">
                        Novo contato
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Cria ou restaura um lead manualmente para esta conta.
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium text-foreground"
                        >Nome</label
                    >
                    <input
                        v-model="newContactForm.nome"
                        type="text"
                        required
                        maxlength="255"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    />
                    <p
                        v-if="newContactForm.errors.nome"
                        class="mt-1 text-xs text-rose-500"
                    >
                        {{ newContactForm.errors.nome }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium text-foreground"
                        >WhatsApp (55DDDNNNNNNNN)</label
                    >
                    <input
                        v-model="newContactForm.whatsapp"
                        type="text"
                        required
                        placeholder="5511999999999"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    />
                    <p
                        v-if="newContactForm.errors.whatsapp"
                        class="mt-1 text-xs text-rose-500"
                    >
                        {{ newContactForm.errors.whatsapp }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium text-foreground"
                        >CPF (opcional)</label
                    >
                    <input
                        v-model="newContactForm.cpf"
                        type="text"
                        placeholder="00000000000"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    />
                    <p
                        v-if="newContactForm.errors.cpf"
                        class="mt-1 text-xs text-rose-500"
                    >
                        {{ newContactForm.errors.cpf }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-medium text-foreground"
                        >Instância de WhatsApp</label
                    >
                    <select
                        v-model="newContactForm.evolution_instance"
                        required
                        class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm text-foreground"
                    >
                        <option value="" disabled>Selecione</option>
                        <option
                            v-for="instance in instances"
                            :key="instance.name"
                            :value="instance.name"
                        >
                            {{ instance.label }}
                        </option>
                    </select>
                    <p
                        v-if="newContactForm.errors.evolution_instance"
                        class="mt-1 text-xs text-rose-500"
                    >
                        {{ newContactForm.errors.evolution_instance }}
                    </p>
                </div>

                <div class="flex gap-2 pt-2">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        @click="showNewContactModal = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="newContactForm.processing"
                        class="flex-1 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        Criar
                    </button>
                </div>
            </form>
        </div>
    </aside>
</template>
