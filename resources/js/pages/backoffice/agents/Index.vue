<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type Agent = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    is_default: boolean;
    template_slug: string | null;
    effective: {
        agent_provider: string | null;
        agent_model: string | null;
        temperature: number | null;
    };
};

defineProps<{
    agents: Agent[];
}>();

const page = usePage();
const routes = useBackofficeRoutes();

const activeTenant = computed(
    () => page.props.backoffice?.active_tenant ?? null,
);
</script>

<template>
    <BackofficeLayout>
        <Head title="Agentes — Backoffice" />

        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-xl font-semibold text-zinc-100">Agentes</h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Configuração de IA por agente da empresa ativa: modelo LLM,
                    ferramentas e prompt.
                </p>
            </div>

            <div
                v-if="!activeTenant"
                class="rounded-md border border-zinc-800 bg-zinc-900/40 px-6 py-12 text-center"
            >
                <p class="text-sm font-medium text-zinc-200">
                    Nenhuma empresa ativa
                </p>
                <p class="mt-1 text-sm text-zinc-400">
                    Escolha a empresa que você quer gerenciar para ver os
                    agentes dela.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="mt-4 border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                >
                    <Link :href="routes.tenants()">Escolher empresa</Link>
                </Button>
            </div>

            <div
                v-else
                class="overflow-x-auto rounded-md border border-zinc-800"
            >
                <table class="w-full min-w-[46rem] text-sm">
                    <thead>
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Agente
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Template
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Modelo em uso
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Temp.
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="agents.length > 0">
                        <tr
                            v-for="agent in agents"
                            :key="agent.id"
                            class="border-t border-zinc-800"
                        >
                            <td class="px-4 py-3">
                                <span class="font-medium text-zinc-100">
                                    {{ agent.name }}
                                </span>
                                <span
                                    v-if="agent.is_default"
                                    class="ml-2 rounded bg-zinc-800 px-1.5 py-0.5 text-xs text-zinc-300"
                                >
                                    padrão
                                </span>
                                <span
                                    v-if="!agent.is_active"
                                    class="ml-2 rounded bg-zinc-800 px-1.5 py-0.5 text-xs text-zinc-400"
                                >
                                    inativo
                                </span>
                                <p class="mt-0.5 text-xs text-zinc-500">
                                    {{ agent.slug }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-zinc-400">
                                {{ agent.template_slug ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-300">
                                <span class="font-mono text-xs">
                                    {{ agent.effective.agent_model ?? '—' }}
                                </span>
                                <p class="mt-0.5 text-xs text-zinc-500">
                                    {{ agent.effective.agent_provider ?? '—' }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-xs text-zinc-400"
                            >
                                {{ agent.effective.temperature ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                                >
                                    <Link :href="routes.agent(agent.id)">
                                        Configurar
                                    </Link>
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
                                    Nenhum agente nesta empresa
                                </p>
                                <p class="mt-1 text-sm">
                                    Crie um agente pelo app para configurá-lo
                                    aqui.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </BackofficeLayout>
</template>
