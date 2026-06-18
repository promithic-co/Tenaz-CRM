<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BarChart3, DollarSign, Cpu, Zap } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
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
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'AI Usage', href: '/laboratory/ai-usage' },
];

const formatNumber = (n: number) => n.toLocaleString('pt-BR');
const formatCurrency = (n: number) => `$${n.toFixed(4)}`;
const estimateBrl = (usd: number) => `R$ ${(usd * 5.5).toFixed(2)}`;
const formatMs = (n: number | null) => (n === null ? '-' : `${n.toLocaleString('pt-BR')} ms`);
const shortHash = (hash: string | null) => (hash ? hash.slice(0, 8) : '-');
const formatDate = (value: string | null) => value ? new Date(value).toLocaleString('pt-BR') : '-';

const maxCost = Math.max(...props.dailyUsage.map(d => d.cost_usd), 0.01);

function submitFilters() {
    const query = Object.fromEntries(
        Object.entries(filterForm).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );

    router.get('/laboratory/ai-usage', query, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="AI Usage" />

        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-white">
                <BarChart3 class="h-7 w-7" />
                AI Usage & Costs
            </h1>

            <!-- AI Runs Explorer -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">AI Runs</h2>
                    <form class="grid grid-cols-2 gap-2 lg:grid-cols-7" @submit.prevent="submitFilters">
                        <input v-model="filterForm.date_from" type="date" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <input v-model="filterForm.date_to" type="date" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <input v-model="filterForm.agent_id" type="number" placeholder="Agent ID" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <select v-model="filterForm.architecture_version" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Arquitetura</option>
                            <option value="legacy_prompt">legacy_prompt</option>
                            <option value="folder_skills">folder_skills</option>
                            <option value="hybrid">hybrid</option>
                        </select>
                        <input v-model="filterForm.model" type="text" placeholder="Modelo" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900" />
                        <select v-model="filterForm.status" class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Status</option>
                            <option value="success">success</option>
                            <option value="fallback">fallback</option>
                            <option value="timeout">timeout</option>
                            <option value="error">error</option>
                            <option value="human_handoff">human_handoff</option>
                        </select>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-white dark:text-gray-900">Filtrar</button>
                    </form>
                </div>

                <div v-if="runs.length === 0" class="py-8 text-center text-gray-400">No AI runs found</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <th class="pb-2">Time</th>
                                <th class="pb-2">Agent</th>
                                <th class="pb-2">Architecture</th>
                                <th class="pb-2">Prompt/Skill</th>
                                <th class="pb-2">Model</th>
                                <th class="pb-2 text-right">Cost</th>
                                <th class="pb-2 text-right">Latency</th>
                                <th class="pb-2 text-right">LLM</th>
                                <th class="pb-2 text-right">Tools</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2">Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="run in runs" :key="run.id" class="border-b border-gray-100 dark:border-gray-700/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ formatDate(run.started_at) }}</td>
                                <td class="py-2 font-medium text-gray-900 dark:text-white">{{ run.agent_name ?? `#${run.agent_id}` }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ run.architecture_version }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ shortHash(run.skill_hash ?? run.prompt_hash) }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ run.model ?? '-' }}</td>
                                <td class="py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(run.estimated_cost_usd) }}</td>
                                <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ formatMs(run.duration_ms) }}</td>
                                <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ run.llm_calls }}</td>
                                <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ run.tool_calls }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ run.status }}</td>
                                <td class="py-2 text-gray-600 dark:text-gray-300">{{ run.outcome ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <Zap class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Requests (30d)</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(totalMonth.requests) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <Cpu class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Tokens</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(totalMonth.prompt_tokens + totalMonth.completion_tokens) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                            <DollarSign class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cost (USD)</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalMonth.cost_usd) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                            <DollarSign class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cost (BRL est.)</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ estimateBrl(totalMonth.cost_usd) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Cost Bar Chart -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Daily Cost (last 30 days)</h2>
                <div v-if="dailyUsage.length === 0" class="py-12 text-center text-gray-400">
                    No usage data yet. Data is aggregated daily at 01:00.
                </div>
                <div v-else class="flex items-end gap-1" style="height: 200px">
                    <div
                        v-for="day in dailyUsage"
                        :key="day.date"
                        class="group relative flex-1 cursor-default"
                        style="min-width: 8px"
                    >
                        <div
                            class="w-full rounded-t bg-blue-500 transition-colors group-hover:bg-blue-400"
                            :style="{ height: `${Math.max((day.cost_usd / maxCost) * 100, 2)}%` }"
                        ></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block">
                            {{ day.date }}: {{ formatCurrency(day.cost_usd) }} ({{ day.requests }} req)
                        </div>
                    </div>
                </div>
            </div>

            <!-- By Model Breakdown -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Cost by Model/Agent</h2>
                <div v-if="byModel.length === 0" class="py-8 text-center text-gray-400">No data</div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="pb-2">Model/Agent</th>
                            <th class="pb-2 text-right">Requests</th>
                            <th class="pb-2 text-right">Prompt Tokens</th>
                            <th class="pb-2 text-right">Completion Tokens</th>
                            <th class="pb-2 text-right">Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in byModel"
                            :key="m.model"
                            class="border-b border-gray-100 dark:border-gray-700/50"
                        >
                            <td class="py-2 font-medium text-gray-900 dark:text-white">{{ m.model }}</td>
                            <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(m.requests) }}</td>
                            <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(m.prompt_tokens) }}</td>
                            <td class="py-2 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(m.completion_tokens) }}</td>
                            <td class="py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(m.cost_usd) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
