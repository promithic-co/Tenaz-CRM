<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { edit } from '@/actions/App/Http/Controllers/Backoffice/BackofficeTemplateController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

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

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Backoffice', href: '/backoffice' },
    { title: 'Templates LLM', href: '/backoffice/templates' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Templates LLM" />

        <div class="px-3 py-4 sm:px-4 sm:py-6">
            <div class="flex max-w-5xl flex-col space-y-12">
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        title="Templates LLM"
                        description="Configuração de provedor e modelo por template de agente."
                    />
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/backoffice/modelos">Modelos de agente</Link>
                    </Button>
                </div>

                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Template
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Provedor (chat)
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Modelo (chat)
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Temp.
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Tokens
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
                                <td class="px-4 py-3 font-medium">
                                    {{ t.template_slug }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ t.agent_provider }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ t.agent_model }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ t.temperature }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ t.max_tokens }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link
                                            :href="
                                                edit({
                                                    template_slug:
                                                        t.template_slug,
                                                }).url
                                            "
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
                                    class="py-12 text-center text-muted-foreground"
                                >
                                    <p class="text-sm font-medium">
                                        Nenhum template configurado
                                    </p>
                                    <p class="mt-1 text-sm">
                                        Execute o seeder de templates para criar
                                        as configurações padrão.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
