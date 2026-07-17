<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    HeartHandshake,
    Megaphone,
    Search,
    Sparkles,
    Stethoscope,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TemplateVariablesForm, {
    type TemplateVariableField,
} from '@/components/TemplateVariablesForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type InstanceOption = {
    id: number;
    name: string;
    display_name: string | null;
    phone_number: string | null;
};

type AgentTemplate = {
    slug: string;
    name: string;
    label: string;
    description: string;
    category: string | null;
    tagline: string;
    icon: string;
    mode: string | null;
    use_cases: string[];
    example_first_message: string;
    variables_schema: TemplateVariableField[] | null;
};

type Props = {
    instances: InstanceOption[];
    templates: AgentTemplate[];
    default_template: string | null;
};

const iconMap: Record<string, unknown> = {
    'heart-handshake': HeartHandshake,
    megaphone: Megaphone,
    'building-2': Building2,
    stethoscope: Stethoscope,
    sparkles: Sparkles,
};

const modeConfig: Record<
    string,
    {
        label: string;
        classes: string;
        ringClass: string;
        borderClass: string;
        bgClass: string;
    }
> = {
    receptivo: {
        label: 'RECEPTIVO',
        classes: 'bg-teal-500/10 text-teal-500',
        ringClass: 'ring-teal-500/30',
        borderClass: 'border-teal-500',
        bgClass: 'bg-teal-500/5',
    },
    prospeccao: {
        label: 'PROSPECÇÃO',
        classes: 'bg-amber-500/10 text-amber-500',
        ringClass: 'ring-amber-500/30',
        borderClass: 'border-amber-500',
        bgClass: 'bg-amber-500/5',
    },
};

const categoryLabels: Record<string, string> = {
    'credito-consignado': 'Crédito consignado',
    generico: 'Atendimento geral',
};

const BUILT_IN_KEYS = ['agent_name', 'company_name', 'description'];

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agentes', href: '/agentes' },
    { title: 'Novo agente', href: '/agentes/create' },
];

const steps = [
    { number: 1, title: 'Modelo' },
    { number: 2, title: 'Personalização' },
    { number: 3, title: 'WhatsApp' },
];

const currentStep = ref(1);
const searchQuery = ref('');

const initialTemplate =
    props.templates.find((t) => t.slug === props.default_template) ??
    props.templates[0];

const form = useForm({
    template_slug: initialTemplate?.slug ?? '',
    name: initialTemplate?.name ?? '',
    company_name: '',
    description: '',
    variables: {} as Record<string, string>,
    whatsapp_instance_id: null as number | null,
});

const selectedTemplate = computed(
    () => props.templates.find((t) => t.slug === form.template_slug) ?? null,
);

const extraFields = computed<TemplateVariableField[]>(
    () =>
        selectedTemplate.value?.variables_schema?.filter(
            (field) => !BUILT_IN_KEYS.includes(field.key),
        ) ?? [],
);

const filteredTemplates = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return props.templates;
    }

    return props.templates.filter((tpl) =>
        [tpl.name, tpl.label, tpl.description, tpl.tagline, ...tpl.use_cases]
            .join(' ')
            .toLowerCase()
            .includes(query),
    );
});

const templatesByCategory = computed(() => {
    const groups = new Map<string, AgentTemplate[]>();
    for (const tpl of filteredTemplates.value) {
        const category = tpl.category ?? 'outros';
        groups.set(category, [...(groups.get(category) ?? []), tpl]);
    }
    return [...groups.entries()].map(([category, templates]) => ({
        category,
        label: categoryLabels[category] ?? category.replace(/-/g, ' '),
        templates,
    }));
});

const isBulkMode = computed(
    () => selectedTemplate.value?.mode === 'prospeccao',
);

const firstMessagePreview = computed(() => {
    const message = selectedTemplate.value?.example_first_message ?? '';
    return form.company_name
        ? message.replace('[empresa]', form.company_name)
        : message;
});

const canAdvanceFromCustomization = computed(() => {
    if (!form.name.trim() || !form.company_name.trim()) {
        return false;
    }
    return extraFields.value.every(
        (field) => !field.required || (form.variables[field.key] ?? '').trim(),
    );
});

function selectTemplate(tpl: AgentTemplate): void {
    const previousSlug = form.template_slug;
    form.template_slug = tpl.slug;

    const isDefaultName = props.templates.some((t) => t.name === form.name);
    if (!form.name || isDefaultName) {
        form.name = tpl.name;
    }
    if (previousSlug !== tpl.slug) {
        form.variables = {};
    }
}

function modeFor(tpl: AgentTemplate) {
    return tpl.mode ? modeConfig[tpl.mode] : null;
}

function submit(): void {
    form.post('/agentes', {
        onError: (errors) => {
            const keys = Object.keys(errors);
            if (keys.includes('template_slug')) {
                currentStep.value = 1;
            } else if (
                keys.some(
                    (key) =>
                        ['name', 'company_name', 'description'].includes(key) ||
                        key.startsWith('variables.'),
                )
            ) {
                currentStep.value = 2;
            }
        },
    });
}
</script>

<template>
    <Head title="Novo agente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-5 p-3 sm:p-4 lg:p-8">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 sm:p-6 dark:border-sidebar-border"
            >
                <h1 class="text-base font-semibold text-foreground">
                    Criar agente
                </h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    Escolha um modelo pronto, personalize com os dados da sua
                    operação e vincule uma instância WhatsApp.
                </p>

                <!-- Step indicator -->
                <div class="mt-5 flex items-center gap-2">
                    <template v-for="(step, index) in steps" :key="step.number">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-full text-[11px] font-semibold"
                                :class="
                                    currentStep >= step.number
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ step.number }}
                            </span>
                            <span
                                class="text-xs font-medium"
                                :class="
                                    currentStep >= step.number
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ step.title }}
                            </span>
                        </div>
                        <div
                            v-if="index < steps.length - 1"
                            class="h-px flex-1 bg-border"
                        ></div>
                    </template>
                </div>

                <!-- Step 1: template gallery -->
                <div v-if="currentStep === 1" class="mt-6">
                    <div class="relative mb-4">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar modelo por nome ou caso de uso..."
                            class="w-full rounded-lg border border-input bg-background py-2 pr-3 pl-9 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>

                    <p
                        v-if="!filteredTemplates.length"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Nenhum modelo encontrado para "{{ searchQuery }}".
                    </p>

                    <div
                        v-for="group in templatesByCategory"
                        :key="group.category"
                        class="mb-5"
                    >
                        <p
                            class="mb-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                v-for="tpl in group.templates"
                                :key="tpl.slug"
                                type="button"
                                @click="selectTemplate(tpl)"
                                :class="[
                                    'rounded-lg border p-4 text-left transition-all duration-150',
                                    form.template_slug === tpl.slug &&
                                    modeFor(tpl)
                                        ? `${modeFor(tpl)!.borderClass} ${modeFor(tpl)!.bgClass} ring-1 ${modeFor(tpl)!.ringClass}`
                                        : form.template_slug === tpl.slug
                                          ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                          : 'border-border hover:border-primary/30',
                                ]"
                            >
                                <div class="flex items-start gap-3">
                                    <component
                                        :is="
                                            iconMap[tpl.icon] ?? HeartHandshake
                                        "
                                        class="mt-0.5 h-5 w-5 shrink-0"
                                        :class="[
                                            form.template_slug === tpl.slug
                                                ? tpl.mode === 'receptivo'
                                                    ? 'text-teal-500'
                                                    : tpl.mode === 'prospeccao'
                                                      ? 'text-amber-500'
                                                      : 'text-primary'
                                                : 'text-muted-foreground/40',
                                        ]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-sm font-semibold text-foreground"
                                                >{{ tpl.name }}</span
                                            >
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >— {{ tpl.label }}</span
                                            >
                                            <span
                                                v-if="modeFor(tpl)"
                                                class="rounded-full px-1.5 py-0.5 text-[9px] font-bold tracking-widest"
                                                :class="modeFor(tpl)!.classes"
                                            >
                                                {{ modeFor(tpl)!.label }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ tpl.description }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs font-medium text-foreground/60 italic"
                                        >
                                            {{ tpl.tagline }}
                                        </p>

                                        <!-- Use case chips -->
                                        <div
                                            v-if="tpl.use_cases?.length"
                                            class="mt-2 flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="uc in tpl.use_cases"
                                                :key="uc"
                                                class="rounded-md border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                            >
                                                {{ uc }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.template_slug"
                        class="mt-1.5 text-xs text-red-500"
                    >
                        {{ form.errors.template_slug }}
                    </p>

                    <div class="flex items-center justify-end gap-2 pt-4">
                        <Link
                            href="/agentes"
                            class="rounded-lg border border-input px-3 py-2 text-xs text-muted-foreground hover:bg-muted"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="button"
                            :disabled="!form.template_slug"
                            class="rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            @click="currentStep = 2"
                        >
                            Continuar
                        </button>
                    </div>
                </div>

                <!-- Step 2: customization from variables_schema -->
                <div v-else-if="currentStep === 2" class="mt-6 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                Nome do agente
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                maxlength="100"
                                placeholder="Ex: Alicia, Sofia, Consultora"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                Nome da empresa
                            </label>
                            <input
                                v-model="form.company_name"
                                type="text"
                                maxlength="100"
                                placeholder="Ex: Minha Empresa"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            />
                            <p
                                v-if="form.errors.company_name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.company_name }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-foreground"
                            >Descrição
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></label
                        >
                        <input
                            v-model="form.description"
                            type="text"
                            maxlength="255"
                            placeholder="Ex: WhatsApp principal de atendimento"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>

                    <TemplateVariablesForm
                        v-model="form.variables"
                        :fields="extraFields"
                        :errors="form.errors as Record<string, string>"
                    />

                    <!-- First message preview -->
                    <div
                        v-if="firstMessagePreview"
                        class="rounded-md bg-muted/60 px-3 py-2"
                    >
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Exemplo de primeira mensagem
                        </p>
                        <p class="mt-0.5 text-xs text-foreground">
                            "{{ firstMessagePreview }}"
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-xs text-muted-foreground hover:bg-muted"
                            @click="currentStep = 1"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Voltar
                        </button>
                        <button
                            type="button"
                            :disabled="!canAdvanceFromCustomization"
                            class="rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            @click="currentStep = 3"
                        >
                            Continuar
                        </button>
                    </div>
                </div>

                <!-- Step 3: WhatsApp instance -->
                <form
                    v-else
                    class="mt-6 space-y-4"
                    @submit.prevent="submit"
                >
                    <!-- Hint para modo bulk -->
                    <div
                        v-if="isBulkMode"
                        class="flex items-start gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2.5"
                    >
                        <Megaphone
                            class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500"
                        />
                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            Este agente funciona melhor vinculado a uma
                            instância usada em
                            <strong>campanhas de disparo</strong> (Meta Cloud,
                            URA ou discadora).
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-foreground"
                            >Instância WhatsApp
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></label
                        >
                        <select
                            v-model.number="form.whatsapp_instance_id"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        >
                            <option :value="null">
                                Criar sem vincular WhatsApp
                            </option>
                            <option
                                v-for="instance in instances"
                                :key="instance.id"
                                :value="instance.id"
                            >
                                {{ instance.display_name || instance.name }}
                                <template v-if="instance.phone_number">
                                    · {{ instance.phone_number }}</template
                                >
                            </option>
                        </select>
                        <p
                            v-if="form.errors.whatsapp_instance_id"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.whatsapp_instance_id }}
                        </p>
                        <p
                            v-if="!instances.length"
                            class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                        >
                            Você pode criar o agente agora. Ele ficará inativo
                            até vincular uma instância WhatsApp.
                            <Link
                                href="/whatsapp"
                                class="text-primary underline"
                                >Conectar WhatsApp</Link
                            >
                        </p>
                        <p
                            v-else-if="form.whatsapp_instance_id === null"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            O agente ficará inativo até que uma instância
                            WhatsApp seja vinculada.
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-xs text-muted-foreground hover:bg-muted"
                            @click="currentStep = 2"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Voltar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Criando agente...'
                                    : 'Criar agente'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
