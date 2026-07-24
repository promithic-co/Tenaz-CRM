<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type Tenant = {
    id: number;
    name: string;
    users_count: number;
    agents_count: number;
    created_at: string;
};

const props = defineProps<{
    tenants: Tenant[];
}>();

const page = usePage();
const routes = useBackofficeRoutes();

const activeTenantId = computed<string | null>(
    () => page.props.backoffice?.active_tenant?.id ?? null,
);

const search = ref('');
const processing = ref(false);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term === ''
        ? props.tenants
        : props.tenants.filter((tenant) =>
              tenant.name.toLowerCase().includes(term),
          );
});

function isActive(tenant: Tenant): boolean {
    return String(tenant.id) === activeTenantId.value;
}

function select(tenant: Tenant): void {
    processing.value = true;

    router.post(
        routes.activeTenant(),
        { tenant_id: String(tenant.id) },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        },
    );
}

function clear(): void {
    processing.value = true;

    router.delete(routes.activeTenant(), {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}
</script>

<template>
    <BackofficeLayout>
        <Head title="Empresas — Backoffice" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-zinc-100">
                        Empresas
                    </h1>
                    <p class="mt-1 text-sm text-zinc-400">
                        Escolha a empresa que você quer gerenciar. A seleção
                        vale para todo o sistema até você sair dela.
                    </p>
                </div>
                <Button
                    v-if="activeTenantId"
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                    @click="clear"
                >
                    Voltar à visão global
                </Button>
            </div>

            <Input
                v-model="search"
                placeholder="Buscar empresa..."
                class="max-w-sm"
            />

            <div class="overflow-x-auto rounded-md border border-zinc-800">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead>
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Nome
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Usuários
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Agentes
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Criado em
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="filtered.length > 0">
                        <tr
                            v-for="tenant in filtered"
                            :key="tenant.id"
                            class="border-t border-zinc-800"
                            :class="isActive(tenant) ? 'bg-amber-500/5' : ''"
                        >
                            <td class="px-4 py-3 font-medium text-zinc-100">
                                <span class="flex items-center gap-2">
                                    {{ tenant.name }}
                                    <Check
                                        v-if="isActive(tenant)"
                                        :size="14"
                                        class="text-amber-300"
                                    />
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-zinc-400">
                                {{ tenant.users_count }}
                            </td>
                            <td class="px-4 py-3 text-right text-zinc-400">
                                {{ tenant.agents_count }}
                            </td>
                            <td class="px-4 py-3 text-zinc-400">
                                {{ formatDate(tenant.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing || isActive(tenant)"
                                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                                    @click="select(tenant)"
                                >
                                    {{
                                        isActive(tenant)
                                            ? 'Selecionada'
                                            : 'Gerenciar'
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                colspan="5"
                                class="py-12 text-center text-zinc-400"
                            >
                                <p class="text-sm font-medium">
                                    Nenhuma empresa encontrada
                                </p>
                                <p class="mt-1 text-sm">
                                    Ajuste a busca ou cadastre uma organização
                                    na plataforma.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </BackofficeLayout>
</template>
