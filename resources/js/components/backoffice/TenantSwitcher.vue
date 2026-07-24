<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';

type BackofficeTenant = { id: string; name: string };

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const page = usePage();
const routes = useBackofficeRoutes();

const tenants = computed<BackofficeTenant[]>(
    () => page.props.backoffice?.tenants ?? [],
);
const activeTenantId = computed<string | null>(
    () => page.props.backoffice?.active_tenant?.id ?? null,
);

const search = ref('');
const processing = ref(false);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term === ''
        ? tenants.value
        : tenants.value.filter((tenant) =>
              tenant.name.toLowerCase().includes(term),
          );
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = '';
        }
    },
);

function select(tenantId: string): void {
    processing.value = true;

    router.post(
        routes.activeTenant(),
        { tenant_id: tenantId },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
        },
    );
}

function clear(): void {
    processing.value = true;

    router.delete(routes.activeTenant(), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            emit('update:open', false);
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Trocar de empresa</DialogTitle>
                <DialogDescription>
                    Ao selecionar uma empresa, todo o sistema passa a ser
                    exibido como ela.
                </DialogDescription>
            </DialogHeader>

            <Input v-model="search" placeholder="Buscar empresa..." autofocus />

            <div class="max-h-72 overflow-y-auto rounded-md border">
                <button
                    v-for="tenant in filtered"
                    :key="tenant.id"
                    type="button"
                    :disabled="processing"
                    class="flex w-full items-center gap-2 border-b px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-muted/50 disabled:opacity-50"
                    @click="select(tenant.id)"
                >
                    <Building2
                        :size="14"
                        class="shrink-0 text-muted-foreground"
                    />
                    <span class="flex-1 truncate">{{ tenant.name }}</span>
                    <Check
                        v-if="tenant.id === activeTenantId"
                        :size="14"
                        class="shrink-0 text-primary"
                    />
                </button>

                <p
                    v-if="filtered.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Nenhuma empresa encontrada.
                </p>
            </div>

            <Button
                v-if="activeTenantId"
                type="button"
                variant="outline"
                size="sm"
                :disabled="processing"
                @click="clear"
            >
                Sair da empresa (visão global)
            </Button>
        </DialogContent>
    </Dialog>
</template>
