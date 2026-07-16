<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Contact = {
    id: number;
    name: string | null;
    phone: string;
    email: string | null;
    cpf: string | null;
    source: string;
    opt_in_status: 'pending' | 'opted_in' | 'opted_out';
    last_seen_at: string | null;
    created_at: string;
};

type ContactListSummary = { id: number; name: string };

type Props = {
    contacts: {
        data: Contact[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { q: string; status: string | null; source: string | null };
    lists: ContactListSummary[];
    can: { manage: boolean };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: '/contatos' },
];

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const source = ref(props.filters.source ?? '');

function applyFilters(): void {
    router.get(
        '/contatos',
        {
            q: q.value || undefined,
            status: status.value || undefined,
            source: source.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const createOpen = ref(false);
const createForm = useForm({
    name: '',
    phone: '',
    email: '',
    cpf: '',
});

function submitCreate(): void {
    createForm.post('/contatos', {
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

const selected = ref<Set<number>>(new Set());

function toggleOne(id: number): void {
    if (selected.value.has(id)) {
        selected.value.delete(id);
    } else {
        selected.value.add(id);
    }
}

const allOnPageSelected = computed(
    () =>
        props.contacts.data.length > 0 &&
        props.contacts.data.every((c) => selected.value.has(c.id)),
);

function toggleAll(): void {
    if (allOnPageSelected.value) {
        props.contacts.data.forEach((c) => selected.value.delete(c.id));
    } else {
        props.contacts.data.forEach((c) => selected.value.add(c.id));
    }
}

const addToListOpen = ref(false);
const addToListForm = useForm<{
    contact_ids: number[];
    list_id: number | null;
}>({
    contact_ids: [],
    list_id: null,
});

function openAddToList(): void {
    addToListForm.contact_ids = Array.from(selected.value);
    addToListForm.list_id = props.lists[0]?.id ?? null;
    addToListOpen.value = true;
}

function submitAddToList(): void {
    if (!addToListForm.list_id) {
        return;
    }
    router.post(
        `/listas-contato/${addToListForm.list_id}/contatos`,
        {
            contact_ids: addToListForm.contact_ids,
        },
        {
            onSuccess: () => {
                addToListOpen.value = false;
                selected.value.clear();
            },
        },
    );
}

const deleteConfirmId = ref<number | null>(null);
const deleteForm = useForm({});

function submitDelete(): void {
    if (deleteConfirmId.value === null) {
        return;
    }
    deleteForm.delete(`/contatos/${deleteConfirmId.value}`, {
        onSuccess: () => {
            deleteConfirmId.value = null;
        },
    });
}

function statusBadge(s: Contact['opt_in_status']): string {
    if (s === 'opted_in') {
        return 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    }
    if (s === 'opted_out') {
        return 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }
    return 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
}

function statusLabel(s: Contact['opt_in_status']): string {
    if (s === 'opted_in') {
        return 'Opt-in';
    }
    if (s === 'opted_out') {
        return 'Opt-out';
    }
    return 'Pendente';
}

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
    <Head title="Contatos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="flex min-w-full flex-col gap-3 border-b border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >Contatos</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ contacts.total }}</span
                        >
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="selected.size > 0 && can.manage"
                            class="rounded-md border border-input px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                            @click="openAddToList"
                        >
                            Adicionar à lista ({{ selected.size }})
                        </button>
                        <button
                            v-if="can.manage"
                            class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            @click="createOpen = true"
                        >
                            + Novo Contato
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div
                    class="flex min-w-full flex-wrap items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Buscar por nome, telefone, CPF ou email"
                        class="w-full max-w-md rounded-md border border-input bg-background px-3 py-1.5 text-sm placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="status"
                        class="min-h-10 flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm sm:min-h-0 sm:flex-none"
                    >
                        <option value="">Todos status</option>
                        <option value="pending">Pendente</option>
                        <option value="opted_in">Opt-in</option>
                        <option value="opted_out">Opt-out</option>
                    </select>
                    <select
                        v-model="source"
                        class="min-h-10 flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm sm:min-h-0 sm:flex-none"
                    >
                        <option value="">Toda origem</option>
                        <option value="manual">Manual</option>
                        <option value="lead_sync">Lead</option>
                        <option value="csv_import">CSV</option>
                        <option value="whatsapp_inbound">WhatsApp</option>
                        <option value="ura">URA</option>
                        <option value="agent_api">API</option>
                    </select>
                    <button
                        class="rounded-md bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-foreground transition-colors hover:bg-secondary/80"
                        @click="applyFilters"
                    >
                        Filtrar
                    </button>
                </div>

                <table class="w-full min-w-[60rem] text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="w-10 px-3 py-3">
                                <input
                                    type="checkbox"
                                    :checked="allOnPageSelected"
                                    @change="toggleAll"
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
                                CPF
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Email
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Origem
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Visto
                            </th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="c in contacts.data"
                            :key="c.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-3 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selected.has(c.id)"
                                    @change="toggleOne(c.id)"
                                />
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ c.name || '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-foreground">
                                {{ c.phone }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ c.cpf || '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ c.email || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="statusBadge(c.opt_in_status)">{{
                                    statusLabel(c.opt_in_status)
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ c.source }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ formatDate(c.last_seen_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Link
                                        :href="`/contatos/${c.id}`"
                                        class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        Ver
                                    </Link>
                                    <button
                                        v-if="can.manage"
                                        class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                        @click="deleteConfirmId = c.id"
                                    >
                                        Arquivar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="contacts.data.length === 0"
                    :icon="Users"
                    title="Nenhum contato"
                    description="Os contatos aparecem aqui conforme leads, importações e webhooks criam identidades canônicas."
                />

                <div
                    v-if="contacts.links?.length > 3"
                    class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <template v-for="link in contacts.links" :key="link.label">
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
        </div>
    </AppLayout>

    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Novo Contato</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="flex flex-col gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nome</label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >Telefone <span class="text-red-500">*</span></label
                    >
                    <input
                        v-model="createForm.phone"
                        type="text"
                        placeholder="55DDDNNNNNNNNN"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                        required
                    />
                    <p
                        v-if="createForm.errors.phone"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ createForm.errors.phone }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">CPF</label>
                    <input
                        v-model="createForm.cpf"
                        type="text"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p
                        v-if="createForm.errors.cpf"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ createForm.errors.cpf }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Email</label>
                    <input
                        v-model="createForm.email"
                        type="email"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p
                        v-if="createForm.errors.email"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ createForm.errors.email }}
                    </p>
                </div>
                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm transition-colors hover:bg-muted"
                        @click="createOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ createForm.processing ? 'Criando...' : 'Criar' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="addToListOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Adicionar à Lista</DialogTitle>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">
                    {{ addToListForm.contact_ids.length }} contato(s)
                    selecionado(s).
                </p>
                <select
                    v-model="addToListForm.list_id"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option v-for="l in lists" :key="l.id" :value="l.id">
                        {{ l.name }}
                    </option>
                </select>
                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm transition-colors hover:bg-muted"
                        @click="addToListOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                        :disabled="!addToListForm.list_id"
                        @click="submitAddToList"
                    >
                        Adicionar
                    </button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="deleteConfirmId !== null"
        @update:open="
            (v) => {
                if (!v) deleteConfirmId = null;
            }
        "
    >
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Arquivar Contato</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Soft-delete: pode ser restaurado depois. Confirmar?
            </p>
            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm transition-colors hover:bg-muted"
                    @click="deleteConfirmId = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="deleteForm.processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    @click="submitDelete"
                >
                    {{ deleteForm.processing ? 'Arquivando...' : 'Arquivar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
