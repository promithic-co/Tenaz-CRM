<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { preview as previewAction } from '@/actions/App/Http/Controllers/ContactListController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatRelative } from '@/lib/relative-time';

type WhatsappInstance = {
    id: number;
    name: string;
    display_name: string | null;
    provider: 'meta_cloud';
};

type ContactList = {
    id: number;
    nome: string;
    name: string;
    is_dynamic: boolean;
    entries_count: number;
    last_resolved_count: number | null;
    last_resolved_at: string | null;
    filters_json: { version: 1; match: 'all' | 'any'; rules: any[] } | null;
};

type WhatsappTemplate = {
    id: number;
    name: string;
    kind: string;
    element_name: string | null;
    body: string | null;
    variables_count: number;
    whatsapp_instance_id: number;
    whatsapp_instance: WhatsappInstance | null;
};

type CampaignDefaults = {
    contact_list_id: number | null;
    whatsapp_instance_id: number | null;
};

type Props = {
    contactLists: ContactList[];
    templates: WhatsappTemplate[];
    instances: WhatsappInstance[];
    defaults?: CampaignDefaults;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/campanhas' },
    { title: 'Campanhas', href: '/campanhas' },
    { title: 'Nova Campanha', href: '/campanhas/create' },
];

// ─── Provider-first: derive template kind from selected instance ──────────────

const selectedInstanceProvider = computed(() => {
    if (!form.whatsapp_instance_id) {
        return null;
    }
    const inst = props.instances.find(
        (i) => i.id === Number(form.whatsapp_instance_id),
    );
    return inst?.provider ?? null;
});

const templateKindForProvider = computed((): string | null => {
    if (selectedInstanceProvider.value === 'meta_cloud') {
        return 'meta_hsm';
    }

    return null;
});

// ─── Stepper ──────────────────────────────────────────────────────────────────

const currentStep = ref(1);
const totalSteps = 4;

const form = useForm({
    name: '',
    whatsapp_instance_id: (props.defaults?.whatsapp_instance_id ?? '') as
        | string
        | number,
    daily_limit: 1000,
    delay_between_ms: 1000,
    error_threshold_percent: 10,
    contact_list_id: (props.defaults?.contact_list_id ?? '') as string | number,
    whatsapp_template_id: '' as string | number,
    template_params_mapping: {} as Record<string, string>,
    scheduled_at: '',
    schedule_type: 'now',
});

const delayOptions = [
    { label: '1 segundo', value: 1000 },
    { label: '2 segundos', value: 2000 },
    { label: '3 segundos', value: 3000 },
    { label: '5 segundos', value: 5000 },
];

const thresholdOptions = [
    { label: '5%', value: 5 },
    { label: '10%', value: 10 },
    { label: '15%', value: 15 },
    { label: '20%', value: 20 },
];

// ─── Per-step validation ──────────────────────────────────────────────────────

const attemptedStep = ref<Record<number, boolean>>({});

function markAttempted(step: number): void {
    attemptedStep.value = { ...attemptedStep.value, [step]: true };
}

const step1Errors = computed(() => {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) {
        errors.name = 'O nome da campanha é obrigatório.';
    }
    if (!form.whatsapp_instance_id) {
        errors.whatsapp_instance_id = 'A instância WhatsApp é obrigatória.';
    }
    return errors;
});

const step2Errors = computed(() => {
    const errors: Record<string, string> = {};
    if (!form.contact_list_id) {
        errors.contact_list_id = 'A lista de contatos é obrigatória.';
    }
    if (!form.whatsapp_template_id) {
        errors.whatsapp_template_id = 'O template é obrigatório.';
    }
    return errors;
});

const step3Errors = computed(() => {
    const errors: Record<string, string> = {};
    for (let i = 1; i <= variablesCount.value; i++) {
        if (!form.template_params_mapping[String(i)]) {
            errors[String(i)] = `A variável {{${i}}} precisa ser mapeada.`;
        }
    }
    return errors;
});

const step1Valid = computed(() => Object.keys(step1Errors.value).length === 0);
const step2Valid = computed(() => Object.keys(step2Errors.value).length === 0);
const step3Valid = computed(() => Object.keys(step3Errors.value).length === 0);

function isStepValid(step: number): boolean {
    if (step === 1) {
        return step1Valid.value;
    }
    if (step === 2) {
        return step2Valid.value;
    }
    if (step === 3) {
        return step3Valid.value;
    }
    return true;
}

// ─── Live preview state (D-09) ────────────────────────────────────────────────

type LiveCountState = {
    loading: boolean;
    count: number | null;
    capped: boolean;
    error: string | null;
    open: boolean;
};

const livePreview = ref<Record<number, LiveCountState>>({});

function fetchLive(list: ContactList): void {
    if (!list.filters_json) {
        return;
    }
    const current = livePreview.value[list.id];
    const isOpen = current?.open ?? false;
    livePreview.value[list.id] = {
        loading: true,
        count: null,
        capped: false,
        error: null,
        open: !isOpen,
    };
    if (isOpen) {
        return;
    }
    router.post(
        previewAction.url(),
        { filters_json: list.filters_json },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['preview'],
            onSuccess: (page) => {
                const p = (page.props as any).preview;
                livePreview.value[list.id] = {
                    loading: false,
                    count: p?.count ?? 0,
                    capped: Boolean(p?.capped),
                    error: null,
                    open: true,
                };
            },
            onError: () => {
                livePreview.value[list.id] = {
                    loading: false,
                    count: null,
                    capped: false,
                    error: 'Não foi possível consultar.',
                    open: true,
                };
            },
        },
    );
}

// ─── Derived data ─────────────────────────────────────────────────────────────

const filteredTemplates = computed(() => {
    if (!templateKindForProvider.value) {
        return props.templates;
    }
    return props.templates.filter(
        (t) => t.kind === templateKindForProvider.value,
    );
});

const selectedTemplate = computed(() => {
    if (!form.whatsapp_template_id) {
        return null;
    }
    return (
        props.templates.find(
            (t) => t.id === Number(form.whatsapp_template_id),
        ) ?? null
    );
});

const selectedList = computed(() => {
    if (!form.contact_list_id) {
        return null;
    }
    return (
        props.contactLists.find((l) => l.id === Number(form.contact_list_id)) ??
        null
    );
});

const variableMappingOptions = [
    { label: 'Nome do contato', value: 'name' },
    { label: 'Telefone', value: 'phone' },
];

const variablesCount = computed(
    () => selectedTemplate.value?.variables_count ?? 0,
);

// When instance changes, clear template selection if it no longer matches the new kind
watch(
    () => form.whatsapp_instance_id,
    () => {
        const newKind = templateKindForProvider.value;
        if (
            form.whatsapp_template_id &&
            selectedTemplate.value &&
            newKind &&
            selectedTemplate.value.kind !== newKind
        ) {
            form.whatsapp_template_id = '';
        }
    },
);

// Skip step 3 if template has no variables
watch(currentStep, (step) => {
    if (step === 3 && variablesCount.value === 0) {
        currentStep.value = 4;
    }
});

// ─── Navigation ───────────────────────────────────────────────────────────────

function nextStep(): void {
    markAttempted(currentStep.value);
    if (!isStepValid(currentStep.value)) {
        return;
    }
    if (currentStep.value < totalSteps) {
        currentStep.value++;
    }
}

function prevStep(): void {
    if (currentStep.value > 1) {
        currentStep.value--;
        if (currentStep.value === 3 && variablesCount.value === 0) {
            currentStep.value = 2;
        }
    }
}

function goToStep(step: number): void {
    if (step <= currentStep.value) {
        currentStep.value = step;
    }
}

const stepLabels = [
    'Dados Básicos',
    'Lista e Template',
    'Variáveis',
    'Revisão',
];

function isStepCompleted(step: number): boolean {
    return step < currentStep.value;
}

// ─── Template preview ─────────────────────────────────────────────────────────

function previewBody(body: string): string {
    if (!body) {
        return '';
    }
    const mapping = form.template_params_mapping;
    return body.replace(/\{\{(\d+)\}\}/g, (match, num) => {
        const mapped = mapping[num];
        if (mapped === 'name') {
            return '[Nome]';
        }
        if (mapped === 'phone') {
            return '[Telefone]';
        }
        if (mapped) {
            return `[${mapped}]`;
        }
        return match;
    });
}

// ─── Template label ───────────────────────────────────────────────────────────

function templateLabel(tmpl: WhatsappTemplate): string {
    return tmpl.name;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

function submitForm(): void {
    form.scheduled_at =
        form.schedule_type === 'schedule' ? form.scheduled_at : '';
    form.post(CampaignController.store().url);
}
</script>

<template>
    <Head title="Nova Campanha" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <div class="mx-auto max-w-2xl">
                <!-- Stepper Header -->
                <div class="mb-6 flex items-center gap-2">
                    <template v-for="(label, idx) in stepLabels" :key="idx">
                        <button
                            type="button"
                            :disabled="idx + 1 > currentStep"
                            class="flex items-center gap-2 disabled:cursor-not-allowed"
                            @click="goToStep(idx + 1)"
                        >
                            <span
                                :class="[
                                    'flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition-colors',
                                    currentStep === idx + 1
                                        ? 'bg-primary text-primary-foreground'
                                        : isStepCompleted(idx + 1)
                                          ? 'bg-green-500 text-white'
                                          : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <svg
                                    v-if="isStepCompleted(idx + 1)"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span v-else>{{ idx + 1 }}</span>
                            </span>
                            <span
                                :class="[
                                    'hidden text-sm sm:block',
                                    currentStep === idx + 1
                                        ? 'font-semibold text-foreground'
                                        : 'text-muted-foreground',
                                ]"
                            >
                                {{ label }}
                            </span>
                        </button>
                        <div
                            v-if="idx < stepLabels.length - 1"
                            class="flex-1 border-t border-sidebar-border/70 dark:border-sidebar-border"
                        />
                    </template>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <!-- Step 1: Dados Básicos -->
                    <div
                        v-if="currentStep === 1"
                        class="flex flex-col gap-4 p-6"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Dados Básicos
                        </h2>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Nome da Campanha
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Ex: Campanha SIAPE Janeiro 2026"
                                :class="[
                                    'w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none',
                                    attemptedStep[1] && step1Errors.name
                                        ? 'border-red-500'
                                        : 'border-input',
                                ]"
                            />
                            <p
                                v-if="attemptedStep[1] && step1Errors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ step1Errors.name }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Instância WhatsApp
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.whatsapp_instance_id"
                                :class="[
                                    'w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none',
                                    attemptedStep[1] &&
                                    step1Errors.whatsapp_instance_id
                                        ? 'border-red-500'
                                        : 'border-input',
                                ]"
                            >
                                <option value="">Selecione...</option>
                                <option
                                    v-for="instance in instances"
                                    :key="instance.id"
                                    :value="instance.id"
                                >
                                    {{ instance.display_name ?? instance.name }}
                                    (Meta Cloud)
                                </option>
                            </select>
                            <p
                                v-if="
                                    attemptedStep[1] &&
                                    step1Errors.whatsapp_instance_id
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ step1Errors.whatsapp_instance_id }}
                            </p>
                            <p
                                v-if="instances.length === 0"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                Nenhuma instância conectada.
                                <a href="/whatsapp" class="underline"
                                    >Conectar instância</a
                                >.
                            </p>
                            <p
                                v-if="templateKindForProvider"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Templates disponíveis: Meta HSM
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-foreground"
                                    >Limite Diário</label
                                >
                                <input
                                    v-model.number="form.daily_limit"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                />
                                <p
                                    v-if="form.errors.daily_limit"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.daily_limit }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-foreground"
                                    >Atraso Entre Mensagens</label
                                >
                                <select
                                    v-model.number="form.delay_between_ms"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                >
                                    <option
                                        v-for="opt in delayOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                                >Limiar de Falha</label
                            >
                            <select
                                v-model.number="form.error_threshold_percent"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option
                                    v-for="opt in thresholdOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">
                                A campanha será pausada automaticamente se a
                                taxa de falha ultrapassar este limiar.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2: Lista e Template -->
                    <div
                        v-else-if="currentStep === 2"
                        class="flex flex-col gap-4 p-6"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Lista e Template
                        </h2>

                        <!-- Provider hint -->
                        <div
                            v-if="templateKindForProvider"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-400"
                        >
                            Mostrando templates
                            <strong>Meta HSM</strong>
                            compatíveis com a instância selecionada.
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Lista de Contato
                                <span class="text-red-500">*</span>
                            </label>

                            <!-- Dynamic-aware list selection cards -->
                            <div class="flex flex-col gap-2">
                                <label
                                    v-for="list in contactLists"
                                    :key="list.id"
                                    :class="[
                                        'flex cursor-pointer flex-col gap-2 rounded-lg border p-4 transition-colors',
                                        Number(form.contact_list_id) === list.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-muted-foreground/40',
                                        attemptedStep[2] &&
                                        step2Errors.contact_list_id
                                            ? 'border-red-500'
                                            : '',
                                    ]"
                                >
                                    <!-- Radio + name row -->
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="flex items-start gap-3">
                                            <input
                                                v-model="form.contact_list_id"
                                                type="radio"
                                                :value="list.id"
                                                class="mt-0.5 text-primary"
                                            />
                                            <div class="space-y-0.5">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="text-sm font-semibold text-foreground"
                                                        >{{ list.name }}</span
                                                    >
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
                                                </div>
                                                <!-- D-08: stale count (dynamic) or static count -->
                                                <p
                                                    class="text-xs text-muted-foreground tabular-nums"
                                                >
                                                    <template
                                                        v-if="list.is_dynamic"
                                                    >
                                                        ~{{
                                                            list.last_resolved_count ??
                                                            0
                                                        }}
                                                        leads, atualizado
                                                        {{
                                                            list.last_resolved_at
                                                                ? formatRelative(
                                                                      list.last_resolved_at,
                                                                  )
                                                                : 'nunca'
                                                        }}
                                                    </template>
                                                    <template v-else>
                                                        {{ list.entries_count }}
                                                        leads
                                                    </template>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- D-09: "Ver leads atuais" button for dynamic lists -->
                                        <div
                                            v-if="list.is_dynamic"
                                            class="shrink-0"
                                        >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click.prevent="fetchLive(list)"
                                            >
                                                Ver leads atuais
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- D-09: Inline popover content (no toast lib) -->
                                    <div
                                        v-if="
                                            list.is_dynamic &&
                                            livePreview[list.id]?.open
                                        "
                                        class="ml-6 rounded-md border border-border bg-card p-3"
                                    >
                                        <p
                                            class="mb-1 text-sm font-semibold text-foreground"
                                        >
                                            Contagem ao vivo
                                        </p>
                                        <p
                                            v-if="livePreview[list.id]?.loading"
                                            class="text-sm text-muted-foreground"
                                        >
                                            Consultando…
                                        </p>
                                        <p
                                            v-else-if="
                                                livePreview[list.id]?.error
                                            "
                                            class="text-sm text-destructive"
                                        >
                                            {{ livePreview[list.id]?.error }}
                                        </p>
                                        <!-- D-07: capped variant -->
                                        <p
                                            v-else-if="
                                                livePreview[list.id]?.capped
                                            "
                                            class="text-sm text-foreground tabular-nums"
                                        >
                                            5000+ leads correspondem agora
                                        </p>
                                        <p
                                            v-else-if="
                                                livePreview[list.id]?.count ===
                                                0
                                            "
                                            class="text-sm text-foreground"
                                        >
                                            0 leads correspondem agora. A
                                            campanha não vai disparar até que
                                            filtros encontrem leads.
                                        </p>
                                        <p
                                            v-else-if="
                                                livePreview[list.id]?.count !=
                                                null
                                            "
                                            class="text-sm text-foreground tabular-nums"
                                        >
                                            {{ livePreview[list.id]?.count }}
                                            leads correspondem agora
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <p
                                v-if="
                                    attemptedStep[2] &&
                                    step2Errors.contact_list_id
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ step2Errors.contact_list_id }}
                            </p>

                            <!-- D-10: Warning banner when dynamic list selected -->
                            <div
                                v-if="selectedList?.is_dynamic"
                                class="mt-3 rounded-md bg-amber-100 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                role="status"
                            >
                                Lista dinâmica — vai resolver os leads no
                                momento do disparo. A contagem acima pode mudar.
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Template <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.whatsapp_template_id"
                                :class="[
                                    'w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none',
                                    attemptedStep[2] &&
                                    step2Errors.whatsapp_template_id
                                        ? 'border-red-500'
                                        : 'border-input',
                                ]"
                            >
                                <option value="">Selecione...</option>
                                <option
                                    v-for="tmpl in filteredTemplates"
                                    :key="tmpl.id"
                                    :value="tmpl.id"
                                >
                                    {{ templateLabel(tmpl) }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    attemptedStep[2] &&
                                    step2Errors.whatsapp_template_id
                                "
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ step2Errors.whatsapp_template_id }}
                            </p>
                            <p
                                v-if="filteredTemplates.length === 0"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                Nenhum template compatível disponível.
                                <a href="/templates" class="underline"
                                    >Criar template</a
                                >.
                            </p>
                        </div>

                        <!-- Template preview -->
                        <div
                            v-if="selectedTemplate?.body"
                            class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4 dark:border-sidebar-border"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Preview do Template
                            </p>
                            <p
                                class="text-sm whitespace-pre-wrap text-foreground"
                            >
                                {{ selectedTemplate.body }}
                            </p>
                            <p
                                v-if="selectedTemplate.variables_count > 0"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{ selectedTemplate.variables_count }}
                                variável(is) a mapear no próximo passo.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3: Mapeamento de Variáveis -->
                    <div
                        v-else-if="currentStep === 3"
                        class="flex flex-col gap-4 p-6"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Mapeamento de Variáveis
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Defina quais campos de cada contato serão usados
                            para preencher as variáveis do template.
                        </p>

                        <div
                            v-for="n in variablesCount"
                            :key="n"
                            class="flex flex-col gap-1"
                        >
                            <label class="text-sm font-medium text-foreground">
                                Variável
                                <span class="font-mono"
                                    >&#123;&#123;{{ n }}&#125;&#125;</span
                                >
                            </label>
                            <select
                                v-model="
                                    form.template_params_mapping[String(n)]
                                "
                                :class="[
                                    'w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none',
                                    attemptedStep[3] && step3Errors[String(n)]
                                        ? 'border-red-500'
                                        : 'border-input',
                                ]"
                            >
                                <option value="">Selecione...</option>
                                <option
                                    v-for="opt in variableMappingOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    attemptedStep[3] && step3Errors[String(n)]
                                "
                                class="text-xs text-red-500"
                            >
                                {{ step3Errors[String(n)] }}
                            </p>
                        </div>

                        <!-- Preview with replacements -->
                        <div
                            v-if="selectedTemplate?.body"
                            class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4 dark:border-sidebar-border"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Preview com Mapeamento
                            </p>
                            <p
                                class="text-sm whitespace-pre-wrap text-foreground"
                            >
                                {{ previewBody(selectedTemplate.body) }}
                            </p>
                        </div>
                    </div>

                    <!-- Step 4: Revisão e Confirmar -->
                    <div
                        v-else-if="currentStep === 4"
                        class="flex flex-col gap-4 p-6"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Revisão e Confirmar
                        </h2>

                        <!-- Summary card -->
                        <div
                            class="divide-y divide-sidebar-border/70 rounded-lg border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border"
                        >
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Nome</span
                                >
                                <span class="text-sm text-foreground">{{
                                    form.name || '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Instância</span
                                >
                                <span class="text-sm text-foreground">
                                    {{
                                        instances.find(
                                            (i) =>
                                                i.id ===
                                                Number(
                                                    form.whatsapp_instance_id,
                                                ),
                                        )?.display_name ??
                                        instances.find(
                                            (i) =>
                                                i.id ===
                                                Number(
                                                    form.whatsapp_instance_id,
                                                ),
                                        )?.name ??
                                        '—'
                                    }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Lista</span
                                >
                                <span class="text-sm text-foreground"
                                    >{{ selectedList?.name ?? '—' }} ({{
                                        selectedList?.entries_count ?? 0
                                    }}
                                    contatos)</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Template</span
                                >
                                <span class="text-sm text-foreground">{{
                                    selectedTemplate?.name ?? '—'
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Limite Diário</span
                                >
                                <span class="text-sm text-foreground"
                                    >{{ form.daily_limit }} mensagens</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Atraso</span
                                >
                                <span class="text-sm text-foreground"
                                    >{{ form.delay_between_ms }}ms</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <span
                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                    >Limiar de Falha</span
                                >
                                <span class="text-sm text-foreground"
                                    >{{ form.error_threshold_percent }}%</span
                                >
                            </div>
                        </div>

                        <!-- Scheduling -->
                        <div class="flex flex-col gap-3">
                            <p class="text-sm font-medium text-foreground">
                                Agendamento
                            </p>
                            <div class="flex flex-col gap-2">
                                <label
                                    class="flex items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        v-model="form.schedule_type"
                                        type="radio"
                                        value="now"
                                        class="text-primary"
                                    />
                                    Enviar agora
                                </label>
                                <label
                                    class="flex items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        v-model="form.schedule_type"
                                        type="radio"
                                        value="schedule"
                                        class="text-primary"
                                    />
                                    Agendar para
                                </label>
                            </div>
                            <input
                                v-if="form.schedule_type === 'schedule'"
                                v-model="form.scheduled_at"
                                type="datetime-local"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div
                        class="flex items-center justify-between border-t border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border"
                    >
                        <button
                            v-if="currentStep > 1"
                            type="button"
                            class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                            @click="prevStep"
                        >
                            Voltar
                        </button>
                        <div v-else />

                        <button
                            v-if="currentStep < totalSteps"
                            type="button"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            @click="nextStep"
                        >
                            Próximo
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="form.processing"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                            @click="submitForm"
                        >
                            {{
                                form.processing
                                    ? 'Criando...'
                                    : 'Criar Campanha'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
