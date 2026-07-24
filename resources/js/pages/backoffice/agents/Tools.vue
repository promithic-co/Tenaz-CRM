<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type Capability = {
    value: string;
    label: string;
    description: string;
};

type Webhook = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_shared: boolean;
};

const props = defineProps<{
    agent: { id: number; name: string; slug: string };
    capabilities: Capability[];
    enabled: string[];
    restricted: boolean;
    webhooks: Webhook[];
}>();

const routes = useBackofficeRoutes();

const form = useForm({
    capabilities: [...props.enabled],
    webhooks: props.webhooks.map((webhook) => ({
        id: webhook.id,
        is_active: webhook.is_active,
    })),
});

function isEnabled(value: string): boolean {
    return form.capabilities.includes(value);
}

function toggleCapability(value: string, checked: boolean): void {
    form.capabilities = checked
        ? [...form.capabilities, value]
        : form.capabilities.filter((capability) => capability !== value);
}

function toggleWebhook(id: number, checked: boolean): void {
    form.webhooks = form.webhooks.map((webhook) =>
        webhook.id === id ? { ...webhook, is_active: checked } : webhook,
    );
}

function isWebhookActive(id: number): boolean {
    return form.webhooks.some(
        (webhook) => webhook.id === id && webhook.is_active,
    );
}

function submit(): void {
    form.put(routes.agentTools(props.agent.id), { preserveScroll: true });
}
</script>

<template>
    <BackofficeLayout>
        <Head :title="`Ferramentas · ${agent.name} — Backoffice`" />

        <div class="max-w-2xl">
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="mb-3 -ml-2 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
            >
                <Link :href="routes.agent(agent.id)">
                    <ArrowLeft :size="14" />
                    {{ agent.name }}
                </Link>
            </Button>

            <Heading
                title="Ferramentas"
                :description="`O que este agente pode acionar durante o atendimento · ${agent.slug}`"
            />

            <form class="space-y-8" @submit.prevent="submit">
                <section class="space-y-3">
                    <Heading
                        variant="small"
                        title="Ferramentas nativas"
                        description="Desligar uma ferramenta a remove do toolset já no próximo turno do agente."
                    />

                    <p
                        v-if="!restricted"
                        class="rounded-md border border-zinc-800 bg-zinc-900/40 px-4 py-3 text-xs text-zinc-400"
                    >
                        Este agente ainda usa o conjunto padrão (tudo ligado).
                        Ao salvar, a seleção abaixo passa a valer como lista
                        fixa — ferramentas novas da plataforma não entram
                        sozinhas.
                    </p>

                    <ul class="space-y-2">
                        <li
                            v-for="capability in capabilities"
                            :key="capability.value"
                            class="flex items-start gap-3 rounded-md border border-zinc-800 px-4 py-3"
                        >
                            <Checkbox
                                :id="`capability-${capability.value}`"
                                :model-value="isEnabled(capability.value)"
                                @update:model-value="
                                    (checked) =>
                                        toggleCapability(
                                            capability.value,
                                            checked === true,
                                        )
                                "
                            />
                            <label
                                :for="`capability-${capability.value}`"
                                class="grid gap-1"
                            >
                                <span class="text-sm text-zinc-100">
                                    {{ capability.label }}
                                </span>
                                <span class="text-xs text-zinc-400">
                                    {{ capability.description }}
                                </span>
                                <code class="font-mono text-xs text-zinc-600">
                                    {{ capability.value }}
                                </code>
                            </label>
                        </li>
                    </ul>
                </section>

                <section class="space-y-3">
                    <Heading
                        variant="small"
                        title="Webhooks"
                        description="Ferramentas criadas pela empresa. Ligar ou desligar aqui equivale ao is_active da definição."
                    />

                    <ul v-if="webhooks.length > 0" class="space-y-2">
                        <li
                            v-for="webhook in webhooks"
                            :key="webhook.id"
                            class="flex items-start gap-3 rounded-md border border-zinc-800 px-4 py-3"
                        >
                            <Checkbox
                                :id="`webhook-${webhook.id}`"
                                :model-value="isWebhookActive(webhook.id)"
                                @update:model-value="
                                    (checked) =>
                                        toggleWebhook(
                                            webhook.id,
                                            checked === true,
                                        )
                                "
                            />
                            <label
                                :for="`webhook-${webhook.id}`"
                                class="grid gap-1"
                            >
                                <span
                                    class="flex items-center gap-2 text-sm text-zinc-100"
                                >
                                    {{ webhook.name }}
                                    <span
                                        v-if="webhook.is_shared"
                                        class="rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-300"
                                    >
                                        vale para todos os agentes
                                    </span>
                                </span>
                                <span
                                    v-if="webhook.description"
                                    class="text-xs text-zinc-400"
                                >
                                    {{ webhook.description }}
                                </span>
                                <code class="font-mono text-xs text-zinc-600">
                                    {{ webhook.slug }}
                                </code>
                            </label>
                        </li>
                    </ul>

                    <p
                        v-else
                        class="rounded-md border border-zinc-800 px-4 py-6 text-center text-sm text-zinc-400"
                    >
                        Nenhum webhook cadastrado para esta empresa.
                    </p>
                </section>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        Salvar ferramentas
                    </Button>
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="form.recentlySuccessful"
                            class="text-sm text-zinc-400"
                        >
                            Salvo.
                        </p>
                    </Transition>
                </div>
            </form>
        </div>
    </BackofficeLayout>
</template>
