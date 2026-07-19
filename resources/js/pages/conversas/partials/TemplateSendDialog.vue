<script setup lang="ts">
import { FileText, Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import echo from '@/echo';
import { send } from '@/routes/conversas';
import type { Message, WhatsappTemplateOption } from '../types';

type Props = {
    open: boolean;
    leadId: number;
    templates: WhatsappTemplateOption[];
};

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    sent: [message: Message];
}>();

const selectedId = ref<number | null>(null);
const fieldValues = ref<Record<string, string>>({});
const sending = ref(false);
const error = ref<string | null>(null);

const selectedTemplate = computed<WhatsappTemplateOption | null>(
    () => props.templates.find((t) => t.id === selectedId.value) ?? null,
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            selectedId.value = props.templates[0]?.id ?? null;
            resetFields();
            error.value = null;
        }
    },
);

watch(selectedId, () => {
    resetFields();
});

function resetFields(): void {
    const values: Record<string, string> = {};
    for (const field of selectedTemplate.value?.fields ?? []) {
        values[field.path] = '';
    }
    fieldValues.value = values;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function buildParameters(): Record<string, Record<string, string>> {
    const params: Record<string, Record<string, string>> = {};

    for (const [path, value] of Object.entries(fieldValues.value)) {
        const dot = path.indexOf('.');
        if (dot < 0) {
            continue;
        }
        const section = path.slice(0, dot);
        const key = path.slice(dot + 1);
        params[section] = params[section] ?? {};
        params[section][key] = value;
    }

    return params;
}

async function submit(): Promise<void> {
    if (!selectedTemplate.value || sending.value) {
        return;
    }

    const missing = selectedTemplate.value.fields.some(
        (f) => f.required && !fieldValues.value[f.path]?.trim(),
    );
    if (missing) {
        error.value = 'Preencha todos os campos obrigatórios do template.';
        return;
    }

    sending.value = true;
    error.value = null;

    try {
        const headers: Record<string, string> = {
            'X-XSRF-TOKEN': getCsrfToken(),
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };
        const socketId = echo.socketId();
        if (socketId) {
            headers['X-Socket-ID'] = socketId;
        }

        const response = await fetch(send.url({ lead: props.leadId }), {
            method: 'POST',
            headers,
            body: JSON.stringify({
                template_id: selectedTemplate.value.id,
                template_parameters: buildParameters(),
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok && data.message) {
            emit('sent', data.message as Message);
            emit('close');
        } else if (response.status === 422 && data.errors) {
            const first = Object.values(data.errors)[0];
            error.value =
                Array.isArray(first) && typeof first[0] === 'string'
                    ? first[0]
                    : 'Não foi possível enviar o template.';
        } else {
            error.value =
                typeof data.message === 'string'
                    ? data.message
                    : `Falha ao enviar (HTTP ${response.status}).`;
        }
    } catch {
        error.value = 'Sem conexão com o servidor. Tente novamente.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
        @click.self="emit('close')"
    >
        <div
            class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl bg-background shadow-xl sm:max-w-lg sm:rounded-2xl"
        >
            <header
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2">
                    <FileText class="h-4 w-4 text-primary" />
                    <h2 class="text-sm font-semibold text-foreground">
                        Enviar template
                    </h2>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Fechar"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </header>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                <div
                    v-if="!templates.length"
                    class="rounded-lg bg-muted/50 p-4 text-center text-sm text-muted-foreground"
                >
                    Nenhum template aprovado disponível para esta instância.
                </div>

                <template v-else>
                    <label class="block space-y-1">
                        <span
                            class="text-xs font-medium text-muted-foreground"
                            >Template</span
                        >
                        <select
                            v-model="selectedId"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none dark:border-sidebar-border"
                        >
                            <option
                                v-for="t in templates"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                                <template v-if="t.language">
                                    ({{ t.language }})</template
                                >
                            </option>
                        </select>
                    </label>

                    <div
                        v-if="selectedTemplate?.preview"
                        class="rounded-lg border border-sidebar-border/70 bg-muted/40 p-3 text-xs whitespace-pre-wrap text-muted-foreground dark:border-sidebar-border"
                    >
                        {{ selectedTemplate.preview }}
                    </div>

                    <div
                        v-for="field in selectedTemplate?.fields ?? []"
                        :key="field.path"
                        class="space-y-1"
                    >
                        <label class="block space-y-1">
                            <span
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ field.label
                                }}<span
                                    v-if="field.required"
                                    class="text-rose-500"
                                    >
                                    *</span
                                >
                            </span>
                            <input
                                v-model="fieldValues[field.path]"
                                type="text"
                                :placeholder="field.example ?? ''"
                                class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-sidebar-border"
                            />
                        </label>
                    </div>
                </template>

                <p
                    v-if="error"
                    class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-900/20 dark:text-rose-400"
                >
                    {{ error }}
                </p>
            </div>

            <footer
                class="flex items-center justify-end gap-2 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="!selectedTemplate || sending"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    @click="submit"
                >
                    <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                    Enviar
                </button>
            </footer>
        </div>
    </div>
</template>
