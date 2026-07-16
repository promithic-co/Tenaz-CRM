<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    FileText,
    RefreshCw,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import WhatsappTemplateController from '@/actions/App/Http/Controllers/WhatsappTemplateController';
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

type WhatsappInstance = {
    id: number;
    name: string;
    display_name: string | null;
    provider: string;
    meta_waba_id: string | null;
    has_meta_access_token: boolean;
};

type TemplateButtonType = 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER';

type TemplateButton = {
    type: TemplateButtonType;
    text: string;
    url?: string;
    phone_number?: string;
};

type WhatsappTemplate = {
    id: number;
    name: string;
    kind: string;
    meta_template_id: string | null;
    meta_template_name: string | null;
    meta_waba_id: string | null;
    status: string;
    category: string | null;
    language: string | null;
    header: string | null;
    body: string | null;
    footer: string | null;
    buttons_json: TemplateButton[] | null;
    quality_score: string | null;
    rejected_reason: string | null;
    components_json: unknown[] | null;
    variables_count: number;
    whatsapp_instance_id: number | null;
    last_synced_at: string | null;
    created_at: string;
    whatsapp_instance: WhatsappInstance | null;
};

type Props = {
    templates: {
        data: WhatsappTemplate[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    instances: WhatsappInstance[];
    currentKind: string;
    flash: string | null;
    error: string | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/campanhas' },
    { title: 'Templates WhatsApp', href: '/templates' },
];

// ─── Register dialog ──────────────────────────────────────────────────────────

const registerOpen = ref(false);

const registerForm = useForm({
    kind: 'meta_hsm',
    whatsapp_instance_id: '',
    meta_template_name: '',
    header_text: '',
    header_example: '',
    variable_examples: {} as Record<string, string>,
    body: '',
    footer_text: '',
    buttons: [] as TemplateButton[],
    name: '',
    category: 'MARKETING',
    language: 'pt_BR',
});

const headerHasVariable = computed(() =>
    /\{\{1\}\}/.test(registerForm.header_text),
);

const buttonTypeLabels: Record<TemplateButtonType, string> = {
    QUICK_REPLY: 'Resposta rápida',
    URL: 'Link (URL)',
    PHONE_NUMBER: 'Telefone',
};

function addButton(): void {
    if (registerForm.buttons.length >= 10) {
        return;
    }
    registerForm.buttons.push({ type: 'QUICK_REPLY', text: '' });
}

function removeButton(index: number): void {
    registerForm.buttons.splice(index, 1);
}

const metaInstances = computed(() =>
    props.instances.filter((i) => i.provider === 'meta_cloud'),
);

const availableInstances = computed(() => metaInstances.value);

const selectedMetaInstance = computed(
    () =>
        metaInstances.value.find(
            (instance) =>
                instance.id === Number(registerForm.whatsapp_instance_id),
        ) ?? null,
);

const selectedMetaInstanceIsConfigured = computed(() =>
    Boolean(
        selectedMetaInstance.value?.meta_waba_id &&
        selectedMetaInstance.value?.has_meta_access_token,
    ),
);

function resetRegisterForm(): void {
    registerForm.reset();
    registerForm.kind = 'meta_hsm';
    registerForm.variable_examples = {};
    registerForm.buttons = [];
}

function openRegister(): void {
    resetRegisterForm();
    registerOpen.value = true;
}

function submitRegister(): void {
    if (!selectedMetaInstanceIsConfigured.value) {
        return;
    }
    registerForm.kind = 'meta_hsm';
    registerForm.post(WhatsappTemplateController.store().url, {
        onSuccess: () => {
            registerOpen.value = false;
            resetRegisterForm();
        },
    });
}

// ─── Edit ─────────────────────────────────────────────────────────────────────

const editOpen = ref(false);
const editingTemplate = ref<WhatsappTemplate | null>(null);

const editForm = useForm({
    name: '',
});

function openEdit(template: WhatsappTemplate): void {
    editingTemplate.value = template;
    editForm.name = template.name;
    editOpen.value = true;
}

function submitEdit(): void {
    if (!editingTemplate.value) {
        return;
    }
    editForm.put(
        WhatsappTemplateController.update(editingTemplate.value.id).url,
        {
            onSuccess: () => {
                editOpen.value = false;
                editingTemplate.value = null;
            },
        },
    );
}

// ─── Sync Meta Templates ─────────────────────────────────────────────────────

const syncForm = useForm({ whatsapp_instance_id: '' as string | number });

function submitSync(): void {
    if (!syncForm.whatsapp_instance_id) {
        return;
    }
    syncForm.post('/templates/sync-meta', {
        onSuccess: () => {
            syncForm.reset();
        },
    });
}

// ─── Delete ───────────────────────────────────────────────────────────────────

const deleteConfirmId = ref<number | null>(null);
const deleteForm = useForm({});

function submitDelete(): void {
    if (deleteConfirmId.value === null) {
        return;
    }
    deleteForm.delete(
        WhatsappTemplateController.destroy(deleteConfirmId.value).url,
        {
            onSuccess: () => {
                deleteConfirmId.value = null;
            },
        },
    );
}

// ─── Expanded rows ────────────────────────────────────────────────────────────

const expandedRows = ref<Set<number>>(new Set());

function toggleRow(id: number): void {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        APPROVED:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        PENDING:
            'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        REJECTED:
            'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        PAUSED: 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        DISABLED:
            'rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        FLAGGED:
            'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        IN_APPEAL:
            'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        PENDING_DELETION:
            'rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        DELETED:
            'rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        LIMIT_EXCEEDED:
            'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function qualityBadgeClass(score: string | null): string {
    const normalized = (score ?? '').toUpperCase();
    const map: Record<string, string> = {
        GREEN: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        HIGH: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        YELLOW: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        MEDIUM: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        RED: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        LOW: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    return (
        map[normalized] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function highlightVariables(body: string): string {
    // Escape HTML first — body is tenant-entered and Meta-synced, rendered via
    // v-html. Without escaping, a body like <img onerror> becomes stored XSS.
    const escaped = body
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    return escaped.replace(
        /\{\{(\d+)\}\}/g,
        '<span class="rounded bg-yellow-100 px-0.5 font-mono text-xs text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">{{$1}}</span>',
    );
}

function countBodyVars(body: string): number {
    const matches = body.match(/\{\{(\d+)\}\}/g);
    if (!matches) {
        return 0;
    }
    const nums = matches.map((m) => parseInt(m.replace(/\D/g, ''), 10));
    return Math.max(...nums);
}

const bodyVariableCount = computed(() => countBodyVars(registerForm.body));
const bodyVariableIndexes = computed(() =>
    bodyVariableCount.value > 0
        ? Array.from({ length: bodyVariableCount.value }, (_, index) =>
              String(index + 1),
          )
        : [],
);

watch(bodyVariableCount, (count) => {
    const examples: Record<string, string> = {};

    for (let index = 1; index <= count; index += 1) {
        const key = String(index);
        examples[key] = registerForm.variable_examples[key] ?? '';
    }

    registerForm.variable_examples = examples;
});

// Reset register form when dialog closes
watch(registerOpen, (open) => {
    if (!open) {
        resetRegisterForm();
    }
});

// ─── Live status refresh ───────────────────────────────────────────────────────
// Meta flips template status (PENDING → APPROVED/REJECTED) asynchronously via
// review + webhook/sync. Poll only while non-terminal templates exist so the
// table self-updates without burning requests once everything settles.
const NON_TERMINAL_STATUSES = [
    'PENDING',
    'IN_APPEAL',
    'FLAGGED',
    'PENDING_DELETION',
];

const hasPendingTemplates = computed(() =>
    props.templates.data.some((template) =>
        NON_TERMINAL_STATUSES.includes(template.status),
    ),
);

const { start: startPolling, stop: stopPolling } = usePoll(
    15000,
    { only: ['templates'] },
    { autoStart: false },
);

watch(
    hasPendingTemplates,
    (pending) => {
        if (pending) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Templates WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <div
                v-if="flash"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash }}
            </div>
            <div
                v-if="error"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400"
            >
                {{ error }}
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
                            >Templates WhatsApp</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ templates.total }} templates</span
                        >
                    </div>
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <select
                            v-model="syncForm.whatsapp_instance_id"
                            class="rounded-md border border-input bg-background px-2 py-1.5 text-xs text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="">Instância p/ sync...</option>
                            <option
                                v-for="inst in metaInstances"
                                :key="inst.id"
                                :value="inst.id"
                            >
                                {{ inst.display_name ?? inst.name }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="
                                syncForm.processing ||
                                !syncForm.whatsapp_instance_id
                            "
                            class="flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitSync"
                        >
                            <RefreshCw
                                class="h-3 w-3"
                                :class="{ 'animate-spin': syncForm.processing }"
                            />
                            {{
                                syncForm.processing
                                    ? 'Sincronizando...'
                                    : 'Sincronizar da Meta'
                            }}
                        </button>
                        <button
                            class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            @click="openRegister"
                        >
                            + Criar na Meta
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <table class="w-full min-w-[64rem] text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="w-6 px-4 py-3" />
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Nome
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Template ID
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Template Name
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Qualidade
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Variáveis
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Instância
                            </th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <template
                            v-for="template in templates.data"
                            :key="template.id"
                        >
                            <tr
                                class="cursor-pointer transition-colors hover:bg-muted/40"
                                @click="toggleRow(template.id)"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    <ChevronDown
                                        v-if="expandedRows.has(template.id)"
                                        class="h-4 w-4"
                                    />
                                    <ChevronRight v-else class="h-4 w-4" />
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ template.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >{{
                                            template.meta_template_id ?? '—'
                                        }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >{{
                                            template.meta_template_name ?? '—'
                                        }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            statusBadgeClass(template.status)
                                        "
                                        >{{ template.status }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            qualityBadgeClass(
                                                template.quality_score,
                                            )
                                        "
                                        >{{
                                            template.quality_score ?? 'N/A'
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ template.variables_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{
                                        template.whatsapp_instance?.name ?? '—'
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right" @click.stop>
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <button
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            @click="openEdit(template)"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                            @click="
                                                deleteConfirmId = template.id
                                            "
                                        >
                                            Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Expanded body row -->
                            <tr
                                v-if="expandedRows.has(template.id)"
                                class="bg-muted/20"
                            >
                                <td :colspan="9" class="px-8 py-3">
                                    <div
                                        v-if="template.header"
                                        class="mb-3 text-sm text-foreground"
                                    >
                                        <p
                                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                                        >
                                            Cabeçalho
                                        </p>
                                        <!-- eslint-disable-next-line vue/no-v-html -->
                                        <p
                                            class="leading-relaxed font-medium"
                                            v-html="
                                                highlightVariables(
                                                    template.header,
                                                )
                                            "
                                        />
                                    </div>
                                    <div
                                        v-if="template.body"
                                        class="text-sm text-foreground"
                                    >
                                        <p
                                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                                        >
                                            Corpo do Template
                                        </p>
                                        <!-- eslint-disable-next-line vue/no-v-html -->
                                        <p
                                            class="leading-relaxed whitespace-pre-wrap"
                                            v-html="
                                                highlightVariables(
                                                    template.body,
                                                )
                                            "
                                        />
                                    </div>
                                    <div
                                        v-if="template.footer"
                                        class="mt-3 text-sm text-muted-foreground"
                                    >
                                        <p
                                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                                        >
                                            Rodapé
                                        </p>
                                        <p class="leading-relaxed">
                                            {{ template.footer }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="template.buttons_json?.length"
                                        class="mt-3"
                                    >
                                        <p
                                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                                        >
                                            Botões
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="(
                                                    button, i
                                                ) in template.buttons_json"
                                                :key="i"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 bg-background px-2.5 py-1 text-xs text-foreground dark:border-sidebar-border"
                                            >
                                                <span class="font-medium">{{
                                                    button.text
                                                }}</span>
                                                <span
                                                    class="text-muted-foreground"
                                                    >·
                                                    {{
                                                        buttonTypeLabels[
                                                            button.type
                                                        ] ?? button.type
                                                    }}</span
                                                >
                                                <span
                                                    v-if="button.url"
                                                    class="font-mono text-muted-foreground"
                                                    >{{ button.url }}</span
                                                >
                                                <span
                                                    v-if="button.phone_number"
                                                    class="font-mono text-muted-foreground"
                                                    >{{
                                                        button.phone_number
                                                    }}</span
                                                >
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2"
                                    >
                                        <div v-if="template.meta_waba_id">
                                            WABA ID:
                                            <span class="font-mono">{{
                                                template.meta_waba_id
                                            }}</span>
                                        </div>
                                        <div v-if="template.last_synced_at">
                                            Última sincronização:
                                            <span>{{
                                                template.last_synced_at
                                            }}</span>
                                        </div>
                                        <div
                                            v-if="template.rejected_reason"
                                            class="sm:col-span-2"
                                        >
                                            Motivo de rejeição:
                                            <span
                                                class="font-medium text-red-600 dark:text-red-400"
                                                >{{
                                                    template.rejected_reason
                                                }}</span
                                            >
                                        </div>
                                        <details
                                            v-if="
                                                template.components_json?.length
                                            "
                                            class="sm:col-span-2"
                                        >
                                            <summary
                                                class="cursor-pointer font-medium text-foreground"
                                            >
                                                Ver componentes Meta
                                            </summary>
                                            <pre
                                                class="mt-2 max-h-52 overflow-auto rounded-md bg-muted p-3 text-[11px] leading-relaxed text-foreground"
                                                >{{
                                                    JSON.stringify(
                                                        template.components_json,
                                                        null,
                                                        2,
                                                    )
                                                }}</pre
                                            >
                                        </details>
                                    </div>
                                    <p
                                        v-if="!template.body"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Corpo do template não disponível.
                                    </p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <EmptyState
                    v-if="templates.data.length === 0"
                    :icon="FileText"
                    title="Nenhum template Meta HSM registrado"
                    description="Crie um template na Meta ou sincronize templates existentes para usar em campanhas."
                />

                <!-- Pagination -->
                <div
                    v-if="templates.links?.length > 3"
                    class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <template v-for="link in templates.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                link.active
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            ]"
                        >
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="px-3 py-1 text-sm text-muted-foreground/40"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Register Template Dialog -->
    <Dialog v-model:open="registerOpen">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Criar Template Meta</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitRegister">
                <!-- Kind selector -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Tipo de Template
                        <span class="text-red-500">*</span></label
                    >
                    <div
                        class="inline-flex items-center gap-1.5 rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-medium text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <CheckCircle2 class="h-3 w-3" />
                        Meta HSM
                    </div>
                    <p
                        v-if="registerForm.errors.kind"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.kind }}
                    </p>
                </div>

                <!-- Instance -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                    >
                        Instância WhatsApp <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="registerForm.whatsapp_instance_id"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    >
                        <option value="">Selecione...</option>
                        <option
                            v-for="instance in availableInstances"
                            :key="instance.id"
                            :value="instance.id"
                        >
                            {{ instance.display_name ?? instance.name }}
                        </option>
                    </select>
                    <p
                        v-if="registerForm.errors.whatsapp_instance_id"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.whatsapp_instance_id }}
                    </p>
                    <div
                        v-if="selectedMetaInstance"
                        class="mt-2 rounded-md border border-sidebar-border/70 bg-muted/40 px-3 py-2 text-xs text-muted-foreground dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span>Conta WABA vinculada</span>
                            <span class="font-mono text-foreground">{{
                                selectedMetaInstance.meta_waba_id ??
                                'Não configurada'
                            }}</span>
                        </div>
                        <div
                            class="mt-1 flex items-center justify-between gap-3"
                        >
                            <span>Token Meta</span>
                            <span
                                :class="
                                    selectedMetaInstance.has_meta_access_token
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                "
                            >
                                {{
                                    selectedMetaInstance.has_meta_access_token
                                        ? 'Configurado'
                                        : 'Não configurado'
                                }}
                            </span>
                        </div>
                    </div>
                    <p
                        v-if="
                            selectedMetaInstance &&
                            !selectedMetaInstanceIsConfigured
                        "
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                    >
                        Configure WABA ID e token Meta nesta instância antes de
                        criar templates pela Meta.
                    </p>
                    <p
                        v-if="metaInstances.length === 0"
                        class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                    >
                        Nenhuma instância Meta Cloud disponível.
                        <a href="/whatsapp" class="underline"
                            >Conectar instância</a
                        >.
                    </p>
                </div>

                <!-- Meta HSM specific fields -->
                <div
                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-xs text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300"
                >
                    <p class="font-semibold">Template Meta HSM</p>
                    <p class="mt-0.5">
                        O template será enviado para análise da Meta. O status
                        será atualizado por sincronização ou webhook.
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Nome Meta <span class="text-red-500">*</span></label
                    >
                    <input
                        v-model="registerForm.meta_template_name"
                        type="text"
                        placeholder="ex: campanha_janeiro"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm text-foreground placeholder:font-sans placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Use apenas letras minúsculas, números e underscore. Esse
                        nome é enviado para a Meta.
                    </p>
                    <p
                        v-if="registerForm.errors.meta_template_name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.meta_template_name }}
                    </p>
                </div>

                <!-- Internal name -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Nome interno <span class="text-red-500">*</span></label
                    >
                    <input
                        v-model="registerForm.name"
                        type="text"
                        placeholder="ex: Campanha Janeiro 2026"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p
                        v-if="registerForm.errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.name }}
                    </p>
                </div>

                <!-- Header (optional, text only) -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Cabeçalho
                        <span class="text-xs font-normal text-muted-foreground"
                            >(opcional)</span
                        ></label
                    >
                    <input
                        v-model="registerForm.header_text"
                        type="text"
                        maxlength="60"
                        placeholder="Texto curto no topo. Use {{1}} para uma variável."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Apenas texto, no máximo 60 caracteres e até uma
                        variável.
                    </p>
                    <p
                        v-if="registerForm.errors.header_text"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.header_text }}
                    </p>
                </div>

                <div v-if="headerHasVariable">
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                    >
                        <span v-text="`Exemplo do cabeçalho {{1}}`" />
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="registerForm.header_example"
                        type="text"
                        placeholder="ex: Cliente Teste"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p
                        v-if="registerForm.errors.header_example"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.header_example }}
                    </p>
                </div>

                <!-- Body -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Corpo do Template</label
                    >
                    <textarea
                        v-model="registerForm.body"
                        rows="4"
                        required
                        maxlength="1024"
                        placeholder="Cole aqui o texto do template. Use {{1}}, {{2}} para variáveis."
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Variáveis detectadas: {{ bodyVariableCount }}
                    </p>
                    <p
                        v-if="registerForm.errors.body"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.body }}
                    </p>
                </div>

                <div
                    v-if="bodyVariableIndexes.length"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <div v-for="index in bodyVariableIndexes" :key="index">
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                        >
                            <span v-text="`Exemplo de {{${index}}}`" />
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="registerForm.variable_examples[index]"
                            type="text"
                            required
                            :placeholder="
                                index === '1'
                                    ? 'ex: Cliente Teste'
                                    : 'ex: valor de exemplo'
                            "
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        />
                        <p
                            v-if="
                                registerForm.errors[
                                    `variable_examples.${index}`
                                ]
                            "
                            class="mt-1 text-xs text-red-500"
                        >
                            {{
                                registerForm.errors[
                                    `variable_examples.${index}`
                                ]
                            }}
                        </p>
                    </div>
                </div>

                <!-- Footer (optional) -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Rodapé
                        <span class="text-xs font-normal text-muted-foreground"
                            >(opcional)</span
                        ></label
                    >
                    <input
                        v-model="registerForm.footer_text"
                        type="text"
                        maxlength="60"
                        placeholder="ex: Responda PARAR para sair."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Texto fixo, sem variáveis, no máximo 60 caracteres.
                    </p>
                    <p
                        v-if="registerForm.errors.footer_text"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.footer_text }}
                    </p>
                </div>

                <!-- Buttons (optional) -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-medium text-foreground"
                            >Botões
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >(opcional, até 10)</span
                            ></label
                        >
                        <button
                            type="button"
                            :disabled="registerForm.buttons.length >= 10"
                            class="rounded-md border border-input px-2 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            @click="addButton"
                        >
                            + Adicionar botão
                        </button>
                    </div>
                    <div
                        v-if="registerForm.buttons.length"
                        class="flex flex-col gap-3"
                    >
                        <div
                            v-for="(button, index) in registerForm.buttons"
                            :key="index"
                            class="rounded-md border border-sidebar-border/70 bg-muted/30 p-3 dark:border-sidebar-border"
                        >
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="button.type"
                                    class="rounded-md border border-input bg-background px-2 py-1.5 text-xs text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                >
                                    <option
                                        v-for="(
                                            label, value
                                        ) in buttonTypeLabels"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <input
                                    v-model="button.text"
                                    type="text"
                                    maxlength="25"
                                    placeholder="Texto do botão"
                                    class="flex-1 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                />
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                    @click="removeButton(index)"
                                >
                                    Remover
                                </button>
                            </div>
                            <input
                                v-if="button.type === 'URL'"
                                v-model="button.url"
                                type="url"
                                placeholder="https://exemplo.com"
                                class="mt-2 w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <input
                                v-if="button.type === 'PHONE_NUMBER'"
                                v-model="button.phone_number"
                                type="text"
                                placeholder="ex: 5511999999999"
                                class="mt-2 w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <p
                                v-if="
                                    registerForm.errors[`buttons.${index}.text`]
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{
                                    registerForm.errors[`buttons.${index}.text`]
                                }}
                            </p>
                            <p
                                v-if="
                                    registerForm.errors[`buttons.${index}.url`]
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{
                                    registerForm.errors[`buttons.${index}.url`]
                                }}
                            </p>
                            <p
                                v-if="
                                    registerForm.errors[
                                        `buttons.${index}.phone_number`
                                    ]
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{
                                    registerForm.errors[
                                        `buttons.${index}.phone_number`
                                    ]
                                }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="registerForm.errors.buttons"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ registerForm.errors.buttons }}
                    </p>
                </div>

                <!-- Category / Language -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                            >Categoria</label
                        >
                        <select
                            v-model="registerForm.category"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="MARKETING">MARKETING</option>
                            <option value="UTILITY">UTILITY</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                            >Idioma</label
                        >
                        <select
                            v-model="registerForm.language"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="pt_BR">pt_BR</option>
                            <option value="en">en</option>
                            <option value="es">es</option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="registerOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="
                            registerForm.processing ||
                            !selectedMetaInstanceIsConfigured
                        "
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            registerForm.processing
                                ? 'Enviando...'
                                : 'Enviar para Meta'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit Template Dialog -->
    <Dialog v-model:open="editOpen">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Detalhes do Template</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                <!-- Instance (read-only — bound at creation) -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Instância WhatsApp</label
                    >
                    <p
                        class="rounded-md border border-input bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                    >
                        {{
                            editingTemplate?.whatsapp_instance?.display_name ??
                            editingTemplate?.whatsapp_instance?.name ??
                            'Sem instância'
                        }}
                    </p>
                </div>

                <!-- Name (editable) -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Nome de Exibição</label
                    >
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p
                        v-if="editForm.errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ editForm.errors.name }}
                    </p>
                </div>

                <!-- Body -->
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-foreground"
                        >Corpo do Template</label
                    >
                    <textarea
                        :value="editingTemplate?.body ?? ''"
                        readonly
                        rows="4"
                        placeholder="Use {{1}}, {{2}} para variáveis."
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                    />
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Variáveis detectadas:
                        {{ countBodyVars(editingTemplate?.body ?? '') }}
                    </p>
                </div>

                <!-- Status / Category / Language -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                            >Status</label
                        >
                        <select
                            :value="editingTemplate?.status ?? ''"
                            disabled
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="APPROVED">APPROVED</option>
                            <option value="PENDING">PENDING</option>
                            <option value="REJECTED">REJECTED</option>
                            <option value="PAUSED">PAUSED</option>
                            <option value="DISABLED">DISABLED</option>
                            <option value="FLAGGED">FLAGGED</option>
                            <option value="IN_APPEAL">IN_APPEAL</option>
                            <option value="PENDING_DELETION">
                                PENDING_DELETION
                            </option>
                            <option value="DELETED">DELETED</option>
                            <option value="LIMIT_EXCEEDED">
                                LIMIT_EXCEEDED
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                            >Categoria</label
                        >
                        <select
                            :value="editingTemplate?.category ?? ''"
                            disabled
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="MARKETING">MARKETING</option>
                            <option value="UTILITY">UTILITY</option>
                            <option value="AUTHENTICATION">
                                AUTHENTICATION
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-foreground"
                            >Idioma</label
                        >
                        <select
                            :value="editingTemplate?.language ?? ''"
                            disabled
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option value="pt_BR">pt_BR</option>
                            <option value="en">en</option>
                            <option value="es">es</option>
                        </select>
                    </div>
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
                        {{
                            editForm.processing
                                ? 'Salvando...'
                                : 'Salvar nome interno'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

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
                <DialogTitle>Excluir Template</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Tem certeza que deseja excluir este template? Campanhas ativas
                com este template impedirão a exclusão.
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
