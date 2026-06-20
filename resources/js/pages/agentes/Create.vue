<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronDown, HeartHandshake, Megaphone } from 'lucide-vue-next';
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
    tagline: string;
    icon: string;
    mode: string | null;
    use_cases: string[];
    example_first_message: string;
};

type AgentSpecialization = {
    value: string;
    label: string;
    description: string;
    badge_classes: string;
};

type Props = {
    instances: InstanceOption[];
    templates: AgentTemplate[];
    default_template: string;
    specializations: AgentSpecialization[];
    default_specialization: string;
};

const iconMap: Record<string, unknown> = {
    'heart-handshake': HeartHandshake,
    megaphone: Megaphone,
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

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agentes', href: '/agentes' },
    { title: 'Novo agente', href: '/agentes/create' },
];

const initialTemplate =
    props.templates.find((t) => t.slug === props.default_template) ??
    props.templates[0];
const initialSpecialization =
    props.specializations.find(
        (item) => item.value === props.default_specialization,
    ) ?? props.specializations[0];
const specializationPickerOpen = ref(false);

const form = useForm({
    template_slug: initialTemplate?.slug ?? '',
    agent_niche: initialSpecialization?.value ?? 'inss',
    name: initialTemplate?.name ?? '',
    company_name: '',
    description: '',
    whatsapp_instance_id: null as number | null,
});

const selectedTemplate = computed(
    () => props.templates.find((t) => t.slug === form.template_slug) ?? null,
);
const selectedSpecialization = computed(
    () =>
        props.specializations.find((item) => item.value === form.agent_niche) ??
        props.specializations[0],
);

const isBulkMode = computed(
    () => selectedTemplate.value?.mode === 'prospeccao',
);

function selectTemplate(tpl: AgentTemplate): void {
    form.template_slug = tpl.slug;
    const isDefaultName = props.templates.some((t) => t.name === form.name);
    if (!form.name || isDefaultName) {
        form.name = tpl.name;
    }
}

function modeFor(tpl: AgentTemplate) {
    return tpl.mode ? modeConfig[tpl.mode] : null;
}

function selectSpecialization(specialization: AgentSpecialization): void {
    form.agent_niche = specialization.value;
    specializationPickerOpen.value = false;
}
</script>

<template>
    <Head title="Novo agente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-5 p-4 lg:p-8">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
            >
                <h1 class="text-base font-semibold text-foreground">
                    Criar agente
                </h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    Um agente representa uma persona/configuração operacional
                    vinculada a uma instância WhatsApp.
                </p>

                <!-- Template picker -->
                <div class="mt-6">
                    <p class="mb-1 text-sm font-medium text-foreground">
                        Escolha um modelo
                    </p>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Cada modelo vem com personalidade e saudação prontas
                        para o caso de uso. Você pode ajustar tudo depois.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="tpl in templates"
                            :key="tpl.slug"
                            type="button"
                            @click="selectTemplate(tpl)"
                            :class="[
                                'rounded-lg border p-4 text-left transition-all duration-150',
                                form.template_slug === tpl.slug && modeFor(tpl)
                                    ? `${modeFor(tpl)!.borderClass} ${modeFor(tpl)!.bgClass} ring-1 ${modeFor(tpl)!.ringClass}`
                                    : form.template_slug === tpl.slug
                                      ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                      : 'border-border hover:border-primary/30',
                            ]"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="iconMap[tpl.icon]"
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

                                    <!-- Preview first message -->
                                    <div
                                        class="mt-3 rounded-md bg-muted/60 px-3 py-2"
                                    >
                                        <p
                                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Primeira mensagem
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-foreground"
                                        >
                                            "{{ tpl.example_first_message }}"
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <p
                        v-if="form.errors.template_slug"
                        class="mt-1.5 text-xs text-red-500"
                    >
                        {{ form.errors.template_slug }}
                    </p>
                </div>

                <!-- Specialization picker -->
                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-foreground"
                        >Público-alvo</label
                    >
                    <div class="relative">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border border-input bg-background px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-muted/40 focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            @click="
                                specializationPickerOpen =
                                    !specializationPickerOpen
                            "
                        >
                            <span class="min-w-0">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            selectedSpecialization?.badge_classes
                                        "
                                    >
                                        {{ selectedSpecialization?.label }}
                                    </span>
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                        >{{
                                            selectedSpecialization?.description
                                        }}</span
                                    >
                                </span>
                            </span>
                            <ChevronDown
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                        </button>

                        <div
                            v-if="specializationPickerOpen"
                            class="absolute z-20 mt-2 w-full overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
                        >
                            <button
                                v-for="specialization in specializations"
                                :key="specialization.value"
                                type="button"
                                class="flex w-full items-start gap-3 px-3 py-2.5 text-left transition-colors hover:bg-muted/60"
                                @click="selectSpecialization(specialization)"
                            >
                                <span
                                    class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border border-border"
                                >
                                    <Check
                                        v-if="
                                            form.agent_niche ===
                                            specialization.value
                                        "
                                        class="h-3 w-3 text-primary"
                                    />
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-medium text-foreground"
                                        >{{ specialization.label }}</span
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ specialization.description }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="form.errors.agent_niche"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.agent_niche }}
                    </p>
                </div>

                <!-- Hint para modo bulk -->
                <div
                    v-if="isBulkMode"
                    class="mt-4 flex items-start gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2.5"
                >
                    <Megaphone
                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500"
                    />
                    <p class="text-xs text-amber-600 dark:text-amber-400">
                        Este agente funciona melhor vinculado a uma instância
                        usada em <strong>campanhas de disparo</strong> (Meta
                        Cloud, URA ou discadora).
                    </p>
                </div>

                <!-- Form fields -->
                <form
                    class="mt-6 space-y-4"
                    @submit.prevent="form.post('/agentes')"
                >
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
                                placeholder="Ex: Alicia, Tenaz CRM, Consultora INSS"
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
                                Empresa / financeira
                            </label>
                            <input
                                v-model="form.company_name"
                                type="text"
                                maxlength="100"
                                placeholder="Ex: Amec, Banco Pan, BMG"
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
                            >Descrição (opcional)</label
                        >
                        <input
                            v-model="form.description"
                            type="text"
                            maxlength="255"
                            placeholder="Ex: WhatsApp principal de atendimento"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
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

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Link
                            href="/agentes"
                            class="rounded-lg border border-input px-3 py-2 text-xs text-muted-foreground hover:bg-muted"
                        >
                            Cancelar
                        </Link>
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
