<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Settings = {
    first_delay_minutes: number;
    daily_time: string;
    max_count: number;
    approach: string;
    followup_window_start: string;
    followup_window_end: string;
    followup_interval_days: number;
    message_type: string;
    tone: string;
    persuasion_intensity: number;
    custom_instructions: string;
};

type Props = { settings: Settings; flash: string | null; agent?: { id: number; name: string } | null };
const props = defineProps<Props>();

const followupPath = computed(() => props.agent ? `/agentes/${props.agent.id}/follow-up` : '/agente/follow-up');

const breadcrumbs: BreadcrumbItem[] = props.agent
    ? [
        { title: 'Agentes', href: '/agentes' },
        { title: props.agent.name, href: followupPath.value },
        { title: 'Configurações do Follow-Up', href: followupPath.value },
    ]
    : [
        { title: 'Agente', href: '/agente' },
        { title: 'Configurações do Follow-Up', href: '/agente/follow-up' },
    ];

const form = useForm({
    first_delay_minutes:    props.settings.first_delay_minutes,
    daily_time:             props.settings.daily_time,
    max_count:              props.settings.max_count,
    approach:               props.settings.approach,
    followup_window_start:  props.settings.followup_window_start,
    followup_window_end:    props.settings.followup_window_end,
    followup_interval_days: props.settings.followup_interval_days,
    message_type:           props.settings.message_type,
    tone:                   props.settings.tone,
    persuasion_intensity:   props.settings.persuasion_intensity,
    custom_instructions:    props.settings.custom_instructions,
});

const abordagens = [
    { value: 'amigavel',   label: 'Amigável',   desc: 'Tom muito caloroso e empático. Sem pressão, foca no cuidado com o cliente.' },
    { value: 'natural',    label: 'Natural',    desc: 'Tom conversacional e equilibrado. Padrão recomendado para a maioria dos leads.' },
    { value: 'persuasivo', label: 'Persuasivo', desc: 'Destaca a oportunidade e cria urgência leve. Para leads com alto potencial.' },
];
const tiposMensagem = [
    { value: 'contextual', label: 'Contextual (padrão)' },
    { value: 'reengajamento', label: 'Reengajamento' },
    { value: 'urgencia', label: 'Urgencia' },
    { value: 'duvida', label: 'Tirar duvida' },
    { value: 'encerramento', label: 'Encerramento' },
    { value: 'proposta', label: 'Proposta' },
];

const tons = [
    { value: 'consultivo', label: 'Consultivo' },
    { value: 'acolhedor', label: 'Acolhedor' },
    { value: 'direto', label: 'Direto' },
    { value: 'descontraido', label: 'Descontraido' },
    { value: 'premium', label: 'Premium' },
];
</script>

<template>
    <Head title="Configurações do Follow-Up" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl p-4">
            <!-- Flash -->
            <div v-if="flash" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400">
                {{ flash }}
            </div>

            <form @submit.prevent="form.post(followupPath)" class="space-y-6">
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Mensagem e Tom</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-muted-foreground">Tipo de mensagem</label>
                            <select
                                v-model="form.message_type"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option v-for="opt in tiposMensagem" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <p v-if="form.errors.message_type" class="mt-1 text-xs text-red-500">{{ form.errors.message_type }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-muted-foreground">Tom de voz</label>
                            <select
                                v-model="form.tone"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option v-for="opt in tons" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <p v-if="form.errors.tone" class="mt-1 text-xs text-red-500">{{ form.errors.tone }}</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="text-sm text-muted-foreground">Intensidade de persuasao</label>
                            <span class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-foreground">{{ form.persuasion_intensity }}/5</span>
                        </div>
                        <input
                            v-model.number="form.persuasion_intensity"
                            type="range"
                            min="1"
                            max="5"
                            step="1"
                            class="w-full accent-primary"
                        />
                        <div class="mt-1 flex justify-between text-[11px] text-muted-foreground">
                            <span>Leve</span>
                            <span>Direta</span>
                        </div>
                        <p v-if="form.errors.persuasion_intensity" class="mt-1 text-xs text-red-500">{{ form.errors.persuasion_intensity }}</p>
                    </div>

                    <div class="mt-5">
                        <label class="mb-1 block text-sm text-muted-foreground">Instrucoes adicionais</label>
                        <textarea
                            v-model="form.custom_instructions"
                            rows="3"
                            maxlength="1000"
                            class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            placeholder="Ex.: priorizar margem disponivel, evitar urgencia alta, oferecer atendimento humano se houver duvida."
                        />
                        <p v-if="form.errors.custom_instructions" class="mt-1 text-xs text-red-500">{{ form.errors.custom_instructions }}</p>
                    </div>
                </div>

                <!-- First follow-up delay -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Primeiro Follow-up</h2>
                    <div class="flex items-center gap-3">
                        <label class="w-48 shrink-0 text-sm text-muted-foreground">Enviar após qualificação</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="form.first_delay_minutes"
                                type="number" min="1" max="1440"
                                class="w-20 rounded-md border border-input bg-background px-3 py-2 text-center text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <span class="text-sm text-muted-foreground">minutos sem resposta</span>
                        </div>
                    </div>
                    <p v-if="form.errors.first_delay_minutes" class="mt-1 text-xs text-red-500">{{ form.errors.first_delay_minutes }}</p>
                </div>

                <!-- Daily time -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Follow-ups do Dia Seguinte</h2>
                    <div class="flex items-center gap-3">
                        <label class="w-48 shrink-0 text-sm text-muted-foreground">Horário de envio</label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.daily_time"
                                type="time"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <span class="text-sm text-muted-foreground">fuso horário São Paulo</span>
                        </div>
                    </div>
                    <p v-if="form.errors.daily_time" class="mt-1 text-xs text-red-500">{{ form.errors.daily_time }}</p>
                </div>

                <!-- Send window -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-1 text-sm font-semibold text-foreground">Janela de Envio</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Follow-ups só serão enviados dentro deste horário (fuso de São Paulo)</p>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-muted-foreground">Início</label>
                            <input
                                v-model="form.followup_window_start"
                                type="time"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-muted-foreground">Fim</label>
                            <input
                                v-model="form.followup_window_end"
                                type="time"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.followup_window_start" class="mt-1 text-xs text-red-500">{{ form.errors.followup_window_start }}</p>
                    <p v-if="form.errors.followup_window_end" class="mt-1 text-xs text-red-500">{{ form.errors.followup_window_end }}</p>
                </div>

                <!-- Interval between follow-ups -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-1 text-sm font-semibold text-foreground">Intervalo entre Follow-ups</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Dias de espera entre cada mensagem de follow-up</p>
                    <div class="flex gap-2">
                        <button
                            v-for="opt in [{ value: 1, label: '1 dia' }, { value: 2, label: '2 dias' }, { value: 3, label: '3 dias' }, { value: 5, label: '5 dias' }, { value: 7, label: '7 dias' }]"
                            :key="opt.value"
                            type="button"
                            @click="form.followup_interval_days = opt.value"
                            :class="[
                                'rounded-lg border-2 px-4 py-3 text-sm font-semibold transition-colors',
                                form.followup_interval_days === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-foreground hover:border-primary/50',
                            ]"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.followup_interval_days" class="mt-1 text-xs text-red-500">{{ form.errors.followup_interval_days }}</p>
                </div>

                <!-- Max follow-up count -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-1 text-sm font-semibold text-foreground">Quantidade de Follow-ups</h2>
                    <p class="mb-4 text-xs text-muted-foreground">A última mensagem é sempre um encerramento respeitoso.</p>
                    <div class="flex gap-2">
                        <button
                            v-for="n in 5"
                            :key="n"
                            type="button"
                            @click="form.max_count = n"
                            :class="[
                                'h-12 w-12 rounded-lg border-2 text-sm font-semibold transition-colors',
                                form.max_count === n
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-foreground hover:border-primary/50',
                            ]"
                        >
                            {{ n }}
                        </button>
                    </div>
                    <p v-if="form.errors.max_count" class="mt-1 text-xs text-red-500">{{ form.errors.max_count }}</p>
                </div>

                <!-- Approach method -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Método de Abordagem</h2>
                    <div class="space-y-3">
                        <label
                            v-for="opt in abordagens"
                            :key="opt.value"
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 transition-colors',
                                form.approach === opt.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-border/80',
                            ]"
                        >
                            <input
                                type="radio"
                                v-model="form.approach"
                                :value="opt.value"
                                class="mt-0.5 accent-primary"
                            />
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ opt.label }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ opt.desc }}</p>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.approach" class="mt-1 text-xs text-red-500">{{ form.errors.approach }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar configurações' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
