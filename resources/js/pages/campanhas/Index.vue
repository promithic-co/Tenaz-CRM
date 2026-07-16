<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import { Megaphone } from 'lucide-vue-next';
import { ref } from 'vue';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import EmptyState from '@/components/EmptyState.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ContactList = { id: number; name: string };
type WhatsappTemplate = { id: number; name: string };

type Campaign = {
    id: number;
    name: string;
    status: string;
    total_recipients: number;
    total_sent: number;
    total_delivered: number;
    total_failed: number;
    created_at: string;
    contact_list: ContactList | null;
    whatsapp_template: WhatsappTemplate | null;
};

type Props = {
    campaigns: {
        data: Campaign[];
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disparos', href: '/campanhas' },
    { title: 'Campanhas', href: '/campanhas' },
];

const deleteConfirmId = ref<number | null>(null);
const deleteForm = useForm({});
const actionForms = ref<Record<string, ReturnType<typeof useForm>>>({});

function getActionForm(key: string): ReturnType<typeof useForm> {
    if (!actionForms.value[key]) {
        actionForms.value[key] = useForm({});
    }
    return actionForms.value[key];
}

function startCampaign(id: number): void {
    getActionForm(`start-${id}`).post(CampaignController.start(id).url, {
        preserveScroll: true,
    });
}

function pauseCampaign(id: number): void {
    getActionForm(`pause-${id}`).post(CampaignController.pause(id).url, {
        preserveScroll: true,
    });
}

function resumeCampaign(id: number): void {
    getActionForm(`resume-${id}`).post(CampaignController.resume(id).url, {
        preserveScroll: true,
    });
}

function deleteCampaign(): void {
    if (deleteConfirmId.value === null) {
        return;
    }
    deleteForm.delete(CampaignController.destroy(deleteConfirmId.value).url, {
        onSuccess: () => {
            deleteConfirmId.value = null;
        },
    });
}

function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        draft: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        scheduled:
            'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        sending:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        paused: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed:
            'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled:
            'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
    };
    return (
        map[status] ??
        'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
    );
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        draft: 'Rascunho',
        scheduled: 'Agendada',
        sending: 'Enviando',
        paused: 'Pausada',
        completed: 'Concluída',
        failed: 'Falha',
        cancelled: 'Cancelada',
    };
    return map[status] ?? status;
}

function deliveryRateClass(rate: number): string {
    if (rate >= 90) {
        return 'text-green-600 dark:text-green-400';
    }
    if (rate >= 70) {
        return 'text-yellow-600 dark:text-yellow-400';
    }
    return 'text-red-600 dark:text-red-400';
}

function deliveryRate(campaign: Campaign): number {
    if (!campaign.total_sent || campaign.total_sent === 0) {
        return 0;
    }
    return Math.round((campaign.total_delivered / campaign.total_sent) * 100);
}
</script>

<template>
    <Head title="Campanhas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
            >
                <!-- Header -->
                <div
                    class="flex min-w-full flex-col gap-3 border-b border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >Campanhas</span
                        >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ campaigns.total }} campanhas</span
                        >
                    </div>
                    <Link
                        :href="CampaignController.create().url"
                        class="flex min-h-10 items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 sm:min-h-0"
                    >
                        + Nova Campanha
                    </Link>
                </div>

                <!-- Table -->
                <table class="w-full min-w-[56rem] text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Nome
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Lista
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Template
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Enviados/Total
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Taxa Entrega
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Criada em
                            </th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="campaign in campaigns.data"
                            :key="campaign.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ campaign.name }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        statusBadgeClass(campaign.status),
                                        campaign.status === 'sending'
                                            ? 'animate-pulse'
                                            : '',
                                    ]"
                                >
                                    {{ statusLabel(campaign.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ campaign.contact_list?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ campaign.whatsapp_template?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-foreground">
                                {{ campaign.total_sent ?? 0 }}/{{
                                    campaign.total_recipients ?? 0
                                }}
                            </td>
                            <td
                                class="px-4 py-3 text-xs font-semibold"
                                :class="
                                    deliveryRateClass(deliveryRate(campaign))
                                "
                            >
                                {{ deliveryRate(campaign) }}%
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ campaign.created_at }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- Draft / Scheduled -->
                                    <template
                                        v-if="
                                            campaign.status === 'draft' ||
                                            campaign.status === 'scheduled'
                                        "
                                    >
                                        <button
                                            :disabled="
                                                getActionForm(
                                                    `start-${campaign.id}`,
                                                ).processing
                                            "
                                            class="rounded px-2 py-1 text-xs text-green-600 transition-colors hover:bg-green-50 hover:text-green-800 disabled:opacity-50 dark:hover:bg-green-950/30"
                                            @click="startCampaign(campaign.id)"
                                        >
                                            Iniciar
                                        </button>
                                        <button
                                            class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                            @click="
                                                deleteConfirmId = campaign.id
                                            "
                                        >
                                            Excluir
                                        </button>
                                    </template>
                                    <!-- Sending -->
                                    <template
                                        v-else-if="
                                            campaign.status === 'sending'
                                        "
                                    >
                                        <button
                                            :disabled="
                                                getActionForm(
                                                    `pause-${campaign.id}`,
                                                ).processing
                                            "
                                            class="rounded px-2 py-1 text-xs text-yellow-600 transition-colors hover:bg-yellow-50 hover:text-yellow-800 disabled:opacity-50 dark:hover:bg-yellow-950/30"
                                            @click="pauseCampaign(campaign.id)"
                                        >
                                            Pausar
                                        </button>
                                        <Link
                                            :href="
                                                CampaignController.show(
                                                    campaign.id,
                                                ).url
                                            "
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            Ver
                                        </Link>
                                    </template>
                                    <!-- Paused -->
                                    <template
                                        v-else-if="campaign.status === 'paused'"
                                    >
                                        <button
                                            :disabled="
                                                getActionForm(
                                                    `resume-${campaign.id}`,
                                                ).processing
                                            "
                                            class="rounded px-2 py-1 text-xs text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-800 disabled:opacity-50 dark:hover:bg-blue-950/30"
                                            @click="resumeCampaign(campaign.id)"
                                        >
                                            Retomar
                                        </button>
                                        <Link
                                            :href="
                                                CampaignController.show(
                                                    campaign.id,
                                                ).url
                                            "
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            Ver
                                        </Link>
                                    </template>
                                    <!-- Completed / Failed -->
                                    <template v-else>
                                        <Link
                                            :href="
                                                CampaignController.show(
                                                    campaign.id,
                                                ).url
                                            "
                                            class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            Ver
                                        </Link>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="campaigns.data.length === 0"
                    :icon="Megaphone"
                    title="Nenhuma campanha criada"
                    description="Crie sua primeira campanha para disparar mensagens em massa."
                >
                    <Link
                        :href="CampaignController.create().url"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        Nova Campanha
                    </Link>
                </EmptyState>

                <!-- Pagination -->
                <div
                    v-if="campaigns.links?.length > 3"
                    class="flex items-center gap-1 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                >
                    <template v-for="link in campaigns.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                link.active
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            ]"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1 text-sm text-muted-foreground/40"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Delete Confirm Dialog -->
    <Dialog
        :open="deleteConfirmId !== null"
        @update:open="
            (v) => {
                if (!v) deleteConfirmId = null;
            }
        "
    >
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Excluir Campanha</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Tem certeza que deseja excluir esta campanha? Esta ação não pode
                ser desfeita.
            </p>
            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    @click="deleteConfirmId = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="deleteForm.processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    @click="deleteCampaign"
                >
                    {{ deleteForm.processing ? 'Excluindo...' : 'Excluir' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
