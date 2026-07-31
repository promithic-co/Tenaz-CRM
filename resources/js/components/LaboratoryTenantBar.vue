<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Building2, Globe } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TenantSwitcher from '@/components/backoffice/TenantSwitcher.vue';
import { Button } from '@/components/ui/button';

/**
 * Laboratory pages read their data through `$user->tenantId`, which resolves the
 * backoffice's active tenant before falling back to the operator's own
 * membership. This bar makes that scope visible and switchable without leaving
 * the page — otherwise the numbers silently belong to whichever company was
 * selected last.
 */
const page = usePage();

const activeTenant = computed(
    () => page.props.backoffice?.active_tenant ?? null,
);

const switcherOpen = ref(false);
</script>

<template>
    <div>
        <div
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2"
            :class="
                activeTenant
                    ? 'border-amber-500/30 bg-amber-500/5'
                    : 'border-dashed bg-muted/30'
            "
        >
            <div class="flex items-center gap-2 text-sm">
                <component
                    :is="activeTenant ? Building2 : Globe"
                    :size="14"
                    :class="
                        activeTenant
                            ? 'text-amber-600'
                            : 'text-muted-foreground'
                    "
                />
                <span v-if="activeTenant" class="font-medium">
                    {{ activeTenant.name }}
                </span>
                <span v-else class="text-muted-foreground">
                    Nenhuma empresa selecionada — os dados abaixo usam a sua
                    própria conta.
                </span>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="switcherOpen = true"
            >
                {{ activeTenant ? 'Trocar empresa' : 'Selecionar empresa' }}
            </Button>
        </div>

        <TenantSwitcher
            :open="switcherOpen"
            @update:open="(value) => (switcherOpen = value)"
        />
    </div>
</template>
