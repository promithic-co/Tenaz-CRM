<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { FlaskConical, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import type { BreadcrumbItem } from '@/types';

type Dataset = { id: number; name: string; total_entries: number };
type RecentRun = {
    id: number;
    label: string;
    cpf_dataset: { id: number; name: string } | null;
    total_cycles: number;
    completed_cycles: number;
    status: string;
    results_summary: { average_fidelity_score?: number } | null;
    created_at: string;
};

type Props = { datasets: Dataset[]; recentRuns: RecentRun[] };
defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'Stress Test', href: '/laboratory/stress-test' },
];

type ModelOption = { label: string; value: string; hint?: string };
type VendorGroup = { vendor: string; models: ModelOption[] };

const MODEL_CATALOG: VendorGroup[] = [
    { vendor: 'OpenAI', models: [
        { label: 'GPT-5.4 Pro', value: 'gpt-5.4-pro', hint: 'Mais capaz' },
        { label: 'GPT-5.4', value: 'gpt-5.4' },
        { label: 'o4-mini', value: 'o4-mini', hint: 'Raciocínio, econômico' },
        { label: 'GPT-4o', value: 'gpt-4o' },
        { label: 'GPT-4o-mini', value: 'gpt-4o-mini', hint: 'Barato' },
    ]},
    { vendor: 'Anthropic', models: [
        { label: 'Claude Sonnet 4.6', value: 'anthropic/claude-sonnet-4-6', hint: 'Agentic premium' },
        { label: 'Claude Haiku 4.5', value: 'anthropic/claude-haiku-4-5', hint: 'Rápido e barato' },
    ]},
    { vendor: 'Google', models: [
        { label: 'Gemini 2.5 Flash', value: 'google/gemini-2.5-flash', hint: 'Muito rápido' },
        { label: 'Gemini 3 Flash', value: 'google/gemini-3-flash', hint: 'Alta capacidade' },
    ]},
    { vendor: 'DeepSeek', models: [
        { label: 'DeepSeek V3.2', value: 'deepseek/deepseek-v3.2', hint: 'Custo-benefício' },
    ]},
    { vendor: 'Kimi', models: [
        { label: 'Kimi K2', value: 'moonshotai/kimi-k2', hint: 'Raciocínio avançado' },
        { label: 'Kimi K2.5', value: 'moonshotai/kimi-k2.5', hint: 'Mais recente' },
    ]},
];

const label = ref('');
const objective = ref('Testar fidelidade dos valores de crédito reportados pelo agente.');
const cpfDatasetId = ref<number | null>(null);
const cycles = ref(5);
const roundsPerCycle = ref(5);
const testerModel = ref('anthropic/claude-sonnet-4-6');
const agentModel = ref('gpt-4o-mini');
const starting = ref(false);

function csrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function startRun() {
    if (!label.value.trim() || !objective.value.trim()) return;
    starting.value = true;
    try {
        const res = await fetch('/laboratory/stress-tests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({
                label: label.value.trim(),
                objective: objective.value.trim(),
                cpf_dataset_id: cpfDatasetId.value || null,
                config: {
                    cycles: cycles.value,
                    rounds_per_cycle: roundsPerCycle.value,
                    tester_model: testerModel.value,
                    agent_model: agentModel.value,
                },
            }),
        });
        const data = await res.json();
        if (res.ok && data.data?.id) {
            router.visit(`/laboratory/stress-test/${data.data.id}`);
        }
    } finally {
        starting.value = false;
    }
}

function statusClass(status: string): string {
    const map: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        running: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled: 'bg-muted text-muted-foreground',
    };
    return map[status] ?? 'bg-muted text-muted-foreground';
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Stress Test - Laboratory" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center gap-2">
                <FlaskConical class="h-5 w-5 text-muted-foreground" />
                <h1 class="text-lg font-semibold text-foreground">Bateria de Stress Test</h1>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Config -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">Nova bateria</h2>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Label</label>
                            <input v-model="label" type="text" placeholder="Ex: Bateria Março 2026" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Objetivo do teste</label>
                            <textarea v-model="objective" rows="3" placeholder="Testar fidelidade dos valores de crédito..." class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Dataset de CPFs (opcional)</label>
                            <select v-model="cpfDatasetId" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="null">Nenhum</option>
                                <option v-for="d in datasets" :key="d.id" :value="d.id">{{ d.name }} ({{ d.total_entries }} CPFs)</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Modelo Red Team</label>
                                <select v-model="testerModel" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <optgroup v-for="g in MODEL_CATALOG" :key="g.vendor" :label="g.vendor">
                                        <option v-for="m in g.models" :key="m.value" :value="m.value">{{ m.label }}{{ m.hint ? ` — ${m.hint}` : '' }}</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Modelo do Agente</label>
                                <select v-model="agentModel" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                    <optgroup v-for="g in MODEL_CATALOG" :key="g.vendor" :label="g.vendor">
                                        <option v-for="m in g.models" :key="m.value" :value="m.value">{{ m.label }}{{ m.hint ? ` — ${m.hint}` : '' }}</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Ciclos (1–50)</label>
                                <input v-model.number="cycles" type="number" min="1" max="50" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Rodadas por ciclo (1–15)</label>
                                <input v-model.number="roundsPerCycle" type="number" min="1" max="15" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <button
                            type="button"
                            :disabled="starting"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            @click="startRun"
                        >
                            <Loader2 v-if="starting" class="h-4 w-4 animate-spin" />
                            Iniciar Bateria
                        </button>
                    </div>
                </div>

                <!-- Recent runs -->
                <div class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <h2 class="text-sm font-semibold text-foreground">Baterias recentes</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sidebar-border/70 bg-muted/50 dark:border-sidebar-border">
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Label</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Dataset</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Ciclos</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Fidelidade</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted-foreground">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                <tr
                                    v-for="r in recentRuns"
                                    :key="r.id"
                                    class="cursor-pointer text-foreground hover:bg-muted/50"
                                    @click="router.visit(`/laboratory/stress-test/${r.id}`)"
                                >
                                    <td class="px-4 py-2">{{ r.label }}</td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ r.cpf_dataset?.name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="r.status === 'running'" class="font-medium">{{ r.completed_cycles }}/{{ r.total_cycles }}</span>
                                        <span v-else>{{ r.total_cycles }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span :class="[statusClass(r.status), 'rounded-full px-2 py-0.5 text-xs font-medium', r.status === 'running' && 'animate-pulse']">
                                            {{ r.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">
                                        <template v-if="r.results_summary?.average_fidelity_score != null">
                                            {{ r.results_summary.average_fidelity_score.toFixed(1) }}%
                                        </template>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-2 text-muted-foreground">{{ formatDate(r.created_at) }}</td>
                                </tr>
                                <tr v-if="!recentRuns.length">
                                    <td colspan="6" class="px-4 py-6 text-center text-muted-foreground">Nenhuma bateria ainda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
