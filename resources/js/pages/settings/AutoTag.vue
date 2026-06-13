<script setup lang="ts">
import AutoTagSettingsController from '@/actions/App/Http/Controllers/Settings/AutoTagSettingsController';
import { Form, Head } from '@inertiajs/vue3';
import { Bot } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit as editAutoTag } from '@/routes/auto-tag';
import type { BreadcrumbItem } from '@/types';

type Props = {
    auto_tagging_enabled: boolean;
    status?: string | null;
};

const props = defineProps<Props>();

const enabled = ref<boolean>(props.auto_tagging_enabled);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Auto-tag IA', href: editAutoTag() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Auto-tag IA" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Auto-tag por IA"
                    description="A IA analisa a conversa e sugere tags automaticamente quando o lead muda para um status relevante."
                />

                <div
                    v-if="status"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
                >
                    {{ status }}
                </div>

                <Form
                    v-bind="AutoTagSettingsController.update.form()"
                    class="space-y-6"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="auto_tagging_enabled" :value="enabled ? '1' : '0'" />

                    <div class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <Bot class="h-5 w-5 text-primary" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">Ativar auto-tag por IA</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            Dispara automaticamente nos status: qualificado, escalado, sem crédito, desqualificado e optou sair.
                                        </p>
                                    </div>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            v-model="enabled"
                                            class="sr-only peer"
                                        />
                                        <div class="peer h-6 w-11 rounded-full bg-muted after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full dark:bg-muted"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 bg-muted/40 p-4 dark:border-sidebar-border">
                        <p class="text-xs font-medium text-muted-foreground">Como funciona</p>
                        <ul class="mt-2 space-y-1 text-xs text-muted-foreground">
                            <li>• A IA lê as últimas 20 mensagens da conversa</li>
                            <li>• Compara com as tags marcadas como "detectável por IA"</li>
                            <li>• Atribui apenas tags com alta confiança (≥ mínimo configurado)</li>
                            <li>• Tags manuais nunca são substituídas pela IA</li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{ processing ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
