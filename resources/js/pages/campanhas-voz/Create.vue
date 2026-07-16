<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import VoiceCampaignController from '@/actions/App/Http/Controllers/VoiceCampaignController';
import VoicePreviewController from '@/actions/App/Http/Controllers/VoicePreviewController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type VoiceInstance = {
    id: number;
    name: string;
    display_name: string | null;
};

type ContactList = {
    id: number;
    name: string;
    entries_count: number;
};

type Props = {
    voiceInstances: VoiceInstance[];
    contactLists: ContactList[];
    twilioPhoneNumber: string | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campanhas de Voz', href: '/campanhas-voz' },
    { title: 'Nova Campanha', href: '/campanhas-voz/criar' },
];

// ─── Available voices ────────────────────────────────────────────────────────

const voiceOptions = [
    { value: 'Google.pt-BR-Standard-A', label: 'Google — Feminino (Padrão)' },
    { value: 'Google.pt-BR-Standard-B', label: 'Google — Masculino' },
    { value: 'Google.pt-BR-Standard-C', label: 'Google — Feminino C' },
    {
        value: 'Polly.Camila-Neural',
        label: '⭐ Camila Neural (Feminino) — Amazon Polly',
    },
    {
        value: 'Polly.Thiago-Neural',
        label: '⭐ Thiago Neural (Masculino) — Amazon Polly',
    },
    {
        value: 'Polly.Vitoria-Neural',
        label: '⭐ Vitória Neural (Feminino) — Amazon Polly',
    },
];

// ─── Available DTMF actions ───────────────────────────────────────────────────

const dtmfActionOptions = [
    {
        value: 'interested',
        label: 'Tenho interesse — notificar agente via WhatsApp',
    },
    { value: 'optout', label: 'Não quero mais receber ligações (opt-out)' },
    { value: 'callback', label: 'Me ligue mais tarde' },
    { value: 'hangup', label: 'Encerrar chamada silenciosamente' },
];

// ─── Form ────────────────────────────────────────────────────────────────────

type DtmfEntry = { digit: string; action: string; label: string };

const form = useForm({
    name: '',
    contact_list_id: '' as string | number,
    voice_instance_id: '' as string | number,
    greeting_template: '',
    tts_voice: 'Polly.Camila-Neural',
    post_call_message: '',
    delay_between_calls_ms: 3000,
    dtmf_actions: {} as Record<string, { action: string; label: string }>,
});

// ─── DTMF rows (local UI state, synced to form.dtmf_actions) ─────────────────

const dtmfRows = ref<DtmfEntry[]>([
    { digit: '1', action: 'interested', label: '' },
    { digit: '2', action: 'optout', label: '' },
]);

function syncDtmfToForm() {
    const map: Record<string, { action: string; label: string }> = {};
    for (const row of dtmfRows.value) {
        if (row.digit)
            map[row.digit] = { action: row.action, label: row.label };
    }
    form.dtmf_actions = map;
}

function addDtmfRow() {
    // Pick next available digit
    const used = new Set(dtmfRows.value.map((r) => r.digit));
    const next =
        ['1', '2', '3', '4', '5', '6', '7', '8', '9'].find(
            (d) => !used.has(d),
        ) ?? '';
    dtmfRows.value.push({ digit: next, action: 'callback', label: '' });
    syncDtmfToForm();
}

function removeDtmfRow(i: number) {
    dtmfRows.value.splice(i, 1);
    syncDtmfToForm();
}

function onActionChange(_i: number) {
    syncDtmfToForm();
}

// ─── Audio preview ───────────────────────────────────────────────────────────

const audioUrl = ref<string | null>(null);
const previewBusy = ref(false);
const previewError = ref<string | null>(null);

async function generatePreview() {
    const text = form.greeting_template.trim();
    if (!text) {
        previewError.value = 'Digite o texto da URA antes de gerar o preview.';
        return;
    }

    previewBusy.value = true;
    previewError.value = null;
    audioUrl.value = null;

    try {
        const response = await fetch(VoicePreviewController.preview.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content ?? '',
                Accept: 'audio/mpeg, application/json',
            },
            body: JSON.stringify({ text, voice: form.tts_voice }),
        });

        if (!response.ok) {
            const json = await response.json().catch(() => ({}));
            previewError.value =
                (json as { error?: string }).error ?? 'Falha ao gerar áudio.';
            return;
        }

        const blob = await response.blob();
        audioUrl.value = URL.createObjectURL(blob);
    } catch {
        previewError.value = 'Erro de conexão ao gerar preview.';
    } finally {
        previewBusy.value = false;
    }
}

// ─── Submit ───────────────────────────────────────────────────────────────────

function submitForm(): void {
    syncDtmfToForm();
    form.post(VoiceCampaignController.store().url);
}

const dtmfIsValid = computed(
    () =>
        dtmfRows.value.length > 0 &&
        dtmfRows.value.every((r) => r.digit && r.action),
);
</script>

<template>
    <Head title="Nova Campanha de Voz" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-3 sm:p-4">
            <div class="mx-auto max-w-2xl">
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <!-- Header -->
                    <div
                        class="border-b border-sidebar-border/70 px-4 py-4 sm:px-6 dark:border-sidebar-border"
                    >
                        <h1 class="text-base font-semibold text-foreground">
                            Nova Campanha de Voz
                        </h1>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Configure uma campanha de ligações automáticas via
                            Twilio.
                        </p>
                    </div>

                    <div class="flex flex-col gap-5 p-4 sm:p-6">
                        <!-- Campaign name -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Nome da Campanha
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Ex: Campanha INSS Janeiro 2026"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Contact list -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Lista de Contatos
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.contact_list_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="">Selecione uma lista...</option>
                                <option
                                    v-for="list in contactLists"
                                    :key="list.id"
                                    :value="list.id"
                                >
                                    {{ list.name }} ({{ list.entries_count }}
                                    contatos)
                                </option>
                            </select>
                            <p
                                v-if="form.errors.contact_list_id"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.contact_list_id }}
                            </p>
                        </div>

                        <!-- Outbound phone info -->
                        <div
                            v-if="props.twilioPhoneNumber"
                            class="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
                        >
                            Número de saída:
                            <span class="font-mono font-medium">{{
                                props.twilioPhoneNumber
                            }}</span>
                        </div>

                        <!-- Voice instance -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                            >
                                Instância de Voz
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.voice_instance_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="">
                                    Selecione uma instância ativa...
                                </option>
                                <option
                                    v-for="instance in voiceInstances"
                                    :key="instance.id"
                                    :value="instance.id"
                                >
                                    {{ instance.display_name ?? instance.name }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.voice_instance_id"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.voice_instance_id }}
                            </p>
                            <p
                                v-if="voiceInstances.length === 0"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                Nenhuma instância de voz ativa. Crie uma em
                                <a href="/voz" class="underline"
                                    >Instâncias de Voz</a
                                >.
                            </p>
                        </div>

                        <!-- Greeting template -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                                >Texto da URA (o robô irá falar)</label
                            >
                            <textarea
                                v-model="form.greeting_template"
                                rows="4"
                                placeholder="Olá {nome}, aqui é a Tenaz CRM, assistente virtual. Estou ligando sobre uma oportunidade especial para você..."
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Use
                                <span
                                    class="rounded bg-muted px-1 font-mono text-xs"
                                    >{nome}</span
                                >,
                                <span
                                    class="rounded bg-muted px-1 font-mono text-xs"
                                    >{valor}</span
                                >
                                e outros campos do contato.
                            </p>
                            <p
                                v-if="form.errors.greeting_template"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.greeting_template }}
                            </p>
                        </div>

                        <!-- TTS Voice selector + Audio preview -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                                >Voz do Robô</label
                            >
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <select
                                    v-model="form.tts_voice"
                                    class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                >
                                    <option
                                        v-for="v in voiceOptions"
                                        :key="v.value"
                                        :value="v.value"
                                    >
                                        {{ v.label }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="previewBusy"
                                    class="flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                                    @click="generatePreview"
                                >
                                    <svg
                                        v-if="!previewBusy"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 text-primary"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                    >
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-4 w-4 animate-spin text-muted-foreground"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8v8H4z"
                                        />
                                    </svg>
                                    {{
                                        previewBusy
                                            ? 'Gerando...'
                                            : 'Ouvir prévia'
                                    }}
                                </button>
                            </div>

                            <!-- Audio player -->
                            <div v-if="audioUrl" class="mt-2">
                                <audio
                                    :src="audioUrl"
                                    controls
                                    autoplay
                                    class="w-full rounded-md"
                                />
                            </div>
                            <p
                                v-if="previewError"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ previewError }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                ⭐ Vozes Neural (Amazon Polly) têm qualidade
                                superior. O preview usa a mesma voz que seus
                                clientes ouvirão.
                            </p>
                            <p
                                v-if="form.errors.tts_voice"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.tts_voice }}
                            </p>
                        </div>

                        <!-- DTMF Actions -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label
                                    class="text-sm font-medium text-foreground"
                                >
                                    Ações do Teclado (DTMF)
                                    <span class="text-red-500">*</span>
                                </label>
                                <button
                                    v-if="dtmfRows.length < 9"
                                    type="button"
                                    class="text-xs text-primary hover:underline"
                                    @click="addDtmfRow"
                                >
                                    + Adicionar ação
                                </button>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div
                                    v-for="(row, i) in dtmfRows"
                                    :key="i"
                                    class="flex items-center gap-2"
                                >
                                    <!-- Digit -->
                                    <div
                                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md border border-input bg-muted text-sm font-bold text-foreground"
                                    >
                                        {{ row.digit }}
                                    </div>

                                    <!-- Action select -->
                                    <select
                                        v-model="row.action"
                                        class="flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                                        @change="onActionChange(i)"
                                    >
                                        <option
                                            v-for="opt in dtmfActionOptions"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </option>
                                    </select>

                                    <!-- Custom label -->
                                    <input
                                        v-model="row.label"
                                        type="text"
                                        placeholder="Legenda personalizada"
                                        class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none sm:w-40"
                                        @input="syncDtmfToForm"
                                    />

                                    <!-- Remove -->
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-red-500"
                                        @click="removeDtmfRow(i)"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path d="M18 6L6 18M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="dtmfRows.length === 0"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                ⚠ Adicione pelo menos uma ação DTMF. A campanha
                                não pode ser iniciada sem elas.
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                O robô lerá as opções em voz alta após o texto
                                da URA. O cliente pressiona o dígito
                                correspondente.
                            </p>
                            <p
                                v-if="form.errors['dtmf_actions']"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors['dtmf_actions'] }}
                            </p>
                        </div>

                        <!-- Post-call message -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                                >Mensagem pós-ligação (WhatsApp)</label
                            >
                            <textarea
                                v-model="form.post_call_message"
                                rows="3"
                                placeholder="Olá {nome}, conforme falamos agora, aqui está mais informações sobre..."
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Mensagem enviada via WhatsApp após o contato
                                expressar interesse na ligação.
                            </p>
                            <p
                                v-if="form.errors.post_call_message"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.post_call_message }}
                            </p>
                        </div>

                        <!-- Delay between calls -->
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-foreground"
                                >Atraso entre ligações (ms)</label
                            >
                            <input
                                v-model.number="form.delay_between_calls_ms"
                                type="number"
                                min="1000"
                                max="60000"
                                step="500"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Tempo de espera entre cada ligação. Mínimo:
                                1000ms (1s). Atual:
                                {{
                                    (
                                        form.delay_between_calls_ms / 1000
                                    ).toFixed(1)
                                }}s.
                            </p>
                            <p
                                v-if="form.errors.delay_between_calls_ms"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.delay_between_calls_ms }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-sidebar-border"
                    >
                        <a
                            href="/campanhas-voz"
                            class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </a>
                        <button
                            type="button"
                            :disabled="form.processing || !dtmfIsValid"
                            :title="
                                !dtmfIsValid
                                    ? 'Configure pelo menos uma ação DTMF antes de criar.'
                                    : undefined
                            "
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitForm"
                        >
                            {{
                                form.processing
                                    ? 'Criando...'
                                    : 'Criar Campanha'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
