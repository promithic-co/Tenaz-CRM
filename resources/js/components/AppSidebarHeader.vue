<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Search, Bell } from 'lucide-vue-next';
import { usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

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

function openSearch() {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true }));
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
        <div class="relative">
            <Link href="/atendimentos" class="relative flex items-center justify-center h-8 w-8 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground">
                <Bell class="h-4 w-4" />
                <span
                    v-if="escalationCount > 0"
                    class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center leading-none"
                >
                    {{ escalationCount > 9 ? '9+' : escalationCount }}
                </span>
            </Link>
        </div>
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
