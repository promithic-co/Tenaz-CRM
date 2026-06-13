<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import StatusRow from './partials/StatusRow.vue';
import TransitionMatrix from './partials/TransitionMatrix.vue';
import AddStatusModal from './partials/AddStatusModal.vue';
import DeleteStatusModal from './partials/DeleteStatusModal.vue';
import type { BreadcrumbItem } from '@/types';
import StatusPipelineController from '@/actions/App/Http/Controllers/StatusPipelineController';

type Status = {
    slug: string;
    label: string;
    color: string;
    is_terminal: boolean;
    is_canonical: boolean;
    position: number;
};

type Transition = {
    from: string;
    to: string;
};

type Props = {
    statuses: Status[];
    transitions: Transition[];
    lead_counts_by_status: Record<string, number>;
    canonical_slugs: string[];
    initial_status: string;
    has_persisted_machine: boolean;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes' },
    { title: 'Pipeline', href: '/configuracoes/pipeline' },
];

// ─── Local reactive state ─────────────────────────────────────────────────────

const localStatuses = ref<Status[]>([...props.statuses]);
const localTransitions = ref<Transition[]>([...props.transitions]);
const localLeadCounts = ref<Record<string, number>>({ ...props.lead_counts_by_status });
const savingSlug = ref<string | null>(null);
const showAdvancedTransitions = ref(false);

// ─── Add status modal ─────────────────────────────────────────────────────────

const showAddModal = ref(false);

function onStatusCreated(newStatus: Status) {
    localStatuses.value.push(newStatus);
}

// ─── Delete status modal ──────────────────────────────────────────────────────

const showDeleteModal = ref(false);
const deleteTarget = ref<Status | null>(null);

function requestDelete(slug: string) {
    const status = localStatuses.value.find(s => s.slug === slug);
    if (!status) return;
    deleteTarget.value = status;
    showDeleteModal.value = true;
}

function onStatusDeleted(slug: string) {
    localStatuses.value = localStatuses.value.filter(s => s.slug !== slug);
    localTransitions.value = localTransitions.value.filter(
        t => t.from !== slug && t.to !== slug,
    );
}

// ─── Update status (label / color) ────────────────────────────────────────────

function updateStatus(slug: string, attrs: Partial<Status>) {
    // Optimistic update
    const idx = localStatuses.value.findIndex(s => s.slug === slug);
    if (idx === -1) return;

    const original = { ...localStatuses.value[idx] };
    localStatuses.value[idx] = { ...localStatuses.value[idx], ...attrs };

    savingSlug.value = slug;

    router.put(StatusPipelineController.updateStatus(slug).url, attrs, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            const updated: Status[] = (page as any).props?.statuses ?? [];
            if (updated.length) {
                localStatuses.value = updated;
            }
        },
        onError: () => {
            // Revert on error
            localStatuses.value[idx] = original;
        },
        onFinish: () => {
            savingSlug.value = null;
        },
    });
}

// ─── Transitions ──────────────────────────────────────────────────────────────

function addTransition(from: string, to: string) {
    // Optimistic
    localTransitions.value.push({ from, to });

    router.post(StatusPipelineController.storeTransition().url, { from, to }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            localTransitions.value = localTransitions.value.filter(
                t => !(t.from === from && t.to === to),
            );
        },
    });
}

function removeTransition(from: string, to: string) {
    // Optimistic
    const prev = [...localTransitions.value];
    localTransitions.value = localTransitions.value.filter(
        t => !(t.from === from && t.to === to),
    );

    router.delete(StatusPipelineController.destroyTransition({ from, to }).url, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            localTransitions.value = prev;
        },
    });
}

// ─── Reset pipeline ───────────────────────────────────────────────────────────

const resetConfirmStep = ref(0); // 0 = idle, 1 = first confirm, 2 = type confirm
const resetInput = ref('');

function startReset() {
    resetConfirmStep.value = 1;
}

function cancelReset() {
    resetConfirmStep.value = 0;
    resetInput.value = '';
}

function confirmReset() {
    if (resetInput.value !== 'RESETAR') return;

    router.post(StatusPipelineController.reset().url, {}, {
        headers: { 'X-Confirm': '1' },
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            const p = (page as any).props ?? {};
            if (p.statuses) { localStatuses.value = p.statuses; }
            if (p.transitions) { localTransitions.value = p.transitions; }
            cancelReset();
        },
    });
}

// ─── Sorted statuses ──────────────────────────────────────────────────────────

const sortedStatuses = computed(() =>
    [...localStatuses.value].sort((a, b) => a.position - b.position),
);
</script>

<template>
    <Head title="Pipeline de Status" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 space-y-6 max-w-5xl">

            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-foreground">Pipeline de Status</h1>
                    <p class="text-sm text-muted-foreground">
                        Configure os nomes, cores e ordem do pipeline de leads.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button size="sm" @click="showAddModal = true">
                        + Adicionar Status
                    </Button>
                    <Button variant="outline" size="sm" @click="startReset">
                        Resetar para Padrão
                    </Button>
                </div>
            </div>

            <!-- Empty state: tenant using default machine (no persisted row) -->
            <div
                v-if="!has_persisted_machine"
                class="rounded-lg border border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground"
            >
                Você está usando o pipeline padrão. Clique em "Adicionar Status" ou edite um label para criar sua cópia personalizada.
            </div>

            <!-- Reset confirm dialog (inline) -->
            <div
                v-if="resetConfirmStep === 1"
                class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 space-y-3"
            >
                <p class="text-sm font-medium text-destructive">
                    Resetar o pipeline é uma ação destrutiva. Todos os status customizados serão removidos.
                    Se houver leads em status customizados, o reset sera bloqueado para preservar o contexto comercial.
                </p>
                <p class="text-sm text-foreground">Digite <code class="font-mono bg-muted px-1 rounded">RESETAR</code> para confirmar:</p>
                <div class="flex items-center gap-2">
                    <input
                        v-model="resetInput"
                        class="rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        placeholder="RESETAR"
                        autofocus
                    />
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="resetInput !== 'RESETAR'"
                        @click="confirmReset"
                    >
                        Confirmar Reset
                    </Button>
                    <Button variant="outline" size="sm" @click="cancelReset">Cancelar</Button>
                </div>
            </div>

            <!-- Main status list -->
            <div class="space-y-6">

                <!-- Left: Status list -->
                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-foreground/80">Statuses</h2>
                    <div class="space-y-1.5">
                        <StatusRow
                            v-for="status in sortedStatuses"
                            :key="status.slug"
                            :status="status"
                            :lead-count="localLeadCounts[status.slug] ?? 0"
                            :saving="savingSlug === status.slug"
                            @update="updateStatus"
                            @delete="requestDelete"
                        />
                    </div>
                </div>

                <!-- Advanced transition matrix -->
                <div class="rounded-lg border border-border bg-card">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-foreground"
                        @click="showAdvancedTransitions = !showAdvancedTransitions"
                    >
                        <span>Avancado: transicoes internas</span>
                        <span class="text-xs text-muted-foreground">{{ showAdvancedTransitions ? 'Ocultar' : 'Mostrar' }}</span>
                    </button>
                    <div v-if="showAdvancedTransitions" class="space-y-2 border-t border-border p-3">
                        <p class="text-xs text-muted-foreground">
                            Use apenas quando precisar ajustar caminhos internos. Status customizados ja recebem transicoes seguras automaticamente.
                        </p>
                        <TransitionMatrix
                            :statuses="sortedStatuses"
                            :transitions="localTransitions"
                            :canonical-slugs="canonical_slugs"
                            @add="addTransition"
                            @remove="removeTransition"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <AddStatusModal
            v-model:open="showAddModal"
            @created="onStatusCreated"
        />

        <DeleteStatusModal
            v-model:open="showDeleteModal"
            :status="deleteTarget"
            :lead-count="deleteTarget ? (localLeadCounts[deleteTarget.slug] ?? 0) : 0"
            @deleted="onStatusDeleted"
        />
    </AppLayout>
</template>
