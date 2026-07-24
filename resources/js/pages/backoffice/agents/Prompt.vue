<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowDown,
    ArrowUp,
    Lock,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBackofficeRoutes } from '@/composables/useBackofficeRoutes';
import BackofficeLayout from '@/layouts/BackofficeLayout.vue';

type Section = {
    title: string;
    content: string;
};

type HistoryEntry = {
    version: number;
    editor_mode: string | null;
    is_active: boolean;
    created_at: string | null;
};

const props = defineProps<{
    agent: { id: number; name: string; slug: string };
    editor: {
        mode: 'structured' | 'raw';
        sections: Section[];
        raw_content: string;
        is_override: boolean;
    };
    template: { id: number; version: number; updated_at: string | null } | null;
    head: string;
    tail: Section[];
    toolsRestricted: boolean;
    history: HistoryEntry[];
}>();

const routes = useBackofficeRoutes();

const form = useForm({
    editor_mode: props.editor.mode,
    sections: props.editor.sections.map((section) => ({ ...section })),
    raw_content: props.editor.raw_content,
});

const isRaw = computed(() => form.editor_mode === 'raw');

// Vue 3 does not interpolate mustaches inside plain attributes, so the example
// placeholders are bound from here instead of written in the template.
const rawPlaceholder =
    'Use {{agent_name}}, {{company_name}}, {{max_chars}}, {{agent_greeting}}, {{required_docs}}, {{extra_rules}}...';

const fieldErrors = computed<Record<string, string>>(
    () => form.errors as unknown as Record<string, string>,
);

function addSection(): void {
    form.sections = [...form.sections, { title: '', content: '' }];
}

function removeSection(index: number): void {
    form.sections = form.sections.filter((_, position) => position !== index);
}

function moveSection(index: number, offset: number): void {
    const target = index + offset;

    if (target < 0 || target >= form.sections.length) {
        return;
    }

    const next = [...form.sections];
    [next[index], next[target]] = [next[target], next[index]];
    form.sections = next;
}

function save(): void {
    form.transform((data) => ({
        editor_mode: data.editor_mode,
        // Only the active mode is sent — the other one would fail validation
        // with stale content the operator never looked at.
        ...(data.editor_mode === 'raw'
            ? { raw_content: data.raw_content }
            : { sections: data.sections }),
    })).patch(routes.agentPrompt(props.agent.id), {
        preserveScroll: true,
    });
}

function resetToDefault(): void {
    router.delete(routes.agentPrompt(props.agent.id), {
        preserveScroll: true,
    });
}

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <BackofficeLayout>
        <Head :title="`Prompt de ${agent.name} — Backoffice`" />

        <div class="max-w-3xl">
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
                title="Prompt"
                description="Miolo do prompt deste agente. A abertura e as seções de segurança são da plataforma e vão junto em toda versão salva."
            />

            <div
                class="mb-6 flex flex-wrap items-center gap-3 rounded-md border border-zinc-800 bg-zinc-900/40 px-4 py-3 text-sm"
            >
                <span v-if="template" class="text-amber-300">
                    Prompt personalizado — versão {{ template.version }}
                </span>
                <span v-else class="text-zinc-300">
                    Prompt padrão (composto pela plataforma)
                </span>
                <span class="text-xs text-zinc-500">
                    {{
                        template
                            ? `Salvo em ${formatDate(template.updated_at)}`
                            : 'Salvar aqui cria a primeira versão personalizada.'
                    }}
                </span>
                <Button
                    v-if="editor.is_override"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="ml-auto border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                    @click="resetToDefault"
                >
                    Voltar ao prompt padrão
                </Button>
            </div>

            <div class="mb-6 flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="isRaw ? 'outline' : 'default'"
                    :class="
                        isRaw
                            ? 'border-zinc-700 bg-transparent text-zinc-300 hover:bg-zinc-800'
                            : ''
                    "
                    @click="form.editor_mode = 'structured'"
                >
                    Estruturado
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="isRaw ? 'default' : 'outline'"
                    :class="
                        isRaw
                            ? ''
                            : 'border-zinc-700 bg-transparent text-zinc-300 hover:bg-zinc-800'
                    "
                    @click="form.editor_mode = 'raw'"
                >
                    Texto cru
                </Button>
            </div>

            <details
                class="mb-6 rounded-md border border-zinc-800 bg-zinc-900/40 px-4 py-3"
            >
                <summary
                    class="flex cursor-pointer items-center gap-2 text-sm text-zinc-300"
                >
                    <Lock :size="13" class="text-zinc-500" />
                    Abertura da plataforma (fixa)
                </summary>
                <pre
                    class="mt-3 overflow-x-auto font-mono text-xs whitespace-pre-wrap text-zinc-400"
                    >{{ head }}</pre
                >
            </details>

            <!-- Structured editor -->
            <div v-if="!isRaw" class="space-y-4">
                <div
                    v-for="(section, index) in form.sections"
                    :key="index"
                    class="rounded-md border border-zinc-800 bg-zinc-900/30 p-4"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <span class="text-xs text-zinc-500">
                            Seção {{ index + 1 }}
                        </span>
                        <div class="ml-auto flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                                :disabled="index === 0"
                                @click="moveSection(index, -1)"
                            >
                                <ArrowUp :size="14" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                                :disabled="index === form.sections.length - 1"
                                @click="moveSection(index, 1)"
                            >
                                <ArrowDown :size="14" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-zinc-400 hover:bg-zinc-800 hover:text-red-300"
                                @click="removeSection(index)"
                            >
                                <Trash2 :size="14" />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`section-title-${index}`">Título</Label>
                        <Input
                            :id="`section-title-${index}`"
                            v-model="section.title"
                            type="text"
                            maxlength="120"
                            placeholder="ex: FLUXO DE ATENDIMENTO"
                        />
                        <InputError
                            :message="fieldErrors[`sections.${index}.title`]"
                        />
                    </div>

                    <div class="mt-3 grid gap-2">
                        <Label :for="`section-content-${index}`"
                            >Conteúdo</Label
                        >
                        <textarea
                            :id="`section-content-${index}`"
                            v-model="section.content"
                            rows="8"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs text-zinc-100 outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError
                            :message="fieldErrors[`sections.${index}.content`]"
                        />
                    </div>
                </div>

                <InputError :message="form.errors.sections" />

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-zinc-700 bg-transparent text-zinc-200 hover:bg-zinc-800 hover:text-zinc-100"
                    @click="addSection"
                >
                    <Plus :size="14" />
                    Adicionar seção
                </Button>
            </div>

            <!-- Raw editor -->
            <div v-else class="space-y-3">
                <div
                    class="rounded-md border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-200"
                >
                    No modo cru você escreve o miolo inteiro. A abertura e as
                    seções FERRAMENTAS, SEGURANÇA e ENCERRAMENTO continuam sendo
                    anexadas pela plataforma — não precisa (nem adianta)
                    reescrevê-las aqui.
                </div>
                <Label for="raw_content">Prompt</Label>
                <textarea
                    id="raw_content"
                    v-model="form.raw_content"
                    rows="24"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs text-zinc-100 outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :placeholder="rawPlaceholder"
                />
                <InputError :message="form.errors.raw_content" />
            </div>

            <div class="mt-6 flex items-center gap-4">
                <Button :disabled="form.processing" @click="save">
                    Salvar nova versão
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

            <details
                class="mt-8 rounded-md border border-zinc-800 bg-zinc-900/40 px-4 py-3"
            >
                <summary
                    class="flex cursor-pointer items-center gap-2 text-sm text-zinc-300"
                >
                    <Lock :size="13" class="text-zinc-500" />
                    Seções de segurança da plataforma (fixas)
                </summary>
                <p v-if="toolsRestricted" class="mt-2 text-xs text-zinc-500">
                    O texto abaixo já reflete as ferramentas habilitadas deste
                    agente. Se você mudar as ferramentas, salve o prompt de novo
                    para atualizar estas seções.
                </p>
                <div
                    v-for="section in tail"
                    :key="section.title"
                    class="mt-4 border-t border-zinc-800 pt-3 first:border-0 first:pt-0"
                >
                    <p class="text-xs font-medium text-zinc-300">
                        {{ section.title }}
                    </p>
                    <pre
                        class="mt-1.5 overflow-x-auto font-mono text-xs whitespace-pre-wrap text-zinc-500"
                        >{{ section.content }}</pre
                    >
                </div>
            </details>

            <div v-if="history.length > 0" class="mt-8">
                <p class="text-xs tracking-wide text-zinc-500 uppercase">
                    Versões
                </p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li
                        v-for="entry in history"
                        :key="entry.version"
                        class="flex items-center gap-3 text-zinc-400"
                    >
                        <span class="font-mono text-xs">
                            v{{ entry.version }}
                        </span>
                        <span class="text-xs">
                            {{
                                entry.editor_mode === 'raw'
                                    ? 'texto cru'
                                    : 'estruturado'
                            }}
                        </span>
                        <span class="text-xs text-zinc-500">
                            {{ formatDate(entry.created_at) }}
                        </span>
                        <span
                            v-if="entry.is_active"
                            class="rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-300"
                        >
                            ativa
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </BackofficeLayout>
</template>
