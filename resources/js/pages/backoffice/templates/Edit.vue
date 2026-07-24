<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
import { Separator } from '@/components/ui/separator';
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

const props = defineProps<{
    template: Template;
    providerWhitelist: string[];
}>();

const agentProvider = ref<string>(props.template.agent_provider);
const transcriptionProvider = ref<string>(
    props.template.transcription_provider,
);
const visionProvider = ref<string>(props.template.vision_provider);

const routes = useBackofficeRoutes();
</script>

<template>
    <BackofficeLayout>
        <Head :title="'Editar Template: ' + template.template_slug" />

        <div class="max-w-2xl">
            <Heading
                :title="'Editar Template: ' + template.template_slug"
                description="Altere provedor, modelo e parâmetros LLM para este template."
            />

            <Form
                :action="`${routes.templates()}/${template.template_slug}`"
                method="patch"
                class="space-y-8"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <input
                    type="hidden"
                    name="template_slug"
                    :value="template.template_slug"
                />

                <!-- Section 1: Chat -->
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        title="Chat"
                        description="Provedor e modelo usado para respostas de conversação."
                    />

                    <div class="grid gap-2">
                        <Label for="agent_provider">Provedor</Label>
                        <Select v-model="agentProvider" name="agent_provider">
                            <SelectTrigger id="agent_provider">
                                <SelectValue
                                    placeholder="Selecionar provedor"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="provider in providerWhitelist"
                                    :key="provider"
                                    :value="provider"
                                >
                                    {{ provider }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="agent_provider"
                            :value="agentProvider"
                        />
                        <InputError :message="errors.agent_provider" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="agent_model">Modelo</Label>
                        <Input
                            id="agent_model"
                            name="agent_model"
                            type="text"
                            :default-value="template.agent_model"
                            placeholder="ex: openai/gpt-4o"
                            required
                            maxlength="150"
                        />
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
                            step="0.1"
                            class="w-32"
                            :default-value="template.temperature"
                        />
                        <InputError :message="errors.temperature" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="max_tokens">Máx. tokens (1–65535)</Label>
                        <Input
                            id="max_tokens"
                            name="max_tokens"
                            type="number"
                            min="1"
                            max="65535"
                            step="1"
                            class="w-40"
                            :default-value="template.max_tokens"
                        />
                        <InputError :message="errors.max_tokens" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="max_conversation_messages"
                            >Máx. mensagens (1–200)</Label
                        >
                        <Input
                            id="max_conversation_messages"
                            name="max_conversation_messages"
                            type="number"
                            min="1"
                            max="200"
                            step="1"
                            class="w-32"
                            :default-value="template.max_conversation_messages"
                        />
                        <InputError
                            :message="errors.max_conversation_messages"
                        />
                    </div>
                </div>

                <Separator />

                <!-- Section 2: Transcrição -->
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        title="Transcrição"
                        description="Provedor e modelo para transcrição de áudio."
                    />

                    <div class="grid gap-2">
                        <Label for="transcription_provider">Provedor</Label>
                        <Select
                            v-model="transcriptionProvider"
                            name="transcription_provider"
                        >
                            <SelectTrigger id="transcription_provider">
                                <SelectValue
                                    placeholder="Selecionar provedor"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="provider in providerWhitelist"
                                    :key="provider"
                                    :value="provider"
                                >
                                    {{ provider }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="transcription_provider"
                            :value="transcriptionProvider"
                        />
                        <InputError :message="errors.transcription_provider" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="transcription_model">Modelo</Label>
                        <Input
                            id="transcription_model"
                            name="transcription_model"
                            type="text"
                            :default-value="template.transcription_model"
                            placeholder="ex: openai/whisper-1"
                            required
                            maxlength="150"
                        />
                        <InputError :message="errors.transcription_model" />
                    </div>
                </div>

                <Separator />

                <!-- Section 3: Visão -->
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        title="Visão"
                        description="Provedor e modelo para análise de imagens."
                    />

                    <div class="grid gap-2">
                        <Label for="vision_provider">Provedor</Label>
                        <Select v-model="visionProvider" name="vision_provider">
                            <SelectTrigger id="vision_provider">
                                <SelectValue
                                    placeholder="Selecionar provedor"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="provider in providerWhitelist"
                                    :key="provider"
                                    :value="provider"
                                >
                                    {{ provider }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="vision_provider"
                            :value="visionProvider"
                        />
                        <InputError :message="errors.vision_provider" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="vision_model">Modelo</Label>
                        <Input
                            id="vision_model"
                            name="vision_model"
                            type="text"
                            :default-value="template.vision_model"
                            placeholder="ex: openai/gpt-4o"
                            required
                            maxlength="150"
                        />
                        <InputError :message="errors.vision_model" />
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <Button :disabled="processing">Salvar alterações</Button>
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Salvo.
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>
    </BackofficeLayout>
</template>
