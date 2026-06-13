<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { HeartHandshake, Megaphone } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { storeAgent, storeInstance, storePersona } from '@/actions/App/Http/Controllers/OnboardingController';

// ─── Types ────────────────────────────────────────────────────────────────────

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

type InstanceOption = {
    id: number;
    name: string;
    display_name: string | null;
    phone_number: string | null;
};

type PersonaValues = {
    agent_name: string;
    company_name: string;
    agent_personality: string;
    agent_greeting: string;
};

type Props = {
    current_step: 'template' | 'instance' | 'persona' | 'complete';
    templates?: AgentTemplate[];
    default_template?: string | null;
    instances?: InstanceOption[];
    persona_values?: PersonaValues | null;
    agent_name?: string | null;
    is_ready?: boolean;
};

const props = defineProps<Props>();

// ─── Step indicator ───────────────────────────────────────────────────────────

const steps = [
    { key: 'template', label: 'Agente' },
    { key: 'instance', label: 'WhatsApp' },
    { key: 'persona', label: 'Personalidade' },
];

const activeStepIndex = computed(() => {
    if (props.current_step === 'complete') { return 3; }
    return steps.findIndex((s) => s.key === props.current_step);
});

// ─── Icon + mode config ───────────────────────────────────────────────────────

const iconMap: Record<string, unknown> = {
    'heart-handshake': HeartHandshake,
    megaphone: Megaphone,
};

const modeConfig: Record<string, { label: string; classes: string; ringClass: string; borderClass: string; bgClass: string }> = {
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

function modeFor(tpl: AgentTemplate) {
    return tpl.mode ? (modeConfig[tpl.mode] ?? null) : null;
}

// ─── Step 1: Agent template ───────────────────────────────────────────────────

const initialTemplate = computed(() => {
    const list = props.templates ?? [];
    return list.find((t) => t.slug === props.default_template) ?? list[0] ?? null;
});

const agentForm = useForm({
    template_slug: initialTemplate.value?.slug ?? '',
});

const selectedTemplate = computed(() =>
    (props.templates ?? []).find((t) => t.slug === agentForm.template_slug) ?? null,
);

function selectTemplate(tpl: AgentTemplate): void {
    agentForm.template_slug = tpl.slug;
}

function submitAgent(): void {
    agentForm.post(storeAgent().url);
}

// ─── Step 2: WhatsApp instance ────────────────────────────────────────────────

const selectedInstanceId = ref<number | null>(null);

const instanceForm = useForm({
    whatsapp_instance_id: null as number | null,
});

function submitInstance(): void {
    instanceForm.whatsapp_instance_id = selectedInstanceId.value;
    instanceForm.post(storeInstance().url);
}

// ─── Step 3: Persona ──────────────────────────────────────────────────────────

const personaForm = useForm({
    agent_name: props.persona_values?.agent_name ?? '',
    company_name: props.persona_values?.company_name ?? '',
    agent_personality: props.persona_values?.agent_personality ?? '',
    agent_greeting: props.persona_values?.agent_greeting ?? '',
});

function submitPersona(): void {
    personaForm.post(storePersona().url);
}
</script>

<template>
    <Head title="Onboarding" />

    <AppLayout>
        <div class="mx-auto max-w-3xl p-4 lg:p-8">

            <!-- Page heading -->
            <h1 class="text-2xl font-semibold text-foreground">
                Configure seu primeiro agente
            </h1>

            <!-- Step indicator -->
            <div v-if="current_step !== 'complete'" class="mt-6">
                <div class="flex items-center gap-0">
                    <template v-for="(step, idx) in steps" :key="step.key">
                        <!-- Step item -->
                        <div class="flex flex-1 flex-col items-center gap-1.5">
                            <!-- Dot + number -->
                            <div
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                                    idx < activeStepIndex
                                        ? 'bg-primary/20 text-primary'
                                        : idx === activeStepIndex
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <svg
                                    v-if="idx < activeStepIndex"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span v-else>{{ idx + 1 }}</span>
                            </div>
                            <!-- Label -->
                            <span
                                :class="[
                                    'text-xs font-medium',
                                    idx === activeStepIndex ? 'text-foreground' : 'text-muted-foreground',
                                ]"
                            >
                                {{ step.label }}
                            </span>
                        </div>

                        <!-- Connector line (between steps) -->
                        <div
                            v-if="idx < steps.length - 1"
                            :class="[
                                'h-px flex-1 transition-colors',
                                idx < activeStepIndex ? 'bg-primary/40' : 'bg-border',
                            ]"
                        />
                    </template>
                </div>
            </div>

            <Separator class="mt-6" />

            <!-- ─── Step 1: Agent Template ─────────────────────────────────── -->
            <div v-if="current_step === 'template'" class="mt-6 space-y-6">

                <!-- No templates empty state -->
                <div
                    v-if="!templates?.length"
                    class="rounded-xl border border-dashed border-border bg-card p-10 text-center"
                >
                    <h2 class="text-lg font-semibold text-foreground">
                        Nenhum modelo de agente disponível
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Não há modelos disponíveis para iniciar a configuração. Entre em contato com o suporte para continuar.
                    </p>
                </div>

                <!-- Normal template picker -->
                <template v-else>
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Escolha seu agente especializado
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Comece com um modelo pronto para o seu tipo de atendimento. Você poderá ajustar a personalidade antes de concluir.
                        </p>
                    </div>

                    <!-- Template cards -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="tpl in templates"
                            :key="tpl.slug"
                            type="button"
                            @click="selectTemplate(tpl)"
                            :class="[
                                'rounded-lg border p-4 text-left transition-all duration-150',
                                agentForm.template_slug === tpl.slug && modeFor(tpl)
                                    ? `${modeFor(tpl)!.borderClass} ${modeFor(tpl)!.bgClass} ring-1 ${modeFor(tpl)!.ringClass}`
                                    : agentForm.template_slug === tpl.slug
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                    : 'border-border hover:border-primary/30',
                            ]"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="iconMap[tpl.icon]"
                                    class="mt-0.5 h-5 w-5 shrink-0"
                                    :class="[
                                        agentForm.template_slug === tpl.slug
                                            ? (tpl.mode === 'receptivo' ? 'text-teal-500' : tpl.mode === 'prospeccao' ? 'text-amber-500' : 'text-primary')
                                            : 'text-muted-foreground/40',
                                    ]"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-foreground">{{ tpl.name }}</span>
                                        <span class="text-xs text-muted-foreground">— {{ tpl.label }}</span>
                                        <span
                                            v-if="modeFor(tpl)"
                                            class="rounded-full px-1.5 py-0.5 text-[9px] font-bold tracking-widest"
                                            :class="modeFor(tpl)!.classes"
                                        >
                                            {{ modeFor(tpl)!.label }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs text-muted-foreground">{{ tpl.description }}</p>
                                    <p class="mt-1 text-xs font-medium italic text-foreground/60">{{ tpl.tagline }}</p>

                                    <!-- Use case chips -->
                                    <div v-if="tpl.use_cases?.length" class="mt-2 flex flex-wrap gap-1">
                                        <span
                                            v-for="uc in tpl.use_cases"
                                            :key="uc"
                                            class="rounded-md border border-border bg-muted/60 px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                        >
                                            {{ uc }}
                                        </span>
                                    </div>

                                    <!-- Preview first message -->
                                    <div class="mt-3 rounded-md bg-muted/60 px-3 py-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                            Primeira mensagem
                                        </p>
                                        <p class="mt-0.5 text-xs text-foreground">"{{ tpl.example_first_message }}"</p>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Validation error -->
                    <p v-if="agentForm.errors.template_slug" class="text-sm text-destructive">
                        Escolha um agente para continuar.
                    </p>

                    <!-- Action row -->
                    <div class="flex items-center justify-end pt-2">
                        <Button
                            type="button"
                            :disabled="agentForm.processing || !agentForm.template_slug"
                            @click="submitAgent"
                        >
                            {{ agentForm.processing ? 'Criando agente...' : 'Continuar com este agente' }}
                        </Button>
                    </div>
                </template>
            </div>

            <!-- ─── Step 2: WhatsApp Instance ──────────────────────────────── -->
            <div v-else-if="current_step === 'instance'" class="mt-6 space-y-6">

                <div>
                    <h2 class="text-lg font-semibold text-foreground">
                        Conecte um WhatsApp agora ou continue depois
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Seu agente só ficará ativo depois que uma instância WhatsApp for vinculada.
                    </p>
                </div>

                <!-- Instance selector -->
                <div v-if="instances?.length" class="space-y-3">
                    <Label>Instância WhatsApp</Label>

                    <div class="space-y-2">
                        <!-- No selection option -->
                        <button
                            type="button"
                            @click="selectedInstanceId = null"
                            :class="[
                                'w-full rounded-lg border px-4 py-3 text-left text-sm transition-colors',
                                selectedInstanceId === null
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                    : 'border-border hover:border-primary/30',
                            ]"
                        >
                            <span class="font-medium text-foreground">Sem instância (continuar inativo)</span>
                            <p class="mt-0.5 text-xs text-muted-foreground">O agente ficará salvo como inativo até a vinculação.</p>
                        </button>

                        <!-- Available instances -->
                        <button
                            v-for="inst in instances"
                            :key="inst.id"
                            type="button"
                            @click="selectedInstanceId = inst.id"
                            :class="[
                                'w-full rounded-lg border px-4 py-3 text-left text-sm transition-colors',
                                selectedInstanceId === inst.id
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                    : 'border-border hover:border-primary/30',
                            ]"
                        >
                            <span class="font-medium text-foreground">
                                {{ inst.display_name ?? inst.name }}
                            </span>
                            <span v-if="inst.phone_number" class="ml-2 text-xs text-muted-foreground">{{ inst.phone_number }}</span>
                        </button>
                    </div>

                    <!-- Error -->
                    <p v-if="instanceForm.errors.whatsapp_instance_id" class="text-sm text-destructive">
                        Não foi possível vincular esta instância. Escolha outra instância livre ou continue sem WhatsApp.
                    </p>
                </div>

                <!-- Empty instances state -->
                <div
                    v-else
                    class="rounded-lg border border-border bg-muted/30 px-5 py-4"
                >
                    <p class="text-sm font-medium text-foreground">Nenhuma instância WhatsApp disponível</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Você pode conectar um número agora ou avançar sem WhatsApp. O agente ficará salvo como inativo até a vinculação.
                    </p>
                </div>

                <!-- Action row -->
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Secondary: connect now (detour) -->
                    <Link
                        href="/whatsapp?return=/onboarding"
                        class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        Conectar WhatsApp agora
                    </Link>

                    <!-- Primary CTA -->
                    <Button
                        type="button"
                        :disabled="instanceForm.processing"
                        @click="submitInstance"
                    >
                        <span v-if="instanceForm.processing">Vinculando WhatsApp...</span>
                        <span v-else-if="selectedInstanceId !== null">Vincular e continuar</span>
                        <span v-else>Continuar sem WhatsApp</span>
                    </Button>
                </div>
            </div>

            <!-- ─── Step 3: Persona ────────────────────────────────────────── -->
            <div v-else-if="current_step === 'persona'" class="mt-6 space-y-6">

                <div>
                    <h2 class="text-lg font-semibold text-foreground">
                        Personalize como seu agente conversa
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ajuste a apresentação e o tom de voz. As configurações técnicas continuam gerenciadas pela plataforma.
                    </p>
                </div>

                <!-- Persona fields -->
                <form class="space-y-5" @submit.prevent="submitPersona">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="agent_name">Nome do agente</Label>
                            <Input
                                id="agent_name"
                                v-model="personaForm.agent_name"
                                type="text"
                                maxlength="50"
                                placeholder="Ex: Alicia, Sofia, Max"
                                :class="{ 'border-destructive': personaForm.errors.agent_name }"
                            />
                            <p class="text-xs text-muted-foreground">Como o agente se apresenta ao cliente</p>
                            <p v-if="personaForm.errors.agent_name" class="text-xs text-destructive">{{ personaForm.errors.agent_name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="company_name">Nome da empresa</Label>
                            <Input
                                id="company_name"
                                v-model="personaForm.company_name"
                                type="text"
                                maxlength="100"
                                placeholder="Ex: Amec, Banco Pan, BMG"
                                :class="{ 'border-destructive': personaForm.errors.company_name }"
                            />
                            <p class="text-xs text-muted-foreground">Nome que aparece na apresentação</p>
                            <p v-if="personaForm.errors.company_name" class="text-xs text-destructive">{{ personaForm.errors.company_name }}</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="agent_personality">Personalidade</Label>
                        <Input
                            id="agent_personality"
                            v-model="personaForm.agent_personality"
                            type="text"
                            maxlength="200"
                            placeholder="Ex: calorosa e empática"
                            :class="{ 'border-destructive': personaForm.errors.agent_personality }"
                        />
                        <p class="text-xs text-muted-foreground">Diretriz de tom e linguagem do agente</p>
                        <p v-if="personaForm.errors.agent_personality" class="text-xs text-destructive">{{ personaForm.errors.agent_personality }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="agent_greeting">Saudação inicial</Label>
                        <Input
                            id="agent_greeting"
                            v-model="personaForm.agent_greeting"
                            type="text"
                            maxlength="300"
                            placeholder="Ex: Diga olá, apresente-se e pergunte como pode ajudar"
                            :class="{ 'border-destructive': personaForm.errors.agent_greeting }"
                        />
                        <p class="text-xs text-muted-foreground">Instrução de como o agente inicia a conversa</p>
                        <p v-if="personaForm.errors.agent_greeting" class="text-xs text-destructive">{{ personaForm.errors.agent_greeting }}</p>
                    </div>

                    <!-- General error -->
                    <p
                        v-if="Object.keys(personaForm.errors).length"
                        class="text-sm text-destructive"
                    >
                        Revise os campos destacados para concluir a configuração.
                    </p>

                    <!-- Action row -->
                    <div class="flex items-center justify-end pt-2">
                        <Button type="submit" :disabled="personaForm.processing">
                            {{ personaForm.processing ? 'Concluindo configuração...' : 'Concluir configuração' }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- ─── Completion ─────────────────────────────────────────────── -->
            <div v-else-if="current_step === 'complete'" class="mt-6 space-y-6">

                <!-- Ready state -->
                <template v-if="is_ready">
                    <div class="space-y-3">
                        <Badge class="bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20 hover:bg-green-500/10">
                            WhatsApp conectado
                        </Badge>
                        <h2 class="text-lg font-semibold text-foreground">
                            Seu agente está pronto para atender
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            A configuração inicial foi concluída e o agente já pode operar pelo número vinculado.
                        </p>
                    </div>
                </template>

                <!-- Pending state -->
                <template v-else>
                    <div class="space-y-3">
                        <Badge class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 hover:bg-amber-500/10">
                            Aguardando conexão WhatsApp
                        </Badge>
                        <h2 class="text-lg font-semibold text-foreground">
                            Seu agente foi configurado
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            A configuração inicial foi concluída. Conecte um WhatsApp quando estiver pronto para ativar o agente.
                        </p>
                    </div>
                </template>

                <!-- Agent summary -->
                <div v-if="agent_name" class="rounded-lg border border-border bg-muted/30 px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agente configurado</p>
                    <p class="mt-1 text-sm font-semibold text-foreground">{{ agent_name }}</p>
                </div>

                <!-- Action row -->
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Pending: connect WhatsApp (no return to onboarding) -->
                    <Link
                        v-if="!is_ready"
                        href="/whatsapp"
                        class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        Conectar WhatsApp
                    </Link>
                    <div v-else />

                    <!-- Go to dashboard -->
                    <Link href="/dashboard">
                        <Button type="button">
                            Ir para dashboard
                        </Button>
                    </Link>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
