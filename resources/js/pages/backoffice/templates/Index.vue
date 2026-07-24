<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type Template = {
    id: number;
    template_slug: string;
    agent_provider: string;
    agent_model: string;
    transcription_provider: string;
    transcription_model: string;
    vision_provider: string;
    vision_model: string;
    temperature: number;
    max_tokens: number;
    max_conversation_messages: number;
};

defineProps<{
    templates: Template[];
}>();

const routes = useBackofficeRoutes();
</script>

<template>
    <BackofficeLayout>
        <Head title="Templates LLM — Backoffice" />

        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-xl font-semibold text-zinc-100">
                    Templates LLM
                </h1>
                <p class="mt-1 text-sm text-zinc-400">
                    Configuração global de provedor e modelo por template de
                    agente. Vale para todas as empresas que não sobrescrevem no
                    nível do agente.
                </p>
            </div>

            <div class="overflow-x-auto rounded-md border border-zinc-800">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr>
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
                                Provedor (chat)
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Modelo (chat)
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Temp.
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
                                scope="col"
                            >
                                Tokens
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase"
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
                            class="border-t border-zinc-800 transition-colors hover:bg-zinc-900/60"
                        >
                            <td class="px-4 py-3 font-medium text-zinc-100">
                                {{ t.template_slug }}
                            </td>
                            <td class="px-4 py-3 text-zinc-400">
                                {{ t.agent_provider }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-zinc-300"
                            >
                                {{ t.agent_model }}
                            </td>
                            <td class="px-4 py-3 text-zinc-400">
                                {{ t.temperature }}
                            </td>
                            <td class="px-4 py-3 text-zinc-400">
                                {{ t.max_tokens }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100"
                                >
                                    <Link
                                        :href="`${routes.templates()}/${t.template_slug}/edit`"
                                        >Editar</Link
                                    >
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                colspan="6"
                                class="py-12 text-center text-zinc-400"
                            >
                                <p class="text-sm font-medium">
                                    Nenhum template configurado
                                </p>
                                <p class="mt-1 text-sm">
                                    Execute o seeder de templates para criar as
                                    configurações padrão.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </BackofficeLayout>
</template>
