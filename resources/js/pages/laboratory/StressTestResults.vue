<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FlaskConical,
    Loader2,
    ChevronDown,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type GroundTruthProduct = {
    name: string;
    valor_total: number | null;
    valor_parcela: number | null;
    note: string | null;
};

type Cycle = {
    id: number;
    cycle_number: number;
    cpf_used: string | null;
    scenario: string | null;
    status: string;
    fidelity_score: number | null;
    hallucinations: {
        field: string;
        expected: string;
        actual: string;
        severity: string;
    }[];
    token_metrics: unknown[];
    evaluation_report: string | null;
    completed_at: string | null;
    formatted_payload_for_agent: string | null;
    ground_truth_summary: {
        products: GroundTruthProduct[];
        refin_vs_portab_note: string;
    } | null;
};

type Run = {
    id: number;
    label: string;
    objective: string;
    cpf_dataset: { id: number; name: string } | null;
    config: { cycles?: number; rounds_per_cycle?: number };
    status: string;
    total_cycles: number;
    completed_cycles: number;
    results_summary: {
        average_fidelity_score?: number;
        total_hallucinations?: number;
        total_tokens_consumed?: number;
        pass_rate_percent?: number;
        cycles_passed?: number;
    } | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    cycles: Cycle[];
    cycles_truncated?: boolean;
};

type Props = { run: Run };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'Stress Test', href: '/laboratory/stress-test' },
    { title: props.run.label, href: '#' },
];

const expandedCycleId = ref<number | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const summary = computed(() => props.run.results_summary ?? {});
const avgFidelity = computed(() => summary.value.average_fidelity_score ?? 0);
const fidelityColor = computed(() =>
    avgFidelity.value >= 90
        ? 'text-green-600 dark:text-green-400'
        : avgFidelity.value >= 70
          ? 'text-yellow-600 dark:text-yellow-400'
          : 'text-red-600 dark:text-red-400',
);

function statusClass(status: string): string {
    const map: Record<string, string> = {
        pending:
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        running:
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        completed:
            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled: 'bg-muted text-muted-foreground',
    };
    return map[status] ?? 'bg-muted text-muted-foreground';
}

function fidelityBadgeClass(score: number | null): string {
    if (score == null) return 'bg-muted text-muted-foreground';
    if (score >= 90)
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (score >= 70)
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
}

function severityDot(severity: string): string {
    const map: Record<string, string> = {
        critical: 'bg-red-500',
        high: 'bg-orange-500',
        medium: 'bg-yellow-500',
        low: 'bg-muted-foreground',
    };
    return map[severity] ?? 'bg-muted-foreground';
}

function csrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatBrl(value: number | null): string {
    if (value == null) return '—';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

async function cancelRun() {
    const res = await fetch(`/laboratory/stress-tests/${props.run.id}/cancel`, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json' },
    });
    if (res.ok) router.reload();
}

onMounted(() => {
    if (props.run.status === 'running') {
        pollInterval = setInterval(
            () => router.reload({ only: ['run'] }),
            5000,
        );
    }
});
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head :title="`${run.label} - Stress Test`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-3 sm:p-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2">
                    <FlaskConical class="h-5 w-5 text-muted-foreground" />
                    <h1 class="text-lg font-semibold text-foreground">
                        {{ run.label }}
                    </h1>
                    <span
                        :class="[
                            statusClass(run.status),
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            run.status === 'running' && 'animate-pulse',
                        ]"
                    >
                        {{ run.status }}
                    </span>
                </div>
                <button
                    v-if="run.status === 'pending' || run.status === 'running'"
                    type="button"
                    class="rounded-md border border-input bg-background px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted"
                    @click="cancelRun"
                >
                    Cancelar
                </button>
            </div>
            <p v-if="run.objective" class="text-sm text-muted-foreground">
                {{ run.objective }}
            </p>

            <!-- Stats -->
            <div
                class="grid grid-cols-1 gap-3 min-[400px]:grid-cols-2 lg:grid-cols-5"
            >
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Fidelidade Média</span
                    >
                    <p class="mt-1 text-2xl font-bold" :class="fidelityColor">
                        {{ avgFidelity.toFixed(1) }}%
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Ciclos</span
                    >
                    <p class="mt-1 text-2xl font-bold text-foreground">
                        {{ run.completed_cycles }}/{{ run.total_cycles }}
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Alucinações</span
                    >
                    <p
                        class="mt-1 text-2xl font-bold"
                        :class="
                            (summary.total_hallucinations ?? 0) > 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-foreground'
                        "
                    >
                        {{ summary.total_hallucinations ?? 0 }}
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Tokens</span
                    >
                    <p class="mt-1 text-2xl font-bold text-foreground">
                        {{
                            (summary.total_tokens_consumed ?? 0).toLocaleString(
                                'pt-BR',
                            )
                        }}
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Taxa Aprovação</span
                    >
                    <p class="mt-1 text-2xl font-bold text-foreground">
                        {{ (summary.pass_rate_percent ?? 0).toFixed(0) }}%
                    </p>
                </div>
            </div>

            <!-- Cycles table -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Ciclos
                    </h2>
                    <span
                        v-if="run.cycles_truncated"
                        class="text-xs text-muted-foreground"
                    >
                        Mostrando os {{ run.cycles.length }} ciclos mais
                        recentes de {{ run.total_cycles }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/50 dark:border-sidebar-border"
                            >
                                <th class="w-8 px-4 py-2"></th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-muted-foreground"
                                >
                                    #
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-muted-foreground"
                                >
                                    CPF
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-muted-foreground"
                                >
                                    Fidelidade
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-muted-foreground"
                                >
                                    Alucinações
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <template v-for="c in run.cycles" :key="c.id">
                                <tr class="text-foreground">
                                    <td class="px-4 py-2">
                                        <button
                                            type="button"
                                            class="p-0.5 text-muted-foreground hover:text-foreground"
                                            @click="
                                                expandedCycleId =
                                                    expandedCycleId === c.id
                                                        ? null
                                                        : c.id
                                            "
                                        >
                                            <ChevronRight
                                                v-if="expandedCycleId !== c.id"
                                                class="h-4 w-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ c.cycle_number }}
                                    </td>
                                    <td
                                        class="px-4 py-2 font-mono text-muted-foreground"
                                    >
                                        {{ c.cpf_used ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            v-if="c.fidelity_score != null"
                                            :class="
                                                fidelityBadgeClass(
                                                    c.fidelity_score,
                                                )
                                            "
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ c.fidelity_score.toFixed(1) }}%
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            v-if="c.hallucinations?.length"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                v-for="(
                                                    h, i
                                                ) in c.hallucinations.slice(
                                                    0,
                                                    3,
                                                )"
                                                :key="i"
                                                class="inline-block h-2 w-2 rounded-full"
                                                :class="severityDot(h.severity)"
                                            ></span>
                                            {{ c.hallucinations.length }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >0</span
                                        >
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            :class="statusClass(c.status)"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            >{{ c.status }}</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="expandedCycleId === c.id"
                                    class="bg-muted/30"
                                >
                                    <td colspan="6" class="px-4 py-4">
                                        <div class="space-y-3 text-sm">
                                            <div v-if="c.scenario">
                                                <p
                                                    class="mb-1 font-medium text-muted-foreground"
                                                >
                                                    Cenário
                                                </p>
                                                <p
                                                    class="whitespace-pre-wrap text-foreground"
                                                >
                                                    {{ c.scenario }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="c.ground_truth_summary"
                                                class="overflow-x-auto rounded-md border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                                            >
                                                <p
                                                    class="mb-2 font-medium text-muted-foreground"
                                                >
                                                    Ground truth (valorTotal vs
                                                    valorParcela por produto)
                                                </p>
                                                <table
                                                    class="w-full min-w-[36rem] text-xs"
                                                >
                                                    <thead>
                                                        <tr
                                                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                                                        >
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Produto
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Valor total
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Valor parcela
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Nota
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                                                    >
                                                        <tr
                                                            v-for="(p, i) in c
                                                                .ground_truth_summary
                                                                .products"
                                                            :key="i"
                                                        >
                                                            <td
                                                                class="py-1 font-medium"
                                                            >
                                                                {{ p.name }}
                                                            </td>
                                                            <td class="py-1">
                                                                {{
                                                                    formatBrl(
                                                                        p.valor_total,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td class="py-1">
                                                                {{
                                                                    formatBrl(
                                                                        p.valor_parcela,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td
                                                                class="py-1 text-muted-foreground"
                                                            >
                                                                {{
                                                                    p.note ??
                                                                    '—'
                                                                }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <p
                                                    class="mt-2 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        c.ground_truth_summary
                                                            .refin_vs_portab_note
                                                    }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="
                                                    c.formatted_payload_for_agent
                                                "
                                                class="rounded-md border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border"
                                            >
                                                <p
                                                    class="mb-1 font-medium text-muted-foreground"
                                                >
                                                    Payload enviado ao agente
                                                    (texto exato da ferramenta)
                                                </p>
                                                <pre
                                                    class="max-h-48 overflow-auto font-mono text-xs whitespace-pre-wrap text-foreground"
                                                    >{{
                                                        c.formatted_payload_for_agent
                                                    }}</pre
                                                >
                                            </div>
                                            <div
                                                v-if="c.hallucinations?.length"
                                                class="overflow-x-auto"
                                            >
                                                <p
                                                    class="mb-1 font-medium text-muted-foreground"
                                                >
                                                    Alucinações
                                                </p>
                                                <table
                                                    class="w-full min-w-[36rem] text-xs"
                                                >
                                                    <thead>
                                                        <tr
                                                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                                                        >
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Campo
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Esperado
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Reportado
                                                            </th>
                                                            <th
                                                                class="pb-1 text-left font-medium text-muted-foreground"
                                                            >
                                                                Severidade
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                                                    >
                                                        <tr
                                                            v-for="(
                                                                h, i
                                                            ) in c.hallucinations"
                                                            :key="i"
                                                        >
                                                            <td class="py-1">
                                                                {{ h.field }}
                                                            </td>
                                                            <td class="py-1">
                                                                {{ h.expected }}
                                                            </td>
                                                            <td class="py-1">
                                                                {{ h.actual }}
                                                            </td>
                                                            <td class="py-1">
                                                                {{ h.severity }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div v-if="c.evaluation_report">
                                                <p
                                                    class="mb-1 font-medium text-muted-foreground"
                                                >
                                                    Relatório de avaliação
                                                </p>
                                                <pre
                                                    class="max-h-64 overflow-auto rounded-md border border-sidebar-border/70 bg-background p-3 text-xs whitespace-pre-wrap dark:border-sidebar-border"
                                                    >{{
                                                        c.evaluation_report
                                                    }}</pre
                                                >
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr
                                v-if="
                                    run.status === 'running' &&
                                    run.cycles.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-4 py-6 text-center text-muted-foreground"
                                >
                                    <Loader2
                                        class="mx-auto h-6 w-6 animate-spin"
                                    />
                                    <p class="mt-2">Executando ciclos...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
