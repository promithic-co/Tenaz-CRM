<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type EffectiveConfig = {
    agent_provider: string | null;
    agent_model: string | null;
    temperature: number | null;
};

const props = defineProps<{
    agent: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
        template_slug: string | null;
    };
    model: {
        agent_provider: string | null;
        agent_model: string | null;
        temperature: number | null;
        has_config_row: boolean;
    };
    effective: EffectiveConfig;
    providerWhitelist: string[];
    modelSuggestions: string[];
}>();

const routes = useBackofficeRoutes();

// Falls back to the resolved value so saving an agent that has no config row
// yet persists exactly what the runtime was already using.
const provider = ref<string>(
    props.model.agent_provider ?? props.effective.agent_provider ?? '',
);
</script>

<template>
    <BackofficeLayout>
        <Head :title="`${agent.name} — Backoffice`" />

        <div class="max-w-2xl">
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="mb-3 -ml-2 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
            >
                <Link :href="routes.agents()">
                    <ArrowLeft :size="14" />
                    Agentes
                </Link>
            </Button>

            <Heading
                :title="agent.name"
                :description="
                    agent.template_slug
                        ? `Template ${agent.template_slug} · ${agent.slug}`
                        : agent.slug
                "
            />

            <div class="mb-6 flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                >
                    <Link :href="routes.agentTools(agent.id)">
                        Ferramentas
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                >
                    <Link :href="routes.agentPrompt(agent.id)">Prompt</Link>
                </Button>
            </div>

            <div
                class="mb-8 rounded-md border border-zinc-800 bg-zinc-900/40 px-4 py-3 text-sm"
            >
                <p class="text-xs tracking-wide text-zinc-500 uppercase">
                    Em uso agora (após herança do template)
                </p>
                <p class="mt-1.5 font-mono text-xs text-zinc-200">
                    {{ effective.agent_provider ?? '—' }} /
                    {{ effective.agent_model ?? '—' }} · temp
                    {{ effective.temperature ?? '—' }}
                </p>
                <p
                    v-if="!model.has_config_row"
                    class="mt-2 text-xs text-zinc-400"
                >
                    Este agente ainda não tem configuração própria — os valores
                    acima vêm do template. Salvar aqui cria a configuração da
                    empresa.
                </p>
            </div>

            <Form
                :action="routes.agentModel(agent.id)"
                method="patch"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <Heading
                    variant="small"
                    title="Modelo LLM"
                    description="Provedor, modelo e temperatura usados nas respostas deste agente."
                />

                <div class="grid gap-2">
                    <Label for="agent_provider">Provedor</Label>
                    <Select v-model="provider" name="agent_provider">
                        <SelectTrigger id="agent_provider">
                            <SelectValue placeholder="Selecionar provedor" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="item in providerWhitelist"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="agent_provider"
                        :value="provider"
                    />
                    <InputError :message="errors.agent_provider" />
                </div>

                <div class="grid gap-2">
                    <Label for="agent_model">Modelo</Label>
                    <Input
                        id="agent_model"
                        name="agent_model"
                        type="text"
                        list="model-suggestions"
                        :default-value="
                            model.agent_model ?? effective.agent_model ?? ''
                        "
                        placeholder="ex: anthropic/claude-haiku-4-5"
                        required
                        maxlength="150"
                    />
                    <datalist id="model-suggestions">
                        <option
                            v-for="suggestion in modelSuggestions"
                            :key="suggestion"
                            :value="suggestion"
                        />
                    </datalist>
                    <p class="text-xs text-zinc-500">
                        Slug do modelo no provedor escolhido. A lista é sugestão
                        — qualquer slug válido é aceito.
                    </p>
                    <InputError :message="errors.agent_model" />
                </div>

                <div class="grid gap-2">
                    <Label for="temperature">Temperatura (0–2)</Label>
                    <Input
                        id="temperature"
                        name="temperature"
                        type="number"
                        min="0"
                        max="2"
                        step="0.05"
                        class="w-32"
                        :default-value="
                            model.temperature ?? effective.temperature ?? 0.4
                        "
                    />
                    <InputError :message="errors.temperature" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button :disabled="processing">Salvar modelo</Button>
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-zinc-400"
                        >
                            Salvo.
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>
    </BackofficeLayout>
</template>
