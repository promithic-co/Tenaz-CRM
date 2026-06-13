<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import CsvImportDialog from '@/components/CsvImportDialog.vue';
import { Users } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { store as storeEntry, destroy as destroyEntry } from '@/actions/App/Http/Controllers/ContactListEntryController';
import FilterChipsDisplay from './partials/FilterChipsDisplay.vue';
import EditFiltersDialog from './partials/EditFiltersDialog.vue';
import FreezeListDialog from './partials/FreezeListDialog.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';

type Lead = {
    id: number;
    nome: string;
};

type Entry = {
    id: number;
    name: string | null;
    phone: string;
    opt_in_status: string;
    lead_id: number | null;
    lead: Lead | null;
    created_at: string;
};

type ContactList = {
    id: number;
    name: string;
    description: string | null;
    entries_count: number;
    is_dynamic: boolean;
    has_campaign_in_sending: boolean;
    filters_json: Record<string, unknown> | null;
    last_resolved_count: number | null;
    last_resolved_at: string | null;
};

type ChipGroup = {
    label: string;
    values: string[];
    modifier?: string;
};

type Props = {
    list: ContactList;
    entries: {
        data: Entry[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    optInStats: {
        pending: number;
        opted_in: number;
        opted_out: number;
    };
    filterChips: ChipGroup[];
    statuses: Array<{ value: string; label: string }>;
    agents: Array<{ id: number; nome: string }>;
    instances: Array<{ id: number; label: string }>;
};

const props = defineProps<Props>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Listas de Contato', href: '/listas-contato' },
    { title: props.list.name, href: `/listas-contato/${props.list.id}` },
];

// CSV import dialog
const csvOpen = ref(false);

// Contact picker dialog
type PickerContact = { id: number; name: string | null; phone: string; email: string | null; cpf: string | null; opt_in_status: string };
const pickerOpen = ref(false);
const pickerQuery = ref('');
const pickerResults = ref<PickerContact[]>([]);
const pickerAlready = ref<number[]>([]);
const pickerSelected = ref<Set<number>>(new Set());
const pickerLoading = ref(false);
let pickerDebounce: number | null = null;

function runPickerSearch(): void {
    pickerLoading.value = true;
    fetch(`/contatos/search?q=${encodeURIComponent(pickerQuery.value)}&list_id=${props.list.id}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((r) => r.json())
        .then((data: { contacts: PickerContact[]; already_in_list: number[] }) => {
            pickerResults.value = data.contacts;
            pickerAlready.value = data.already_in_list;
        })
        .finally(() => { pickerLoading.value = false; });
}

watch(pickerQuery, () => {
    if (pickerDebounce) { clearTimeout(pickerDebounce); }
    pickerDebounce = window.setTimeout(runPickerSearch, 200);
});

function openPicker(): void {
    pickerOpen.value = true;
    pickerSelected.value.clear();
    runPickerSearch();
}

function togglePicker(id: number): void {
    const next = new Set(pickerSelected.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    pickerSelected.value = next;
}

function submitPicker(): void {
    if (pickerSelected.value.size === 0) { return; }
    router.post(`/listas-contato/${props.list.id}/contatos`, {
        contact_ids: Array.from(pickerSelected.value),
    }, {
        preserveScroll: true,
        onSuccess: () => { pickerOpen.value = false; },
    });
}

// Manual add form toggle
const showAddForm = ref(false);
const addForm = useForm({
    phone: '',
    name: '',
});

function submitAdd(): void {
    addForm.post(storeEntry.url(props.list.id), {
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
        preserveScroll: true,
    });
}

function formatDate(value: string): string {
    const d = new Date(value);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(2);
    return `${hh}:${mm} ${dd}/${mo}/${yy}`;
}

// Remove entry
const removeForm = useForm({});

function removeEntry(entryId: number): void {
    removeForm.delete(destroyEntry.url(entryId), {
        preserveScroll: true,
    });
}

function optInBadgeClass(status: string): string {
    if (status === 'opted_in') {
        return 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400';
    }
    if (status === 'opted_out') {
        return 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }
    return 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
}

function optInBadgeLabel(status: string): string {
    if (status === 'opted_in') { return 'Ativo'; }
    if (status === 'opted_out') { return 'Removido'; }
    return 'Pendente';
}

// Flash banner — existing pattern (line 164)
const flashSuccess = computed(() => (page.props.flash as Record<string, string | null> | undefined)?.success ?? null);

// D-14: block edit/freeze when a linked campaign is sending
const editBlocked = computed(() => Boolean(props.list.has_campaign_in_sending));

// Dialog open state
const editOpen = ref(false);
const freezeOpen = ref(false);
</script>

<template>
    <Head :title="list.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Flash message -->
            <div
                v-if="flashSuccess"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400"
            >
                {{ flashSuccess }}
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">{{ list.name }}</h1>
                    <p v-if="list.description" class="mt-0.5 text-sm text-muted-foreground">{{ list.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-md border border-input px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="openPicker"
                    >
                        Adicionar contatos existentes
                    </button>
                    <button
                        class="rounded-md border border-input px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="showAddForm = !showAddForm"
                    >
                        {{ showAddForm ? 'Cancelar' : 'Adicionar Contato' }}
                    </button>
                    <button
                        class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="csvOpen = true"
                    >
                        Importar CSV
                    </button>
                </div>
            </div>

            <!-- Dynamic list: Filtros aplicados section (D-13, D-14, D-15, D-16) -->
            <section v-if="list.is_dynamic" class="space-y-4">
                <h2 class="text-lg font-semibold">Filtros aplicados</h2>
                <FilterChipsDisplay :groups="filterChips" />

                <div class="flex flex-wrap items-center gap-2">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span>
                                    <Button variant="outline" :disabled="editBlocked" @click="editOpen = true">
                                        Editar filtros
                                    </Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent v-if="editBlocked">
                                Campanha ativa em andamento
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span>
                                    <Button variant="outline" :disabled="editBlocked" @click="freezeOpen = true">
                                        Congelar lista
                                    </Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent v-if="editBlocked">
                                Campanha ativa em andamento
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <!-- D-14: amber notice when blocked -->
                <p
                    v-if="editBlocked"
                    class="rounded bg-amber-100 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    Edição bloqueada: a campanha vinculada está enviando. Aguarde o fim do envio.
                </p>
            </section>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Total Contatos</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ list.entries_count }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Opt-in Ativo</p>
                    <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ optInStats.opted_in }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Pendente</p>
                    <p class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ optInStats.pending }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Removido</p>
                    <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ optInStats.opted_out }}</p>
                </div>
            </div>

            <!-- Inline add contact form -->
            <div
                v-if="showAddForm"
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="mb-3 text-sm font-medium text-foreground">Adicionar contato manualmente</p>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitAdd">
                    <div class="flex-1 min-w-48">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Telefone <span class="text-red-500">*</span></label>
                        <input
                            v-model="addForm.phone"
                            type="text"
                            placeholder="5511999999999"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        <p v-if="addForm.errors.phone" class="mt-1 text-xs text-red-500">{{ addForm.errors.phone }}</p>
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Nome</label>
                        <input
                            v-model="addForm.name"
                            type="text"
                            placeholder="Opcional"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="addForm.processing"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ addForm.processing ? 'Adicionando...' : 'Adicionar' }}
                    </button>
                </form>
            </div>

            <!-- Entries table -->
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex items-center gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Contatos</span>
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ entries.total }} entradas</span>
                </div>

                <table class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Telefone</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Status Opt-in</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Adicionado em</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 text-sm text-foreground">{{ entry.name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ entry.phone }}</td>
                            <td class="px-4 py-3">
                                <span :class="optInBadgeClass(entry.opt_in_status)">{{ optInBadgeLabel(entry.opt_in_status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="entry.lead_id && entry.lead"
                                    :href="`/conversas/${entry.lead_id}`"
                                    class="text-xs text-primary hover:underline"
                                >
                                    {{ entry.lead.nome }}
                                </Link>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatDate(entry.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                    :disabled="removeForm.processing"
                                    @click="removeEntry(entry.id)"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="entries.data.length === 0"
                    :icon="Users"
                    title="Nenhum contato nesta lista"
                    description="Adicione contatos manualmente ou importe um arquivo CSV."
                />

                <!-- Pagination -->
                <div v-if="entries.links?.length > 3" class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <template v-for="link in entries.links" :key="link.label">
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
                        <span v-else v-html="link.label" class="px-3 py-1 text-sm text-muted-foreground/40" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- CSV Import Dialog -->
    <CsvImportDialog :list-id="list.id" v-model:open="csvOpen" />

    <!-- EditFiltersDialog (dynamic lists only — D-13) -->
    <EditFiltersDialog
        v-if="list.is_dynamic"
        v-model:open="editOpen"
        :list-id="list.id"
        :initial-filters="(list.filters_json as any) ?? { version: 1, match: 'all', rules: [] }"
        :statuses="statuses"
        :agents="agents"
        :instances="instances"
    />

    <!-- FreezeListDialog (dynamic lists only — D-15: ONLY here, never Index) -->
    <FreezeListDialog
        v-if="list.is_dynamic"
        v-model:open="freezeOpen"
        :list-id="list.id"
        :count="list.last_resolved_count ?? list.entries_count ?? 0"
    />

    <!-- Contact picker dialog -->
    <div
        v-if="pickerOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="pickerOpen = false"
    >
        <div class="w-full max-w-2xl rounded-xl bg-card p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-foreground">Adicionar contatos existentes</h3>
                <button class="text-sm text-muted-foreground hover:text-foreground" @click="pickerOpen = false">Fechar</button>
            </div>
            <input
                v-model="pickerQuery"
                type="text"
                placeholder="Buscar por nome, telefone, CPF ou email"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
            />
            <div class="mt-3 max-h-80 overflow-y-auto rounded-md border border-sidebar-border/70">
                <p v-if="pickerLoading" class="px-3 py-2 text-xs text-muted-foreground">Buscando...</p>
                <p v-else-if="pickerResults.length === 0" class="px-3 py-2 text-xs text-muted-foreground">Nenhum contato encontrado.</p>
                <ul v-else class="divide-y divide-sidebar-border/70">
                    <li
                        v-for="c in pickerResults"
                        :key="c.id"
                        class="flex items-center justify-between px-3 py-2 text-sm"
                    >
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :disabled="pickerAlready.includes(c.id)"
                                :checked="pickerSelected.has(c.id)"
                                @change="togglePicker(c.id)"
                            />
                            <span>
                                <span class="font-medium text-foreground">{{ c.name || '(sem nome)' }}</span>
                                <span class="ml-2 text-xs text-muted-foreground">{{ c.phone }}</span>
                            </span>
                        </label>
                        <span v-if="pickerAlready.includes(c.id)" class="text-xs text-muted-foreground">Já na lista</span>
                    </li>
                </ul>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button class="rounded-md border border-input px-3 py-1.5 text-sm" @click="pickerOpen = false">Cancelar</button>
                <button
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    :disabled="pickerSelected.size === 0"
                    @click="submitPicker"
                >
                    Adicionar ({{ pickerSelected.size }})
                </button>
            </div>
        </div>
    </div>
</template>
