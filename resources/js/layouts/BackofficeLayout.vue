<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TenantSwitcher from '@/components/backoffice/TenantSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import { dashboard } from '@/routes';

const page = usePage();
const routes = useBackofficeRoutes();

const activeTenant = computed(
    () => page.props.backoffice?.active_tenant ?? null,
);

const switcherOpen = ref(false);

const navItems = computed(() => [
    { label: 'Empresas', href: routes.tenants() },
    { label: 'Agentes', href: routes.agents() },
    { label: 'Templates LLM', href: routes.templates() },
    { label: 'Modelos de agente', href: routes.nicheTemplates() },
]);

function isActive(href: string): boolean {
    return page.url === href || page.url.startsWith(`${href}/`);
}
</script>

<template>
    <div class="dark min-h-svh bg-zinc-950 text-zinc-100">
        <header class="border-b border-zinc-800 bg-zinc-900">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                    >
                        <Link :href="dashboard()">
                            <ArrowLeft :size="14" />
                            <span class="hidden sm:inline">Voltar ao app</span>
                        </Link>
                    </Button>
                    <span class="text-sm font-medium text-zinc-300"
                        >Backoffice</span
                    >
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm"
                        :class="
                            activeTenant
                                ? 'bg-amber-500/10 text-amber-300'
                                : 'text-zinc-400'
                        "
                    >
                        <Building2 :size="14" />
                        {{
                            activeTenant?.name ??
                            'Visão global (todas as empresas)'
                        }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                        @click="switcherOpen = true"
                    >
                        Trocar
                    </Button>
                </div>
            </div>

            <nav
                class="mx-auto flex max-w-6xl gap-5 overflow-x-auto px-4 sm:px-6"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="-mb-px shrink-0 border-b-2 py-2.5 text-sm transition-colors"
                    :class="
                        isActive(item.href)
                            ? 'border-zinc-100 text-zinc-100'
                            : 'border-transparent text-zinc-400 hover:text-zinc-200'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <slot />
        </main>

        <TenantSwitcher
            :open="switcherOpen"
            @update:open="(value) => (switcherOpen = value)"
        />
    </div>
</template>
