<script setup lang="ts">
import { Link, router, useForm, WhenVisible } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    CornerUpRight,
    MessageSquare,
    PauseCircle,
    Plus,
    Search,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { bulkAction, index, show, store, transfer } from '@/routes/conversas';
import type { QueryParams } from '@/wayfinder';
import type {
    ConversationFilters,
    InboxGroup,
    InboxGroupCounts,
    LeadPaginator,
    InboxLead,
    TransferTarget,
} from '../types';

type Props = {
    leads: LeadPaginator;
    filters: ConversationFilters;
    groupCounts: InboxGroupCounts;
    instances: Array<{ name: string; label: string }>;
    activeLeadId: number | null;
    transferTargets: TransferTarget[];
};

const props = defineProps<Props>();

const groupTabs: Array<{ key: InboxGroup; label: string; title: string }> = [
    {
        key: 'fila',
        label: 'Fila',
        title: 'Escalados aguardando alguem assumir',
    },
    { key: 'minhas', label: 'Minhas', title: 'Atribuidas a voce' },
    { key: 'ia', label: 'IA', title: 'Sem atendente humano' },
    { key: 'todas', label: 'Todas', title: 'Todas as conversas' },
];

const statusFilters = [
    { key: 'todos', label: 'Todos os status' },
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
const openFilter = ref<'status' | 'instance' | null>(null);

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

const activeGroup = computed<InboxGroup>(() => props.filters.group ?? 'todas');

const statusLabel = computed(
    () =>
        statusFilters.find((filter) => filter.key === props.filters.status)
            ?.label ?? 'Todos os status',
);

const instanceLabel = computed(
    () =>
        props.instances.find(
            (instance) => instance.name === props.filters.instance,
        )?.label ?? 'Todas as instancias',
);

function groupCount(group: InboxGroup): number | null {
    return group === 'todas'
        ? null
        : (props.groupCounts?.[group] ?? null);
}

function filterQuery(overrides: QueryParams = {}): QueryParams {
    return {
        group: props.filters.group === 'todas' ? undefined : props.filters.group,
        status: props.filters.status,
        instance: props.filters.instance || undefined,
        search: searchQuery.value || undefined,
        sort: props.filters.sort || 'last_interaction_at',
        direction: props.filters.direction || 'desc',
        ...overrides,
    };
}

function visitInbox(overrides: QueryParams = {}): void {
    openFilter.value = null;

    router.get(index.url(), filterQuery(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function filterHref(status: string): string {
    return index.url({ query: filterQuery({ status }) });
}

/**
 * Switching tab drops sort/direction so the server can apply the ordering that
 * tab deserves — the queue is oldest-first, every other tab newest-first.
 */
function groupHref(group: InboxGroup): string {
    return index.url({
        query: filterQuery({
            group: group === 'todas' ? undefined : group,
            sort: undefined,
            direction: undefined,
        }),
    });
}

function leadHref(lead: InboxLead): string {
    return show.url({ lead: lead.id }, { query: filterQuery() });
}

function toggleFilter(name: 'status' | 'instance'): void {
    openFilter.value = openFilter.value === name ? null : name;
}

function closeFilterOnOutsideClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;

    if (target?.closest('[data-inbox-filter]')) {
        return;
    }

    openFilter.value = null;
}

onMounted(() =>
    document.addEventListener('mousedown', closeFilterOnOutsideClick),
);
onUnmounted(() =>
    document.removeEventListener('mousedown', closeFilterOnOutsideClick),
);

function initials(name: string): string {
    return name.trim().charAt(0).toUpperCase() || '?';
}

/**
 * Always two letters: first and last name, skipping everything in between, or
 * the first two letters when the operator goes by a single name. Skipping the
 * middle also disposes of the "da/de/dos" particles for free. A one-letter chip
 * would collide between teammates (Juliana and Joao both becoming "J") and break
 * the row's alignment.
 */
function assigneeInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (
        parts[0].charAt(0) + parts[parts.length - 1].charAt(0)
    ).toUpperCase();
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

        <nav
            class="flex shrink-0 border-b border-sidebar-border/70 px-1 dark:border-sidebar-border"
        >
            <Link
                v-for="tab in groupTabs"
                :key="tab.key"
                :href="groupHref(tab.key)"
                :title="tab.title"
                preserve-scroll
                preserve-state
                :class="[
                    'flex flex-1 items-center justify-center gap-1.5 border-b-2 px-1 py-2 text-xs font-medium transition-colors',
                    activeGroup === tab.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
            >
                {{ tab.label }}
                <span
                    v-if="groupCount(tab.key)"
                    :class="[
                        'rounded-full px-1.5 py-px text-[10px] font-semibold tabular-nums',
                        activeGroup === tab.key
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    ]"
                >
                    {{ groupCount(tab.key) }}
                </span>
            </Link>
        </nav>

        <div
            class="flex shrink-0 items-center gap-1.5 border-b border-sidebar-border/70 px-3 py-1.5 dark:border-sidebar-border"
        >
            <div class="relative min-w-0 flex-1" data-inbox-filter>
                <button
                    type="button"
                    :class="[
                        'flex h-7 w-full items-center justify-between gap-1 rounded-md border px-2 text-xs transition-colors',
                        filters.status !== 'todos'
                            ? 'border-primary/40 bg-primary/10 font-medium text-foreground'
                            : 'border-input text-muted-foreground hover:text-foreground',
                    ]"
                    @click="toggleFilter('status')"
                >
                    <span class="truncate">{{ statusLabel }}</span>
                    <ChevronDown class="h-3 w-3 shrink-0" />
                </button>
                <div
                    v-if="openFilter === 'status'"
                    class="absolute top-full left-0 z-30 mt-1 w-48 overflow-hidden rounded-md border border-sidebar-border/70 bg-popover py-1 shadow-lg dark:border-sidebar-border"
                >
                    <Link
                        v-for="filter in statusFilters"
                        :key="filter.key"
                        :href="filterHref(filter.key)"
                        preserve-scroll
                        preserve-state
                        class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-popover-foreground hover:bg-muted"
                        @click="openFilter = null"
                    >
                        {{ filter.label }}
                        <Check
                            v-if="filters.status === filter.key"
                            class="h-3 w-3 shrink-0 text-primary"
                        />
                    </Link>
                </div>
            </div>

            <div class="relative min-w-0 flex-1" data-inbox-filter>
                <button
                    type="button"
                    :class="[
                        'flex h-7 w-full items-center justify-between gap-1 rounded-md border px-2 text-xs transition-colors',
                        filters.instance
                            ? 'border-primary/40 bg-primary/10 font-medium text-foreground'
                            : 'border-input text-muted-foreground hover:text-foreground',
                    ]"
                    @click="toggleFilter('instance')"
                >
                    <span class="truncate">{{ instanceLabel }}</span>
                    <ChevronDown class="h-3 w-3 shrink-0" />
                </button>
                <div
                    v-if="openFilter === 'instance'"
                    class="absolute top-full right-0 z-30 mt-1 max-h-64 w-52 overflow-y-auto rounded-md border border-sidebar-border/70 bg-popover py-1 shadow-lg dark:border-sidebar-border"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs text-popover-foreground hover:bg-muted"
                        @click="visitInbox({ instance: null })"
                    >
                        Todas as instancias
                        <Check
                            v-if="!filters.instance"
                            class="h-3 w-3 shrink-0 text-primary"
                        />
                    </button>
                    <button
                        v-for="instance in instances"
                        :key="instance.name"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs text-popover-foreground hover:bg-muted"
                        @click="visitInbox({ instance: instance.name })"
                    >
                        <span class="truncate">{{ instance.label }}</span>
                        <Check
                            v-if="filters.instance === instance.name"
                            class="h-3 w-3 shrink-0 text-primary"
                        />
                    </button>
                </div>
            </div>
        </div>

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
                    'group flex items-center gap-2 border-b border-l-2 border-sidebar-border/70 py-2 pr-3 pl-1.5 transition-colors dark:border-sidebar-border',
                    lead.awaiting_reply
                        ? 'border-l-amber-500'
                        : 'border-l-transparent',
                    activeLeadId === lead.id
                        ? 'bg-muted/80'
                        : 'hover:bg-muted/50',
                ]"
            >
                <input
                    type="checkbox"
                    :class="[
                        'h-3.5 w-3.5 shrink-0 cursor-pointer rounded border-input text-primary focus:ring-1 focus:ring-ring group-hover:visible',
                        selectedCount > 0 ? 'visible' : 'invisible',
                    ]"
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
                    class="flex min-w-0 flex-1 items-center gap-2.5"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                    >
                        {{ initials(lead.nome) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <p
                                :class="[
                                    'truncate text-sm text-foreground',
                                    lead.awaiting_reply
                                        ? 'font-semibold'
                                        : 'font-medium',
                                ]"
                            >
                                {{ lead.nome }}
                            </p>
                            <p
                                class="shrink-0 text-[11px] text-muted-foreground"
                            >
                                {{ lead.ultima_interacao ?? 'Sem historico' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <CornerUpRight
                                v-if="lead.last_message_direction === 'outbound'"
                                class="h-3 w-3 shrink-0 text-muted-foreground"
                            />
                            <p
                                class="min-w-0 flex-1 truncate text-xs text-muted-foreground"
                            >
                                {{ lead.last_message_body ?? lead.whatsapp }}
                            </p>
                            <PauseCircle
                                v-if="lead.pausado"
                                class="h-3 w-3 shrink-0 text-amber-500"
                                aria-label="IA pausada"
                            />
                            <span
                                v-if="lead.status === 'optou_sair'"
                                class="shrink-0 rounded px-1 py-px text-[10px] font-semibold text-rose-500"
                                title="Cliente pediu para nao receber mensagens"
                            >
                                OPT-OUT
                            </span>
                            <span
                                v-if="lead.is_returning"
                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-500"
                                title="Cliente retornante"
                            />
                            <span
                                v-if="lead.assigned_user_name"
                                class="shrink-0 rounded bg-blue-500/10 px-1 py-px text-[10px] font-semibold text-blue-500 dark:text-blue-400"
                                :title="lead.assigned_user_name"
                            >
                                {{ assigneeInitials(lead.assigned_user_name) }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <WhenVisible
                v-if="leads.next_page_url"
                always
                :buffer="200"
                :params="{
                    data: { page: leads.current_page + 1 },
                    only: ['leads'],
                    preserveUrl: true,
                }"
            >
                <div
                    class="flex items-center justify-center gap-2 py-4 text-xs text-muted-foreground"
                >
                    <span
                        class="h-3 w-3 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
                    />
                    Carregando mais...
                </div>
            </WhenVisible>

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
