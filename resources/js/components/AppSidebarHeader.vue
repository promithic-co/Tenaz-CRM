<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Search, Bell, AlertTriangle } from 'lucide-vue-next';
import { usePage, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const escalationCount = computed(() => (page.props as any).escalation_count ?? 0);
const criticalNotificationCount = computed(() => (page.props as any).critical_notification_count ?? 0);
const criticalNotifications = computed(() => (page.props as any).critical_notifications ?? []);
const notificationCount = computed(() => criticalNotificationCount.value + escalationCount.value);
const canManageCampaignRisk = computed(() => ['owner', 'administrator'].includes((page.props as any).auth?.currentRole ?? ''));

function openSearch() {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true }));
}

function keepPaused(campaignId: number) {
    router.post(CampaignController.keepPausedForQualityRisk(campaignId).url, {}, { preserveScroll: true });
}

function continueWithRisk(campaignId: number) {
    router.post(CampaignController.continueWithQualityRisk(campaignId).url, {}, { preserveScroll: true });
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="relative flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                    aria-label="Notificacoes"
                >
                    <Bell class="h-4 w-4" />
                    <span
                        v-if="notificationCount > 0"
                        class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold leading-none text-white"
                    >
                        {{ notificationCount > 9 ? '9+' : notificationCount }}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-96 max-w-[calc(100vw-2rem)]">
                <DropdownMenuLabel>Notificacoes</DropdownMenuLabel>
                <DropdownMenuSeparator />

                <div v-if="criticalNotifications.length > 0" class="max-h-96 overflow-y-auto p-1">
                    <div
                        v-for="notification in criticalNotifications"
                        :key="notification.id"
                        class="rounded-md px-2 py-2 text-sm hover:bg-accent"
                    >
                        <div class="flex items-start gap-2">
                            <AlertTriangle class="mt-0.5 h-4 w-4 text-red-600" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-foreground">{{ notification.title }}</p>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ notification.body }}</p>
                                <Link
                                    v-if="notification.action_url"
                                    :href="notification.action_url"
                                    class="mt-2 inline-flex text-xs font-medium text-primary hover:underline"
                                >
                                    Abrir campanha
                                </Link>
                                <div v-if="notification.campaign_id && canManageCampaignRisk" class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-input px-2 py-1 text-xs font-medium text-foreground hover:bg-muted"
                                        @click="keepPaused(notification.campaign_id)"
                                    >
                                        Manter pausada
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700"
                                        @click="continueWithRisk(notification.campaign_id)"
                                    >
                                        Continuar por risco
                                    </button>
                                </div>
                                <p v-else-if="notification.campaign_id" class="mt-2 text-xs text-muted-foreground">
                                    Um owner ou administrador deve decidir se a campanha continua.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-3 py-4 text-sm text-muted-foreground">
                    Nenhum alerta critico.
                </div>

                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link href="/atendimentos" class="w-full">
                        Atendimentos pendentes
                        <span v-if="escalationCount > 0" class="ml-auto text-xs text-muted-foreground">{{ escalationCount }}</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <button
            @click="openSearch"
            class="hidden sm:flex items-center gap-2 rounded-md border border-input bg-muted/50 px-3 py-1.5 text-xs text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
        >
            <Search class="h-3 w-3" />
            Buscar...
            <kbd class="ml-1 border rounded px-1 text-[10px]">⌘K</kbd>
        </button>
    </header>
</template>
