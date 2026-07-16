<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MoreVertical, RefreshCw, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
// useForm kept for delete form below
import {
    create as createRoute,
    show,
    destroy as destroyList,
    refresh,
} from '@/actions/App/Http/Controllers/ContactListController';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatRelative } from '@/lib/relative-time';
import type { BreadcrumbItem } from '@/types';

type ContactList = {
    id: number;
    name: string;
    description: string | null;
    source: string;
    entries_count: number;
    is_dynamic: boolean;
    last_resolved_count: number | null;
    last_resolved_at: string | null;
    updated_at: string;
    created_at: string;
    has_campaign_in_sending: boolean;
};

type Props = {
    lists: {
        data: ContactList[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    can: Record<string, boolean>;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/listas-contato' },
    { title: 'Listas de Contato', href: '/listas-contato' },
];

// Flash success banner (same pattern as Show.vue line 164)
const page = usePage();
const flashSuccess = computed(
    () =>
        (page.props.flash as Record<string, string | null> | undefined)
            ?.success ?? null,
);

// Create dialog
// Delete confirm
const deleteConfirmId = ref<number | null>(null);
const deleteForm = useForm({});

function confirmDelete(id: number): void {
    deleteConfirmId.value = id;
}

function submitDelete(): void {
    if (deleteConfirmId.value === null) {
        return;
    }
    deleteForm.delete(destroyList.url(deleteConfirmId.value!), {
        onSuccess: () => {
            deleteConfirmId.value = null;
        },
    });
}

// Refresh (dynamic lists only) — flash success rendered via page.props.flash.success (Show.vue pattern)
const refreshing = ref<number | null>(null);

function onRefresh(list: ContactList): void {
    refreshing.value = list.id;
    router.post(
        refresh.url({ list: list.id }),
        {},
        {
            preserveScroll: true,
            // No toast library. Backend flash 'success' handled by flash banner above.
            onFinish: () => {
                refreshing.value = null;
            },
        },
    );
}

function sourceBadgeClass(source: string): string {
    if (source === 'csv') {
        return 'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    }
    if (source === 'leads') {
        return 'rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
    }
    return 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
}

function sourceBadgeLabel(source: string): string {
    if (source === 'csv') {
        return 'CSV';
    }
    if (source === 'leads') {
        return 'Leads';
    }
    return 'Manual';
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
</script>

<template>
    <Head title="Listas de Contato" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <!-- Flash success banner (page.props.flash.success — same pattern as Show.vue) -->
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400"
            >
                {{ flashSuccess }}
            </div>

            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <!-- Header -->
                <div
                    class="flex min-w-full flex-col gap-3 border-b border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >Listas de Contato</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ lists.total }} listas</span
                        >
                    </div>
                    <Link
                        :href="createRoute.url()"
                        class="flex min-h-10 items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 sm:min-h-0"
                    >
                        + Nova Lista
                    </Link>
                </div>

                <!-- Table -->
                <table class="w-full min-w-[52rem] text-sm">
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
                                Tipo
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Contatos
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Origem
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Última atualização
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Criada em
                            </th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="list in lists.data"
                            :key="list.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">
                                    {{ list.name }}
                                </p>
                                <p
                                    v-if="list.description"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ list.description }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="list.is_dynamic"
                                    class="bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                                >
                                    Dinâmica
                                </Badge>
                                <Badge
                                    v-else
                                    class="bg-muted text-muted-foreground"
                                >
                                    Estática
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-foreground">
                                {{ list.entries_count }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="sourceBadgeClass(list.source)">{{
                                    sourceBadgeLabel(list.source)
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                <span
                                    v-if="
                                        list.is_dynamic && list.last_resolved_at
                                    "
                                >
                                    Atualizada
                                    {{ formatRelative(list.last_resolved_at) }}
                                </span>
                                <span v-else-if="list.is_dynamic">
                                    Nunca resolvida
                                </span>
                                <span v-else>
                                    {{ formatRelative(list.updated_at) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ formatDate(list.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Link
                                        :href="show.url(list.id)"
                                        class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        Ver
                                    </Link>
                                    <button
                                        class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                        @click="confirmDelete(list.id)"
                                    >
                                        Excluir
                                    </button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger
                                            class="inline-flex items-center justify-center rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus:outline-none"
                                            aria-label="Ações da lista"
                                        >
                                            <MoreVertical class="h-4 w-4" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <!-- D-15: freeze action intentionally NOT exposed here.
                                                 Operator must open Show.vue to freeze a list (FreezeListDialog in 51-08). -->
                                            <DropdownMenuItem
                                                v-if="list.is_dynamic"
                                                :disabled="
                                                    refreshing === list.id
                                                "
                                                @select="onRefresh(list)"
                                            >
                                                <RefreshCw
                                                    class="mr-2 h-4 w-4"
                                                />
                                                Atualizar agora
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="lists.data.length === 0"
                    :icon="Users"
                    title="Nenhuma lista de contato"
                    description="Crie sua primeira lista para começar a enviar campanhas."
                />

                <!-- Pagination -->
                <div
                    v-if="lists.links?.length > 3"
                    class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <template v-for="link in lists.links" :key="link.label">
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

    <!-- Create List Dialog -->
    <!-- Delete Confirm Dialog -->
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
                <DialogTitle>Excluir Lista</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Tem certeza que deseja excluir esta lista? Esta ação não pode
                ser desfeita.
            </p>
            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
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
                    {{ deleteForm.processing ? 'Excluindo...' : 'Excluir' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
