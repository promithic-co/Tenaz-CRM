<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Settings = {
    enabled: boolean;
    first_delay_minutes: number;
    max_count: number;
    followup_window_start: string;
    followup_window_end: string;
    min_interval_minutes: number;
    message_type: string;
    tone: string;
    persuasion_intensity: number;
    custom_instructions: string;
};

type Props = {
    settings: Settings;
    flash: string | null;
    agent?: { id: number; name: string } | null;
};
const props = defineProps<Props>();

const followupPath = computed(() =>
    props.agent ? `/agentes/${props.agent.id}/follow-up` : '/agente/follow-up',
);

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

const intervalPresets = [
    { value: 30, label: '30 min' },
    { value: 60, label: '1 hora' },
    { value: 120, label: '2 horas' },
    { value: 240, label: '4 horas' },
    { value: 480, label: '8 horas' },
    { value: 720, label: '12 horas' },
    { value: 1440, label: '24 horas' },
];

/**
 * Legacy rows may carry a day-derived interval (e.g. 4320 min) that no longer
 * maps to a preset. Snap the initial value to the nearest chip so the UI stays
 * honest without a data migration.
 */
function nearestPreset(minutes: number): number {
    return intervalPresets.reduce(
        (best, opt) =>
            Math.abs(opt.value - minutes) < Math.abs(best - minutes)
                ? opt.value
                : best,
        intervalPresets[0].value,
    );
}

const form = useForm({
    enabled: props.settings.enabled,
    first_delay_minutes: props.settings.first_delay_minutes,
    max_count: props.settings.max_count,
    followup_window_start: props.settings.followup_window_start,
    followup_window_end: props.settings.followup_window_end,
    min_interval_minutes: nearestPreset(props.settings.min_interval_minutes),
    message_type: props.settings.message_type,
    tone: props.settings.tone,
    persuasion_intensity: props.settings.persuasion_intensity,
    custom_instructions: props.settings.custom_instructions,
});

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
        <div class="max-w-2xl p-3 sm:p-4">
            <!-- Flash -->
            <div
                v-if="flash"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash }}
            </div>

            <form @submit.prevent="form.post(followupPath)" class="space-y-6">
                <!-- Enable follow-up -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">
                                Follow-up automático
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Quando ativo, o agente reengaja leads sem
                                resposta dentro das regras abaixo.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.enabled"
                            @click="form.enabled = !form.enabled"
                            :class="[
                                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:ring-2 focus:ring-ring focus:outline-none',
                                form.enabled ? 'bg-primary' : 'bg-muted',
                            ]"
                        >
                            <span
                                :class="[
                                    'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                                    form.enabled
                                        ? 'translate-x-5'
                                        : 'translate-x-0.5',
                                ]"
                            />
                        </button>
                    </div>
                    <p
                        v-if="form.errors.enabled"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.enabled }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Mensagem e Tom
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block text-sm text-muted-foreground"
                                >Tipo de mensagem</label
                            >
                            <select
                                v-model="form.message_type"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option
                                    v-for="opt in tiposMensagem"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.message_type"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.message_type }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm text-muted-foreground"
                                >Tom de voz</label
                            >
                            <select
                                v-model="form.tone"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option
                                    v-for="opt in tons"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.tone"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.tone }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div
                            class="mb-2 flex items-center justify-between gap-3"
                        >
                            <label class="text-sm text-muted-foreground"
                                >Intensidade de persuasao</label
                            >
                            <span
                                class="rounded-md bg-muted px-2 py-1 text-xs font-medium text-foreground"
                                >{{ form.persuasion_intensity }}/5</span
                            >
                        </div>
                        <input
                            v-model.number="form.persuasion_intensity"
                            type="range"
                            min="1"
                            max="5"
                            step="1"
                            class="w-full accent-primary"
                        />
                        <div
                            class="mt-1 flex justify-between text-[11px] text-muted-foreground"
                        >
                            <span>Leve</span>
                            <span>Direta</span>
                        </div>
                        <p
                            v-if="form.errors.persuasion_intensity"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.persuasion_intensity }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <label class="mb-1 block text-sm text-muted-foreground"
                            >Instrucoes adicionais</label
                        >
                        <textarea
                            v-model="form.custom_instructions"
                            rows="3"
                            maxlength="1000"
                            class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            placeholder="Ex.: priorizar margem disponivel, evitar urgencia alta, oferecer atendimento humano se houver duvida."
                        />
                        <p
                            v-if="form.errors.custom_instructions"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ form.errors.custom_instructions }}
                        </p>
                    </div>
                </div>

                <!-- First follow-up delay -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Primeiro Follow-up
                    </h2>
                    <div class="flex items-center gap-3">
                        <label
                            class="w-56 shrink-0 text-sm text-muted-foreground"
                            >Enviar após a última mensagem do cliente</label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="form.first_delay_minutes"
                                type="number"
                                min="1"
                                max="1440"
                                class="w-20 rounded-md border border-input bg-background px-3 py-2 text-center text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            />
                            <span class="text-sm text-muted-foreground"
                                >minutos sem resposta</span
                            >
                        </div>
                    </div>
                    <p
                        v-if="form.errors.first_delay_minutes"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.first_delay_minutes }}
                    </p>
                </div>

                <!-- Send window -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-1 text-sm font-semibold text-foreground">
                        Janela de Envio
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Follow-ups só serão enviados dentro deste horário (fuso
                        de São Paulo)
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-muted-foreground"
                                >Início</label
                            >
                            <input
                                v-model="form.followup_window_start"
                                type="time"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-muted-foreground"
                                >Fim</label
                            >
                            <input
                                v-model="form.followup_window_end"
                                type="time"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            />
                        </div>
                    </div>
                    <p
                        v-if="form.errors.followup_window_start"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.followup_window_start }}
                    </p>
                    <p
                        v-if="form.errors.followup_window_end"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.followup_window_end }}
                    </p>
                </div>

                <!-- Interval between follow-ups -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-1 text-sm font-semibold text-foreground">
                        Intervalo entre Follow-ups
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Tempo mínimo de espera entre cada mensagem de follow-up.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in intervalPresets"
                            :key="opt.value"
                            type="button"
                            @click="form.min_interval_minutes = opt.value"
                            :class="[
                                'rounded-lg border-2 px-4 py-3 text-sm font-semibold transition-colors',
                                form.min_interval_minutes === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-foreground hover:border-primary/50',
                            ]"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.min_interval_minutes"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.min_interval_minutes }}
                    </p>
                </div>

                <!-- Max follow-up count -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-1 text-sm font-semibold text-foreground">
                        Quantidade de Follow-ups
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        A última mensagem é sempre um encerramento respeitoso.
                    </p>
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
                    <p
                        v-if="form.errors.max_count"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.max_count }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Salvando...'
                                : 'Salvar configurações'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
