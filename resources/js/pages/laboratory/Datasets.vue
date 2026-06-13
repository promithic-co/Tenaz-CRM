<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Database, Upload, Trash2, Loader2, Eye } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

type Dataset = {
    id: number;
    name: string;
    description: string | null;
    total_entries: number;
    preloaded_count: number;
    created_at: string;
};

type Props = { datasets: Dataset[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'Datasets', href: '/laboratory/datasets-page' },
];

const uploading = ref(false);
const uploadMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);
const prefetchingId = ref<number | null>(null);
const showEntriesId = ref<number | null>(null);
const entriesPreview = ref<{ cpf: string; nome: string; status_expected: string; has_qualified_json: boolean }[]>([]);

const name = ref('');
const description = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

function csrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submitUpload() {
    const file = fileInput.value?.files?.[0];
    if (!file || !name.value.trim()) {
        uploadMessage.value = { type: 'error', text: 'Selecione um arquivo e informe o nome.' };
        return;
    }
    uploadMessage.value = null;
    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('name', name.value.trim());
        if (description.value.trim()) formData.append('description', description.value.trim());
        const res = await fetch('/laboratory/datasets', {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: formData,
        });
        const data = await res.json();
        if (res.ok) {
            uploadMessage.value = { type: 'success', text: `Importados ${data.data?.total_entries ?? 0} CPFs no dataset "${data.data?.name}".` };
            name.value = '';
            description.value = '';
            if (fileInput.value) fileInput.value.value = '';
            router.visit('/laboratory/datasets-page', { preserveState: false });
        } else {
            uploadMessage.value = { type: 'error', text: data.message || 'Falha ao importar.' };
        }
    } catch (e) {
        uploadMessage.value = { type: 'error', text: 'Erro de rede.' };
    } finally {
        uploading.value = false;
    }
}

async function prefetch(id: number) {
    prefetchingId.value = id;
    try {
        const res = await fetch(`/laboratory/datasets/${id}/prefetch`, {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' },
        });
        const data = await res.json();
        if (res.ok) {
            router.visit('/laboratory/datasets-page', { preserveState: false });
        }
    } finally {
        prefetchingId.value = null;
    }
}

async function showEntries(id: number) {
    if (showEntriesId.value === id) {
        showEntriesId.value = null;
        return;
    }
    const res = await fetch(`/laboratory/datasets/${id}`, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    entriesPreview.value = data.data?.entries_preview ?? [];
    showEntriesId.value = id;
}

async function destroy(id: number) {
    if (!confirm('Excluir este dataset? Esta ação não pode ser desfeita.')) return;
    const res = await fetch(`/laboratory/datasets/${id}`, {
        method: 'DELETE',
        headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' },
    });
    if (res.ok) router.visit('/laboratory/datasets-page', { preserveState: false });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <Head title="Datasets - Laboratory" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center gap-2">
                <Database class="h-5 w-5 text-muted-foreground" />
                <h1 class="text-lg font-semibold text-foreground">Datasets de CPF</h1>
            </div>

            <!-- Upload -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Importar Dataset</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px]">
                        <label class="mb-1 block text-xs text-muted-foreground">Arquivo (CSV ou JSON)</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.json,.txt"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="min-w-[180px]">
                        <label class="mb-1 block text-xs text-muted-foreground">Nome</label>
                        <input v-model="name" type="text" placeholder="Ex: Exportação Promosys Março" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="min-w-[180px]">
                        <label class="mb-1 block text-xs text-muted-foreground">Descrição (opcional)</label>
                        <input v-model="description" type="text" placeholder="Opcional" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <button
                        type="button"
                        :disabled="uploading"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        @click="submitUpload"
                    >
                        <Loader2 v-if="uploading" class="h-4 w-4 animate-spin" />
                        <Upload v-else class="h-4 w-4" />
                        Importar Dataset
                    </button>
                </div>
                <p v-if="uploadMessage" :class="uploadMessage.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="mt-2 text-sm">
                    {{ uploadMessage.text }}
                </p>
            </div>

            <!-- List -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <h2 class="text-sm font-semibold text-foreground">Datasets</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 bg-muted/50 dark:border-sidebar-border">
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Nome</th>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">CPFs</th>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Pré-carregados</th>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Criado em</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="d in datasets" :key="d.id" class="text-foreground">
                                <td class="px-4 py-2">{{ d.name }}</td>
                                <td class="px-4 py-2">{{ d.total_entries }}</td>
                                <td class="px-4 py-2">{{ d.preloaded_count }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ formatDate(d.created_at) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        class="mr-2 inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                                        @click="showEntries(d.id)"
                                    >
                                        <Eye class="h-3 w-3" />
                                        Ver CPFs
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="prefetchingId === d.id"
                                        class="mr-2 inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted disabled:opacity-50"
                                        @click="prefetch(d.id)"
                                    >
                                        <Loader2 v-if="prefetchingId === d.id" class="h-3 w-3 animate-spin" />
                                        Pré-buscar Promosys
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        @click="destroy(d.id)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                        Excluir
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!datasets.length">
                                <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Nenhum dataset ainda. Importe um arquivo CSV ou JSON acima.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Entries preview modal -->
                <div v-if="showEntriesId" class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">Primeiros registros (máx. 20)</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                <th class="pb-1 font-medium text-muted-foreground">CPF</th>
                                <th class="pb-1 font-medium text-muted-foreground">Nome</th>
                                <th class="pb-1 font-medium text-muted-foreground">Status</th>
                                <th class="pb-1 font-medium text-muted-foreground">Pré-carregado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="(e, i) in entriesPreview" :key="i">
                                <td class="py-1">{{ e.cpf }}</td>
                                <td class="py-1">{{ e.nome }}</td>
                                <td class="py-1">{{ e.status_expected }}</td>
                                <td class="py-1">{{ e.has_qualified_json ? 'Sim' : 'Não' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="mt-2 text-xs text-muted-foreground underline hover:no-underline" @click="showEntriesId = null">Fechar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
