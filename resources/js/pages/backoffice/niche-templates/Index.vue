<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

const routes = useBackofficeRoutes();

type NicheTemplate = {
    id: number;
    slug: string;
    name: string;
    category: string | null;
    visibility: string;
    origin_tenant_id: string | null;
    is_active: boolean;
    sort_order: number;
};

const props = defineProps<{
    templates: NicheTemplate[];
}>();

const rows = reactive(
    Object.fromEntries(
        props.templates.map((t) => [
            t.id,
            {
                is_active: t.is_active,
                visibility: t.visibility,
                sort_order: t.sort_order,
                saving: false,
            },
        ]),
    ),
);

function save(id: number) {
    const row = rows[id];
    row.saving = true;
    router.patch(
        `${routes.nicheTemplates()}/${id}`,
        {
            is_active: row.is_active,
            visibility: row.visibility,
            sort_order: row.sort_order,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                row.saving = false;
            },
        },
    );
}
</script>

<template>
    <BackofficeLayout>
        <Head title="Modelos de agente — Backoffice" />

        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-xl font-semibold text-zinc-100">
                    Modelos de agente
                </h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Curadoria do marketplace: ativar, definir visibilidade e
                    ordenar os modelos (incluindo snapshots de tenants).
                </p>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Modelo
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Origem
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Ativo
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Visibilidade
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Ordem
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                scope="col"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="templates.length > 0">
                        <tr
                            v-for="t in templates"
                            :key="t.id"
                            class="border-t transition-colors hover:bg-muted/50"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ t.name }}</div>
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ t.slug }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ t.origin_tenant_id ?? 'sistema' }}
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    v-model="rows[t.id].is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-input"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    v-model="rows[t.id].visibility"
                                    class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                                >
                                    <option value="system">Sistema</option>
                                    <option value="tenant">Privado</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    v-model.number="rows[t.id].sort_order"
                                    type="number"
                                    min="0"
                                    max="9999"
                                    class="w-20 rounded-md border border-input bg-background px-2 py-1 text-sm"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="rows[t.id].saving"
                                    @click="save(t.id)"
                                >
                                    Salvar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                colspan="6"
                                class="py-12 text-center text-muted-foreground"
                            >
                                <p class="text-sm font-medium">
                                    Nenhum modelo no registro
                                </p>
                                <p class="mt-1 text-sm">
                                    Execute o seeder de modelos ou gere um
                                    snapshot a partir de um agente.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </BackofficeLayout>
</template>
