<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, GripVertical, Phone, Sparkles } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as showContact } from '@/routes/contatos';
import { move } from '@/routes/pipeline';
import type { BreadcrumbItem } from '@/types';

type Status = {
    slug: string;
    label: string;
    color: string;
    is_terminal: boolean;
};

type LeadTag = {
    id: number;
    name: string;
    slug: string;
    color: string;
    is_hot: boolean;
};

type LeadCard = {
    id: number;
    contact_id: number | null;
    nome: string;
    whatsapp: string | null;
    status: string;
    automation_state: 'active' | 'manual';
    followup_status: string | null;
    source_label: string;
    last_message: string;
    last_interaction_at: string | null;
    sla_due_at: string | null;
    tags: LeadTag[];
};

type PipelineColumn = {
    data: LeadCard[];
    next_cursor: string | null;
    count: number;
};

type Props = {
    statuses: Status[];
    columns: Record<string, PipelineColumn>;
    filters: Record<string, unknown>;
    tenantId: string;
    agents: Array<{ id: number; name: string }>;
    instances: Array<{ id: number; name: string }>;
    tagsCatalog: Array<{
        id: number;
        name: string;
        slug: string;
        color: string;
    }>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pipeline', href: '/pipeline' },
];
const movingLeadId = ref<number | null>(null);
const moveError = ref<string | null>(null);
const columnsState = ref<Record<string, LeadCard[]>>(
    buildColumnsState(props.columns),
);

const totalVisibleLeads = computed(() => {
    return Object.values(columnsState.value).reduce(
        (total, leads) => total + leads.length,
        0,
    );
});

const tagColorClasses: Record<string, string> = {
    gray: 'border-slate-500/25 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    red: 'border-red-500/25 bg-red-500/10 text-red-700 dark:text-red-300',
    orange: 'border-orange-500/25 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    yellow: 'border-yellow-500/25 bg-yellow-500/10 text-yellow-700 dark:text-yellow-300',
    green: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    blue: 'border-blue-500/25 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    purple: 'border-purple-500/25 bg-purple-500/10 text-purple-700 dark:text-purple-300',
    pink: 'border-pink-500/25 bg-pink-500/10 text-pink-700 dark:text-pink-300',
};

watch(
    () => props.columns,
    (columns) => {
        columnsState.value = buildColumnsState(columns);
    },
    { deep: true },
);

function buildColumnsState(
    columns: Record<string, PipelineColumn>,
): Record<string, LeadCard[]> {
    return Object.fromEntries(
        Object.entries(columns).map(([slug, column]) => [
            slug,
            column.data.map((lead) => ({ ...lead, tags: [...lead.tags] })),
        ]),
    );
}

function columnItems(slug: string): LeadCard[] {
    if (!columnsState.value[slug]) {
        columnsState.value[slug] = [];
    }

    return columnsState.value[slug];
}

function handleMoveEnd(event: SortableEvent): void {
    const leadId = Number((event.item as HTMLElement | null)?.dataset.leadId);
    const fromStatus = (event.from as HTMLElement | null)?.dataset.status;
    const toStatus = (event.to as HTMLElement | null)?.dataset.status;

    if (!leadId || !fromStatus || !toStatus || fromStatus === toStatus) {
        return;
    }

    const movedLead = columnsState.value[toStatus]?.find(
        (lead) => lead.id === leadId,
    );

    if (movedLead) {
        movedLead.status = toStatus;
    }

    movingLeadId.value = leadId;

    router.post(
        move.url(),
        {
            lead_id: leadId,
            from_status: fromStatus,
            to_status: toStatus,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                moveError.value = null;
                router.reload({ only: ['columns'] });
            },
            onError: (errors) => {
                moveError.value =
                    errors.to_status ??
                    errors.from_status ??
                    'Nao foi possivel salvar a mudanca de status.';
                router.reload({ only: ['columns'] });
            },
            onFinish: () => {
                movingLeadId.value = null;
            },
        },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Sem interacao';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatTime(value: string | null): string {
    if (!value) {
        return 'Sem horario';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function tagClasses(tag: LeadTag): string {
    if (tag.is_hot) {
        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    return tagColorClasses[tag.color] ?? tagColorClasses.gray;
}

function formatPhone(value: string | null): string {
    if (!value) {
        return 'Sem telefone';
    }

    const digits = value.replace(/\D/g, '');
    const withoutCountryCode =
        digits.startsWith('55') && digits.length >= 12
            ? digits.slice(2)
            : digits;

    if (withoutCountryCode.length === 11) {
        return withoutCountryCode.replace(
            /^(\d{2})(\d{5})(\d{4})$/,
            '($1) $2-$3',
        );
    }

    if (withoutCountryCode.length === 10) {
        return withoutCountryCode.replace(
            /^(\d{2})(\d{4})(\d{4})$/,
            '($1) $2-$3',
        );
    }

    return withoutCountryCode || value.replace(/^\+?55\s?/, '');
}
</script>

<template>
    <Head title="Pipeline" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-[calc(100svh-3.5rem)] min-h-0 flex-col gap-3 overflow-hidden p-2 sm:h-[calc(100svh-4rem)] sm:gap-4 sm:p-4 lg:h-[calc(100svh-7.5rem)]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1
                        class="text-xl font-semibold tracking-normal text-foreground"
                    >
                        Pipeline
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ totalVisibleLeads }} leads carregados
                    </p>
                </div>

                <div
                    v-if="moveError"
                    role="status"
                    class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ moveError }}
                </div>
            </div>

            <div
                class="pipeline-board-scroll min-h-0 flex-1 snap-x snap-mandatory scrollbar-thin scrollbar-thumb-muted-foreground/40 scrollbar-track-transparent overflow-x-auto overscroll-x-contain sm:snap-none"
            >
                <div
                    class="grid h-full auto-cols-[min(18rem,calc(100vw-1rem))] grid-flow-col gap-2.5 pb-2 sm:auto-cols-[16.5rem]"
                >
                    <section
                        v-for="status in statuses"
                        :key="status.slug"
                        class="flex min-h-0 snap-start flex-col rounded-lg border border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border"
                    >
                        <header
                            class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-2.5 py-2 dark:border-sidebar-border"
                        >
                            <div class="min-w-0">
                                <h2
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ status.label }}
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    {{ columns[status.slug]?.count ?? 0 }} no
                                    total
                                </p>
                            </div>

                            <span
                                class="h-3 w-3 shrink-0 rounded-full border border-background"
                                :style="{ backgroundColor: status.color }"
                            />
                        </header>

                        <VueDraggable
                            v-model="columnsState[status.slug]"
                            tag="div"
                            :data-status="status.slug"
                            class="pipeline-column-scroll min-h-0 flex-1 scrollbar-thin scrollbar-thumb-muted-foreground/40 scrollbar-track-transparent space-y-1.5 overflow-y-auto p-1.5"
                            group="pipeline-leads"
                            handle=".pipeline-card-handle"
                            :sort="false"
                            :animation="150"
                            ghost-class="pipeline-card-ghost"
                            chosen-class="pipeline-card-chosen"
                            @end="handleMoveEnd"
                        >
                            <article
                                v-for="lead in columnItems(status.slug)"
                                :key="lead.id"
                                :data-lead-id="lead.id"
                                class="rounded-lg border border-border bg-card text-card-foreground shadow-xs transition hover:border-ring/60"
                                :class="{
                                    'opacity-60': movingLeadId === lead.id,
                                }"
                            >
                                <div class="flex items-start gap-1.5 p-2 pb-1">
                                    <button
                                        type="button"
                                        class="pipeline-card-handle -m-1 flex size-10 shrink-0 cursor-grab touch-none items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing sm:m-0 sm:size-7"
                                        aria-label="Mover lead"
                                    >
                                        <GripVertical class="h-3.5 w-3.5" />
                                    </button>

                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        <div
                                            class="flex items-start justify-between gap-1.5"
                                        >
                                            <div class="min-w-0">
                                                <h3
                                                    class="truncate text-sm leading-4 font-semibold text-foreground"
                                                >
                                                    {{ lead.nome }}
                                                </h3>
                                                <div
                                                    class="mt-1 flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Phone
                                                        class="h-3 w-3 shrink-0"
                                                    />
                                                    <span class="truncate">{{
                                                        formatPhone(
                                                            lead.whatsapp,
                                                        )
                                                    }}</span>
                                                    <span
                                                        v-if="
                                                            lead.followup_status ===
                                                            'active'
                                                        "
                                                        class="shrink-0 rounded-md border border-red-500/35 bg-muted px-1.5 py-0.5 text-[10px] leading-3 font-medium text-red-600 dark:bg-muted/50 dark:text-red-300"
                                                    >
                                                        Follow-up ativo
                                                    </span>
                                                </div>
                                            </div>
                                            <span
                                                class="shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                                                >#{{ lead.id }}</span
                                            >
                                        </div>

                                        <div
                                            class="rounded-md bg-muted/35 px-2 py-1 text-[11px] leading-4 text-muted-foreground"
                                        >
                                            <p class="truncate">
                                                <span
                                                    class="font-medium text-foreground/80"
                                                    >Origem:</span
                                                >
                                                {{ lead.source_label }}
                                            </p>
                                            <p class="truncate">
                                                <span
                                                    class="font-medium text-foreground/80"
                                                    >Ultima msg:</span
                                                >
                                                {{
                                                    formatTime(
                                                        lead.last_interaction_at,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="lead.tags.length > 0"
                                    class="flex flex-wrap items-center gap-1 px-2 pb-1.5"
                                >
                                    <span
                                        v-for="tag in lead.tags"
                                        :key="tag.id"
                                        class="max-w-full truncate rounded-md border px-1.5 py-0.5 text-[11px] leading-4 font-medium"
                                        :class="tagClasses(tag)"
                                    >
                                        {{ tag.name }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-2 border-t border-border/70 px-2 py-1 text-[10px] text-muted-foreground"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-1.5"
                                    >
                                        <Sparkles
                                            v-if="
                                                lead.automation_state ===
                                                'active'
                                            "
                                            class="h-3.5 w-3.5 shrink-0 text-sky-500"
                                            aria-label="IA ativa"
                                        />
                                        <Link
                                            v-if="lead.contact_id"
                                            :href="
                                                showContact.url(lead.contact_id)
                                            "
                                            class="inline-flex items-center gap-1 rounded-md px-1 py-0.5 font-medium text-foreground transition hover:bg-muted"
                                        >
                                            Ver Contato
                                            <ExternalLink class="h-3 w-3" />
                                        </Link>
                                    </div>

                                    <time
                                        class="shrink-0 text-right leading-3"
                                        >{{
                                            formatDate(lead.last_interaction_at)
                                        }}</time
                                    >
                                </div>
                            </article>

                            <div
                                v-if="columnItems(status.slug).length === 0"
                                class="flex h-24 items-center justify-center rounded-lg border border-dashed border-border px-3 text-center text-sm text-muted-foreground"
                            >
                                Sem leads neste status
                            </div>
                        </VueDraggable>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.pipeline-card-ghost) {
    opacity: 0.4;
}

:deep(.pipeline-card-chosen) {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}

.pipeline-board-scroll,
.pipeline-column-scroll {
    scrollbar-width: thin;
    scrollbar-color: color-mix(
            in oklab,
            var(--muted-foreground) 45%,
            transparent
        )
        transparent;
}

.pipeline-board-scroll::-webkit-scrollbar,
.pipeline-column-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.pipeline-board-scroll::-webkit-scrollbar-track,
.pipeline-column-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.pipeline-board-scroll::-webkit-scrollbar-thumb,
.pipeline-column-scroll::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: color-mix(in oklab, var(--muted-foreground) 45%, transparent);
}
</style>
