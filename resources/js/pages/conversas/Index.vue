<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import echo from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import ConversationSidebar from './partials/ConversationSidebar.vue';
import ConversationThread from './partials/ConversationThread.vue';
import LeadDetailsPanel from './partials/LeadDetailsPanel.vue';
import type { ActiveConversation, ConversationFilters, LeadPaginator, TransferTarget } from './types';

type Props = {
    leads: LeadPaginator;
    filters: ConversationFilters;
    instances: Array<{ name: string; label: string }>;
    transfer_targets: TransferTarget[];
    activeConversation: ActiveConversation | null;
};

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    if (!props.activeConversation) {
        return [{ title: 'Conversas', href: '/conversas' }];
    }

    return [
        { title: 'Conversas', href: '/conversas' },
        { title: props.activeConversation.lead.nome, href: `/conversas/${props.activeConversation.lead.id}` },
    ];
});

const pageTitle = computed(() => props.activeConversation?.lead.nome ?? 'Conversas');
const activeLeadId = computed(() => props.activeConversation?.lead.id ?? null);

const page = usePage();
const tenantId = computed(() => (page.props.auth as any)?.user?.tenant_id as string | undefined);
const flashMessage = computed(() => (page.props.flash as string | null) ?? null);
const flashError = computed(() => (page.props.flash_error as string | null) ?? null);
const dismissedFlash = ref<string | null>(null);
const dismissedError = ref<string | null>(null);
const visibleFlash = computed(() => (flashMessage.value && flashMessage.value !== dismissedFlash.value ? flashMessage.value : null));
const visibleError = computed(() => (flashError.value && flashError.value !== dismissedError.value ? flashError.value : null));

function dismissFlash(): void { dismissedFlash.value = flashMessage.value; }
function dismissError(): void { dismissedError.value = flashError.value; }

watch(flashMessage, (next) => {
    if (next && next !== dismissedFlash.value) {
        setTimeout(() => { if (flashMessage.value === next) { dismissedFlash.value = next; } }, 5000);
    }
});

let handoffChannel: ReturnType<typeof echo.private> | null = null;

function reloadConversation(): void {
    router.reload({ only: ['activeConversation', 'leads'] });
}

function reloadLeads(): void {
    router.reload({ only: ['leads'] });
}

function subscribeHandoffChannel(leadId: number | string): void {
    handoffChannel = echo.private(`conversation.${leadId}`);
    handoffChannel
        .listen('.handoff.claimed', reloadConversation)
        .listen('.handoff.resolved', reloadConversation)
        .listen('.handoff.returned_to_ai', reloadConversation);
}

function unsubscribeHandoffChannel(leadId: number | string): void {
    echo.leave(`conversation.${leadId}`);
    handoffChannel = null;
}

onMounted(() => {
    const tid = tenantId.value;
    if (tid) {
        echo.private(`conversations.${tid}`).listen('.conversation.assignment.changed', reloadLeads);
    }

    if (activeLeadId.value) {
        subscribeHandoffChannel(activeLeadId.value);
    }
});

watch(activeLeadId, (newId, oldId) => {
    if (oldId) {
        unsubscribeHandoffChannel(oldId);
    }
    if (newId) {
        subscribeHandoffChannel(newId);
    }
});

onUnmounted(() => {
    const tid = tenantId.value;
    if (tid) {
        echo.leave(`conversations.${tid}`);
    }
    if (activeLeadId.value) {
        echo.leave(`conversation.${activeLeadId.value}`);
    }
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-7.5rem)] flex-col overflow-hidden p-4">
            <div v-if="visibleError" class="mb-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-400">
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <span class="flex-1">{{ visibleError }}</span>
                <button type="button" aria-label="Fechar" class="rounded p-1 hover:bg-rose-100 dark:hover:bg-rose-900/40" @click="dismissError">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
            <div v-else-if="visibleFlash" class="mb-3 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-400">
                <CheckCircle class="mt-0.5 h-4 w-4 shrink-0" />
                <span class="flex-1">{{ visibleFlash }}</span>
                <button type="button" aria-label="Fechar" class="rounded p-1 hover:bg-emerald-100 dark:hover:bg-emerald-900/40" @click="dismissFlash">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="grid min-h-0 flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border lg:grid-cols-[20rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)_22rem]">
                <ConversationSidebar
                    :leads="leads"
                    :filters="filters"
                    :instances="instances"
                    :active-lead-id="activeLeadId"
                    :transfer-targets="transfer_targets"
                />

                <ConversationThread
                    :key="activeConversation?.lead.id ?? 'empty-thread'"
                    :conversation="activeConversation"
                />

                <LeadDetailsPanel
                    v-if="activeConversation"
                    :key="activeConversation.lead.id"
                    :conversation="activeConversation"
                />
            </div>
        </div>
    </AppLayout>
</template>
