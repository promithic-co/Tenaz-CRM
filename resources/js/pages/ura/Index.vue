<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Link2, Copy, Check, Plug, Trash2, Pencil } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import UraApiKeyController from '@/actions/App/Http/Controllers/UraApiKeyController';

type Agent = { id: number; name: string };

type WhatsappTemplate = {
    id: number;
    name: string;
    meta_template_name: string | null;
    language: string | null;
    variables_count: number;
    body: string | null;
    status: string;
};

type UraApiKey = {
    id: number;
    name: string;
    key_preview: string;
    active: boolean;
    last_used_at: string | null;
    agent: Agent | null;
    whatsapp_template: WhatsappTemplate | null;
};

type CreatedKey = { id: number; plain: string; name: string };

type Props = {
    apiKeys: UraApiKey[];
    agents: Agent[];
    templates: WhatsappTemplate[];
    flash: string | null;
    createdKey: CreatedKey | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Integrações', href: '/ura' },
    { title: 'URA Externa', href: '/ura' },
];

// ─── New key dialog ───────────────────────────────────────────────────────────

const createOpen = ref(false);

const createForm = useForm({
    name: '',
    agent_id: '' as string | number,
    whatsapp_template_id: '' as string | number,
});

function submitCreate(): void {
    createForm.post(UraApiKeyController.store().url, {
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

// ─── Key reveal dialog (show once after creation) ─────────────────────────────

const revealOpen = ref(false);
const revealKey = ref<CreatedKey | null>(null);
const copied = ref(false);

onMounted(() => {
    if (props.createdKey) {
        revealKey.value = props.createdKey;
        revealOpen.value = true;
    }
});

watch(() => props.createdKey, (val) => {
    if (val) {
        revealKey.value = val;
        revealOpen.value = true;
        copied.value = false;
    }
});

function copyKey(): void {
    if (!revealKey.value) { return; }
    navigator.clipboard.writeText(revealKey.value.plain);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2500);
}

// ─── Edit dialog ──────────────────────────────────────────────────────────────

const editOpen = ref(false);
const editingKey = ref<UraApiKey | null>(null);

const editForm = useForm({
    name: '',
    agent_id: '' as string | number,
    whatsapp_template_id: '' as string | number,
    active: true,
});

function openEdit(key: UraApiKey): void {
    editingKey.value = key;
    editForm.name = key.name;
    editForm.agent_id = key.agent?.id ?? '';
    editForm.whatsapp_template_id = key.whatsapp_template?.id ?? '';
    editForm.active = key.active;
    editOpen.value = true;
}

function submitEdit(): void {
    if (!editingKey.value) { return; }
    editForm.patch(UraApiKeyController.update(editingKey.value.id).url, {
        onSuccess: () => {
            editOpen.value = false;
            editingKey.value = null;
        },
    });
}

// ─── Delete ───────────────────────────────────────────────────────────────────

const deleteId = ref<number | null>(null);
const deleteForm = useForm({});

function submitDelete(): void {
    if (deleteId.value === null) { return; }
    deleteForm.delete(UraApiKeyController.destroy(deleteId.value).url, {
        onSuccess: () => { deleteId.value = null; },
    });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatLastUsed(dt: string | null): string {
    if (!dt) { return 'Nunca utilizada'; }
    return new Date(dt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

const apiEndpoint = computed(() => `${window.location.origin}/api/ura/trigger`);

const selectedTemplate = computed(() => {
    const id = Number(createForm.whatsapp_template_id);
    return id ? props.templates.find((t) => t.id === id) ?? null : null;
});

const editSelectedTemplate = computed(() => {
    const id = Number(editForm.whatsapp_template_id);
    return id ? props.templates.find((t) => t.id === id) ?? null : null;
});
</script>

<template>
    <Head title="URA Externa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <div v-if="flash" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400">
                {{ flash }}
            </div>

            <!-- API info card -->
            <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900/50 dark:bg-blue-900/20">
                <p class="text-xs font-semibold text-blue-800 dark:text-blue-300">Endpoint de integração</p>
                <p class="mt-0.5 font-mono text-sm text-blue-900 dark:text-blue-200">POST {{ apiEndpoint }}</p>
                <p class="mt-1.5 text-xs text-blue-700 dark:text-blue-400">
                    Envie <code class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">X-URA-API-Key: &lt;chave&gt;</code> no header.
                    Body: <code class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">phone</code> (E.164, obrigatório),
                    <code class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">name</code>,
                    <code class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">variables</code> (array de strings, mapeados em <code v-pre class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">{{1}}</code>, <code v-pre class="rounded bg-blue-100 px-1 dark:bg-blue-900/60">{{2}}</code>, …).
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Integrações URA</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ apiKeys.length }}</span>
                    </div>
                    <button
                        class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="createOpen = true"
                    >
                        + Nova integração
                    </button>
                </div>

                <!-- Table -->
                <table class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Agente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Template</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Chave (preview)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Último uso</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="key in apiKeys"
                            :key="key.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">{{ key.name }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ key.agent?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                <span v-if="key.whatsapp_template">
                                    {{ key.whatsapp_template.name }}
                                    <span v-if="key.whatsapp_template.variables_count > 0" class="ml-1 text-muted-foreground/60">({{ key.whatsapp_template.variables_count }} var.)</span>
                                </span>
                                <span v-else class="italic">Sem template</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">…{{ key.key_preview }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatLastUsed(key.last_used_at) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        key.active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ key.active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        title="Editar"
                                        @click="openEdit(key)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        class="rounded p-1.5 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                        title="Excluir"
                                        @click="deleteId = key.id"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="apiKeys.length === 0"
                    :icon="Plug"
                    title="Nenhuma integração URA criada"
                    description="Crie uma chave de API para conectar sua URA e disparar templates WhatsApp automaticamente."
                >
                    <button
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="createOpen = true"
                    >
                        Nova integração
                    </button>
                </EmptyState>
            </div>
        </div>
    </AppLayout>

    <!-- Create dialog -->
    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nova integração URA</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Nome da integração <span class="text-red-500">*</span></label>
                    <input
                        v-model="createForm.name"
                        type="text"
                        placeholder="Ex: Discador Zenvia – INSS"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">{{ createForm.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Agente responsável <span class="text-red-500">*</span></label>
                    <select
                        v-model="createForm.agent_id"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Selecione um agente...</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.agent_id" class="mt-1 text-xs text-red-500">{{ createForm.errors.agent_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Template de abertura</label>
                    <select
                        v-model="createForm.whatsapp_template_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Sem template (texto livre)</option>
                        <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
                            {{ tpl.name }}
                            <template v-if="tpl.meta_template_name"> · {{ tpl.meta_template_name }}</template>
                            <template v-if="tpl.variables_count > 0"> · {{ tpl.variables_count }} var.</template>
                        </option>
                    </select>
                    <p v-if="createForm.errors.whatsapp_template_id" class="mt-1 text-xs text-red-500">{{ createForm.errors.whatsapp_template_id }}</p>
                    <p v-if="templates.length === 0" class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                        Nenhum template APPROVED. <a href="/templates" class="underline">Cadastrar template</a>.
                    </p>
                </div>

                <!-- Template preview -->
                <div v-if="selectedTemplate?.body" class="rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2.5 dark:border-sidebar-border">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Preview do template</p>
                    <p class="whitespace-pre-wrap text-xs text-foreground leading-relaxed">{{ selectedTemplate.body }}</p>
                    <p v-if="selectedTemplate.variables_count > 0" class="mt-1.5 text-xs text-muted-foreground">
                        Envie <code class="rounded bg-muted px-1">variables: ["val1", "val2", …]</code> no body da requisição para substituir
                        <code v-pre class="rounded bg-muted px-0.5 font-mono text-xs">{{1}}</code>–<code class="rounded bg-muted px-0.5 font-mono text-xs">&#123;&#123;{{ selectedTemplate.variables_count }}&#125;&#125;</code>.
                    </p>
                </div>

                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="createOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ createForm.processing ? 'Criando...' : 'Criar integração' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Key reveal dialog (one-time) -->
    <Dialog v-model:open="revealOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Integração criada — copie sua chave</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2.5 text-xs text-yellow-800 dark:border-yellow-900/50 dark:bg-yellow-900/20 dark:text-yellow-300">
                    Esta chave é exibida <strong>uma única vez</strong>. Guarde-a em local seguro antes de fechar.
                </div>

                <div>
                    <p class="mb-1 text-xs font-semibold text-muted-foreground uppercase">Integração</p>
                    <p class="text-sm font-medium text-foreground">{{ revealKey?.name }}</p>
                </div>

                <div>
                    <p class="mb-1 text-xs font-semibold text-muted-foreground uppercase">Chave de API</p>
                    <div class="flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2 dark:border-sidebar-border">
                        <code class="flex-1 break-all font-mono text-xs text-foreground">{{ revealKey?.plain }}</code>
                        <button
                            class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            title="Copiar"
                            @click="copyKey"
                        >
                            <Check v-if="copied" class="h-4 w-4 text-green-500" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div>
                    <p class="mb-1 text-xs font-semibold text-muted-foreground uppercase">Como usar</p>
                    <code class="block rounded-lg bg-muted px-3 py-2 font-mono text-xs text-foreground leading-relaxed">
                        POST /api/ura/trigger<br>
                        X-URA-API-Key: {{ revealKey?.plain }}<br><br>
                        {<br>
                        &nbsp;&nbsp;"phone": "+5511999999999",<br>
                        &nbsp;&nbsp;"name": "João",<br>
                        &nbsp;&nbsp;"variables": ["João", "INSS"]<br>
                        }
                    </code>
                </div>
            </div>

            <DialogFooter>
                <button
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    @click="revealOpen = false"
                >
                    Já copiei, fechar
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit dialog -->
    <Dialog v-model:open="editOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Editar integração</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Nome</label>
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">{{ editForm.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Agente responsável</label>
                    <select
                        v-model="editForm.agent_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Selecione um agente...</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Template de abertura</label>
                    <select
                        v-model="editForm.whatsapp_template_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Sem template (texto livre)</option>
                        <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
                            {{ tpl.name }}
                            <template v-if="tpl.meta_template_name"> · {{ tpl.meta_template_name }}</template>
                            <template v-if="tpl.variables_count > 0"> · {{ tpl.variables_count }} var.</template>
                        </option>
                    </select>
                </div>

                <!-- Template preview -->
                <div v-if="editSelectedTemplate?.body" class="rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2.5 dark:border-sidebar-border">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Preview</p>
                    <p class="whitespace-pre-wrap text-xs text-foreground leading-relaxed">{{ editSelectedTemplate.body }}</p>
                    <p v-if="editSelectedTemplate.variables_count > 0" class="mt-1.5 text-xs text-muted-foreground">
                        {{ editSelectedTemplate.variables_count }} variável(is): envie <code class="rounded bg-muted px-1">variables</code> no body.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="editForm.active"
                        type="checkbox"
                        id="edit-active"
                        class="rounded border-input text-primary focus:ring-primary"
                    />
                    <label for="edit-active" class="text-sm text-foreground">Chave ativa</label>
                </div>

                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="editOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ editForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete confirm dialog -->
    <Dialog :open="deleteId !== null" @update:open="(v) => { if (!v) deleteId = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Excluir integração</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">A chave será revogada imediatamente e o sistema externo não conseguirá mais autenticar. Esta ação não pode ser desfeita.</p>
            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    @click="deleteId = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="deleteForm.processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    @click="submitDelete"
                >
                    {{ deleteForm.processing ? 'Excluindo...' : 'Revogar e excluir' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
