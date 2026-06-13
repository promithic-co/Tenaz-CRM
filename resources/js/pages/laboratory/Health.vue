<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { CheckCircle, XCircle, AlertTriangle, RefreshCw, Database, Cpu, HardDrive, Layers } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

type CheckResult = {
    status: 'ok' | 'warning' | 'error';
    [key: string]: string | number;
};

type Props = {
    checks: Record<string, CheckResult>;
    horizon: CheckResult;
    failedJobs: number;
    checkedAt: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'Health', href: '/laboratory/health' },
];

const refreshing = ref(false);

function refresh() {
    refreshing.value = true;
    router.reload({ onFinish: () => (refreshing.value = false) });
}

function statusColor(status: string) {
    if (status === 'ok') return 'text-green-600';
    if (status === 'warning') return 'text-yellow-600';
    return 'text-red-600';
}

function statusBg(status: string) {
    if (status === 'ok') return 'bg-green-50 border-green-200';
    if (status === 'warning') return 'bg-yellow-50 border-yellow-200';
    return 'bg-red-50 border-red-200';
}

const allChecks = {
    database: { label: 'Database', icon: Database },
    cache: { label: 'Cache / Redis', icon: Cpu },
    queue: { label: 'Queue', icon: Layers },
    disk: { label: 'Disk', icon: HardDrive },
};

function formatDetails(check: CheckResult) {
    return Object.entries(check)
        .filter(([k]) => k !== 'status')
        .map(([k, v]) => `${k}: ${v}`)
        .join(' · ');
}

const overallOk = Object.values(props.checks).every((c) => c.status === 'ok') && props.horizon.status === 'ok';
</script>

<template>
    <Head title="Health — Laboratory" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">System Health</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        Checked at {{ new Date(checkedAt).toLocaleString('pt-BR') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        :class="overallOk ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        role="status"
                        :aria-label="overallOk ? 'System healthy' : 'System degraded'"
                    >
                        {{ overallOk ? 'Healthy' : 'Degraded' }}
                    </span>
                    <button
                        class="flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                        :disabled="refreshing"
                        aria-label="Refresh health checks"
                        @click="refresh"
                    >
                        <RefreshCw :class="['size-3.5', { 'animate-spin': refreshing }]" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Checks Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(meta, key) in allChecks"
                    :key="key"
                    :class="['rounded-lg border p-4', statusBg(checks[key]?.status ?? 'error')]"
                    role="region"
                    :aria-label="`${meta.label} health check`"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium">{{ meta.label }}</p>
                            <p :class="['mt-1 text-xs font-semibold uppercase', statusColor(checks[key]?.status ?? 'error')]">
                                {{ checks[key]?.status ?? 'unknown' }}
                            </p>
                        </div>
                        <component :is="meta.icon" class="size-4 shrink-0 opacity-60" aria-hidden="true" />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ formatDetails(checks[key] ?? { status: 'error' }) }}
                    </p>
                </div>
            </div>

            <!-- Horizon + Failed Jobs -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div :class="['rounded-lg border p-4', statusBg(horizon.status)]" role="region" aria-label="Horizon queue worker status">
                    <p class="text-sm font-medium">Horizon</p>
                    <p :class="['mt-1 text-xs font-semibold uppercase', statusColor(horizon.status)]">
                        {{ horizon.horizon_status ?? horizon.status }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ formatDetails(horizon) }}
                    </p>
                </div>

                <div
                    :class="['rounded-lg border p-4', failedJobs > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200']"
                    role="region"
                    aria-label="Failed jobs count"
                >
                    <p class="text-sm font-medium">Failed Jobs</p>
                    <p :class="['mt-1 text-2xl font-bold', failedJobs > 0 ? 'text-red-700' : 'text-green-700']">
                        {{ failedJobs }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Total in failed_jobs table</p>
                </div>
            </div>

            <!-- Status Legend -->
            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <CheckCircle class="size-3.5 text-green-600" aria-hidden="true" />
                    OK
                </span>
                <span class="flex items-center gap-1">
                    <AlertTriangle class="size-3.5 text-yellow-600" aria-hidden="true" />
                    Warning
                </span>
                <span class="flex items-center gap-1">
                    <XCircle class="size-3.5 text-red-600" aria-hidden="true" />
                    Error
                </span>
            </div>
        </div>
    </AppLayout>
</template>
