<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { laboratory as laboratoryIndex } from '@/routes';
import laboratory from '@/routes/laboratory';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Cpu,
    DollarSign,
    Filter,
    Search,
    Zap,
} from 'lucide-vue-next';
import { reactive } from 'vue';

type DailyUsage = {
    date: string;
    requests: number;
    prompt_tokens: number;
    completion_tokens: number;
    cost_usd: number;
};

type ModelUsage = {
    model: string;
    requests: number;
    prompt_tokens: number;
    completion_tokens: number;
    cost_usd: number;
};

type TotalMonth = {
    requests: number;
    prompt_tokens: number;
    completion_tokens: number;
    cost_usd: number;
};

type AiRun = {
    id: number;
    started_at: string | null;
    agent_id: number | null;
    agent_name: string | null;
    architecture_version: string;
    prompt_hash: string | null;
    skill_hash: string | null;
    model: string | null;
    estimated_cost_usd: number;
    duration_ms: number | null;
    llm_calls: number;
    tool_calls: number;
    status: string;
    outcome: string | null;
};

type Filters = {
    date_from: string;
    date_to: string;
    agent_id: string | number;
    architecture_version: string;
    model: string;
    status: string;
};

type Props = {
    dailyUsage: DailyUsage[];
    byModel: ModelUsage[];
    totalMonth: TotalMonth;
    runs: AiRun[];
    filters: Filters;
};

const props = defineProps<Props>();
const filterForm = reactive({ ...props.filters });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: laboratoryIndex() },
    { title: 'AI Usage', href: laboratory.aiUsage() },
];

const maxCost = Math.max(...props.dailyUsage.map((day) => day.cost_usd), 0.01);

const architectureOptions = [
    { label: 'legacy_prompt', value: 'legacy_prompt' },
    { label: 'folder_skills', value: 'folder_skills' },
    { label: 'hybrid', value: 'hybrid' },
];

const statusOptions = [
    { label: 'success', value: 'success' },
    { label: 'fallback', value: 'fallback' },
    { label: 'timeout', value: 'timeout' },
    { label: 'error', value: 'error' },
    { label: 'human_handoff', value: 'human_handoff' },
];

function submitFilters(): void {
    const query = Object.fromEntries(
        Object.entries(filterForm).filter(
            ([, value]) =>
                value !== '' && value !== null && value !== undefined,
        ),
    );

    router.get(laboratory.aiUsage.url(), query, {
        preserveState: true,
        replace: true,
    });
}

function formatNumber(value: number): string {
    return value.toLocaleString('pt-BR');
}

function formatCurrency(value: number): string {
    return `$${value.toFixed(4)}`;
}

function formatMs(value: number | null): string {
    return value === null ? '-' : `${value.toLocaleString('pt-BR')} ms`;
}

function shortHash(hash: string | null): string {
    return hash ? hash.slice(0, 8) : '-';
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('pt-BR') : '-';
}

function statusClass(status: string): string {
    const map: Record<string, string> = {
        success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        fallback: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        timeout: 'bg-orange-500/10 text-orange-700 dark:text-orange-400',
        error: 'bg-red-500/10 text-red-700 dark:text-red-400',
        human_handoff: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="AI Usage - Laboratory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 p-4 lg:p-6">
            <header
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-sidebar-border bg-card text-muted-foreground"
                    >
                        <BarChart3 class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">
                            AI Usage
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Custos, tokens e runs recentes usados para comparar
                            modelo, prompt e arquitetura.
                        </p>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Requests 30d
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground"
                            >
                                {{ formatNumber(totalMonth.requests) }}
                            </p>
                        </div>
                        <Zap class="size-4 text-muted-foreground" />
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Total tokens
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground"
                            >
                                {{
                                    formatNumber(
                                        totalMonth.prompt_tokens +
                                            totalMonth.completion_tokens,
                                    )
                                }}
                            </p>
                        </div>
                        <Cpu class="size-4 text-muted-foreground" />
                    </div>
                </div>
                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Custo USD
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground"
                            >
                                {{ formatCurrency(totalMonth.cost_usd) }}
                            </p>
                        </div>
                        <DollarSign class="size-4 text-muted-foreground" />
                    </div>
                </div>
            </section>

            <section
                class="rounded-lg border border-sidebar-border bg-card shadow-sm"
            >
                <div class="border-b border-sidebar-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Filter class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            AI runs
                        </h2>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Até 100 runs recentes no intervalo filtrado.
                    </p>
                </div>

                <form
                    class="grid gap-3 border-b border-sidebar-border p-4 md:grid-cols-2 lg:grid-cols-7"
                    @submit.prevent="submitFilters"
                >
                    <input
                        v-model="filterForm.date_from"
                        type="date"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    />
                    <input
                        v-model="filterForm.date_to"
                        type="date"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    />
                    <input
                        v-model="filterForm.agent_id"
                        type="number"
                        placeholder="Agent ID"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    />
                    <select
                        v-model="filterForm.architecture_version"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    >
                        <option value="">Arquitetura</option>
                        <option
                            v-for="option in architectureOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <input
                        v-model="filterForm.model"
                        type="text"
                        placeholder="Modelo"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    />
                    <select
                        v-model="filterForm.status"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                    >
                        <option value="">Status</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        <Search class="size-4" />
                        Filtrar
                    </button>
                </form>

                <div
                    v-if="runs.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Nenhum AI run encontrado para os filtros atuais.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-sm">
                        <thead
                            class="border-b border-sidebar-border bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">
                                    Horário
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Agente
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Arquitetura
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Prompt/Skill
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Modelo
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Custo
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Latência
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    LLM
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Tools
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Outcome
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr
                                v-for="run in runs"
                                :key="run.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDate(run.started_at) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ run.agent_name ?? `#${run.agent_id}` }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ run.architecture_version }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        shortHash(
                                            run.skill_hash ?? run.prompt_hash,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ run.model ?? '-' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold text-foreground"
                                >
                                    {{ formatCurrency(run.estimated_cost_usd) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ formatMs(run.duration_ms) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ run.llm_calls }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ run.tool_calls }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-1 text-xs font-medium',
                                            statusClass(run.status),
                                        ]"
                                    >
                                        {{ run.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ run.outcome ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1fr_1.15fr]">
                <div
                    class="rounded-lg border border-sidebar-border bg-card p-4 shadow-sm"
                >
                    <div class="mb-4">
                        <h2 class="text-sm font-semibold text-foreground">
                            Custo diário
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Últimos 30 dias agregados.
                        </p>
                    </div>
                    <div
                        v-if="dailyUsage.length === 0"
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        Ainda não há dados agregados.
                    </div>
                    <div v-else class="flex h-56 items-end gap-1">
                        <div
                            v-for="day in dailyUsage"
                            :key="day.date"
                            class="group relative flex h-full flex-1 flex-col justify-end"
                        >
                            <div
                                class="min-h-1 w-full rounded-t bg-blue-500/70 transition-colors group-hover:bg-blue-500"
                                :style="{
                                    height: `${Math.max((day.cost_usd / maxCost) * 100, 2)}%`,
                                }"
                            />
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded-md bg-popover px-2 py-1 text-xs text-popover-foreground shadow-md ring-1 ring-border group-hover:block"
                            >
                                {{ day.date }}:
                                {{ formatCurrency(day.cost_usd) }} ({{
                                    day.requests
                                }}
                                req)
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-lg border border-sidebar-border bg-card shadow-sm"
                >
                    <div class="border-b border-sidebar-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-foreground">
                            Custo por modelo/agente
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Baseado na agregação diária disponível.
                        </p>
                    </div>
                    <div
                        v-if="byModel.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Sem dados por modelo.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead
                                class="border-b border-sidebar-border bg-muted/40 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">
                                        Modelo/Agente
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Requests
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Prompt tokens
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Completion tokens
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Custo USD
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border">
                                <tr
                                    v-for="model in byModel"
                                    :key="model.model"
                                    class="hover:bg-muted/40"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-foreground"
                                    >
                                        {{ model.model }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{ formatNumber(model.requests) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{ formatNumber(model.prompt_tokens) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatNumber(
                                                model.completion_tokens,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold text-foreground"
                                    >
                                        {{ formatCurrency(model.cost_usd) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
