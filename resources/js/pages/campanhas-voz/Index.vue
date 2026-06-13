<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import { PhoneCall } from 'lucide-vue-next';
import { ref } from 'vue';
import type { BreadcrumbItem } from '@/types';
import VoiceCampaignController from '@/actions/App/Http/Controllers/VoiceCampaignController';

type VoiceInstance = { id: number; name: string; display_name: string | null };
type ContactList = { id: number; name: string };

type VoiceCampaign = {
    id: number;
    name: string;
    status: string;
    total_calls: number;
    total_answered: number;
    total_interested: number;
    created_at: string;
    voice_instance: VoiceInstance | null;
    contact_list: ContactList | null;
    calls_count: number;
    answered_calls_count: number;
    interested_calls_count: number;
};

type Props = {
    campaigns: VoiceCampaign[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campanhas de Voz', href: '/campanhas-voz' },
];

const actionForms = ref<Record<string, ReturnType<typeof useForm>>>({});

function getActionForm(key: string): ReturnType<typeof useForm> {
    if (!actionForms.value[key]) {
        actionForms.value[key] = useForm({});
    }
    return actionForms.value[key];
}

function startCampaign(id: number): void {
    getActionForm(`start-${id}`).post(VoiceCampaignController.start(id).url, { preserveScroll: true });
}

function pauseCampaign(id: number): void {
    getActionForm(`pause-${id}`).post(VoiceCampaignController.pause(id).url, { preserveScroll: true });
}

function resumeCampaign(id: number): void {
    getActionForm(`resume-${id}`).post(VoiceCampaignController.resume(id).url, { preserveScroll: true });
}

function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        draft: 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground',
        sending: 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400',
        paused: 'rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed: 'rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        failed: 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return map[status] ?? 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground';
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        draft: 'Rascunho',
        sending: 'Ligando',
        paused: 'Pausada',
        completed: 'Concluída',
        failed: 'Falha',
    };
    return map[status] ?? status;
}
</script>

<template>
    <Head title="Campanhas de Voz" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Campanhas de Voz</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ campaigns.length }}</span>
                    </div>
                    <Link
                        :href="VoiceCampaignController.create().url"
                        class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        + Nova Campanha de Voz
                    </Link>
                </div>

                <!-- Table -->
                <table class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Lista</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Ligações</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Atendidas</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Interessados</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Criada em</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            class="cursor-pointer transition-colors hover:bg-muted/40"
                            @click="$inertia.visit(VoiceCampaignController.show(campaign.id).url)"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">{{ campaign.name }}</td>
                            <td class="px-4 py-3">
                                <span :class="[statusBadgeClass(campaign.status), campaign.status === 'sending' ? 'animate-pulse' : '']">
                                    {{ statusLabel(campaign.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ campaign.contact_list?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-foreground">{{ campaign.total_calls ?? 0 }}</td>
                            <td class="px-4 py-3 text-xs text-foreground">{{ campaign.total_answered ?? 0 }}</td>
                            <td class="px-4 py-3 text-xs text-foreground">{{ campaign.total_interested ?? 0 }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ campaign.created_at }}</td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <template v-if="campaign.status === 'draft'">
                                        <button
                                            :disabled="getActionForm(`start-${campaign.id}`).processing"
                                            class="rounded px-2 py-1 text-xs text-green-600 transition-colors hover:bg-green-50 hover:text-green-800 dark:hover:bg-green-950/30 disabled:opacity-50"
                                            @click="startCampaign(campaign.id)"
                                        >
                                            Iniciar
                                        </button>
                                    </template>
                                    <template v-else-if="campaign.status === 'sending'">
                                        <button
                                            :disabled="getActionForm(`pause-${campaign.id}`).processing"
                                            class="rounded px-2 py-1 text-xs text-yellow-600 transition-colors hover:bg-yellow-50 hover:text-yellow-800 dark:hover:bg-yellow-950/30 disabled:opacity-50"
                                            @click="pauseCampaign(campaign.id)"
                                        >
                                            Pausar
                                        </button>
                                    </template>
                                    <template v-else-if="campaign.status === 'paused'">
                                        <button
                                            :disabled="getActionForm(`resume-${campaign.id}`).processing"
                                            class="rounded px-2 py-1 text-xs text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-800 dark:hover:bg-blue-950/30 disabled:opacity-50"
                                            @click="resumeCampaign(campaign.id)"
                                        >
                                            Retomar
                                        </button>
                                    </template>
                                    <Link
                                        :href="VoiceCampaignController.show(campaign.id).url"
                                        class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        Ver
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="campaigns.length === 0"
                    :icon="PhoneCall"
                    title="Nenhuma campanha de voz"
                    description="Crie sua primeira campanha de voz para ligar automaticamente para seus contatos."
                >
                    <Link
                        :href="VoiceCampaignController.create().url"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        Nova Campanha de Voz
                    </Link>
                </EmptyState>
            </div>
        </div>
    </AppLayout>
</template>
