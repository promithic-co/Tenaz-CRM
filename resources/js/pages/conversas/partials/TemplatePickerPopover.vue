<script setup lang="ts">
import { ChevronLeft, Loader2, RefreshCw, Search } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import echo from '@/echo';
import { send } from '@/routes/conversas';
import { sync as syncTemplatesRoute } from '@/routes/conversas/templates';
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

// Search only earns its space once scanning the list stops being instant.
const SEARCH_THRESHOLD = 8;

const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const query = ref('');
const selectedId = ref<number | null>(null);
const fieldValues = ref<Record<string, string>>({});
const sending = ref(false);
const syncing = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);

const showSearch = computed<boolean>(
    () => props.templates.length > SEARCH_THRESHOLD,
);

const visibleTemplates = computed<WhatsappTemplateOption[]>(() => {
    const term = query.value.trim().toLowerCase();
    if (!term) {
        return props.templates;
    }
    return props.templates.filter(
        (t) =>
            t.name.toLowerCase().includes(term) ||
            (t.category ?? '').toLowerCase().includes(term),
    );
});

const selectedTemplate = computed<WhatsappTemplateOption | null>(
    () => props.templates.find((t) => t.id === selectedId.value) ?? null,
);

// The panel already resolved everything it could from the lead; only what is left over is
// the operator's to type. Variables come from the template, never from a form the operator
// has to fill in by hand for every send.
const pendingFields = computed(
    () => selectedTemplate.value?.fields.filter((f) => !f.resolved) ?? [],
);

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }
        query.value = '';
        selectedId.value = null;
        error.value = null;
        notice.value = null;
        document.addEventListener('keydown', onKeydown);
        document.addEventListener('mousedown', onPointerDown);
        await nextTick();
        searchInput.value?.focus();
    },
);

watch(selectedId, () => {
    const values: Record<string, string> = {};
    for (const field of pendingFields.value) {
        values[field.path] = '';
    }
    fieldValues.value = values;
    error.value = null;
});

onBeforeUnmount(() => {
    detachListeners();
});

function detachListeners(): void {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('mousedown', onPointerDown);
}

function close(): void {
    detachListeners();
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') {
        return;
    }
    // Esc backs out one level at a time: preview first, then the popover.
    if (selectedId.value !== null) {
        selectedId.value = null;
        return;
    }
    close();
}

function onPointerDown(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    // The trigger owns the toggle; ignoring it here keeps a click on the button from closing
    // the popover on mousedown only to have the click reopen it.
    if (target?.closest('[data-template-picker-trigger]')) {
        return;
    }
    if (!root.value?.contains(target)) {
        close();
    }
}

function categoryClasses(category: string | null): string {
    switch ((category ?? '').toUpperCase()) {
        case 'MARKETING':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400';
        case 'AUTHENTICATION':
            return 'bg-violet-500/15 text-violet-700 dark:text-violet-400';
        case 'UTILITY':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-400';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function jsonHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        'X-XSRF-TOKEN': getCsrfToken(),
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };
    const socketId = echo.socketId();
    if (socketId) {
        headers['X-Socket-ID'] = socketId;
    }
    return headers;
}

/**
 * Only the fields the CRM could not answer travel to the server; it re-resolves the rest.
 */
function buildParameters(): Record<string, Record<string, string>> {
    const params: Record<string, Record<string, string>> = {};

    for (const [path, value] of Object.entries(fieldValues.value)) {
        const dot = path.indexOf('.');
        if (dot < 0 || !value.trim()) {
            continue;
        }
        const section = path.slice(0, dot);
        params[section] = params[section] ?? {};
        params[section][path.slice(dot + 1)] = value;
    }

    return params;
}

async function syncTemplates(): Promise<void> {
    if (syncing.value) {
        return;
    }

    syncing.value = true;
    error.value = null;
    notice.value = null;

    try {
        const response = await fetch(
            syncTemplatesRoute.url({ lead: props.leadId }),
            { method: 'POST', headers: jsonHeaders() },
        );
        const data = await response.json().catch(() => ({}));

        if (response.ok) {
            notice.value =
                'Sincronização agendada. Recarregue em instantes para ver novos templates.';
        } else {
            error.value =
                typeof data.message === 'string'
                    ? data.message
                    : `Falha ao sincronizar (HTTP ${response.status}).`;
        }
    } catch {
        error.value = 'Sem conexão com o servidor. Tente novamente.';
    } finally {
        syncing.value = false;
    }
}

async function submit(): Promise<void> {
    if (!selectedTemplate.value || sending.value) {
        return;
    }

    const missing = pendingFields.value.some(
        (f) => f.required && !fieldValues.value[f.path]?.trim(),
    );
    if (missing) {
        error.value = 'Preencha os campos que o template exige.';
        return;
    }

    sending.value = true;
    error.value = null;

    try {
        const response = await fetch(send.url({ lead: props.leadId }), {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({
                template_id: selectedTemplate.value.id,
                template_parameters: buildParameters(),
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok && data.message) {
            emit('sent', data.message as Message);
            close();
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
        ref="root"
        role="dialog"
        aria-label="Enviar template"
        class="absolute bottom-full left-0 z-40 mb-2 flex max-h-[26rem] w-[21rem] max-w-[calc(100vw-1rem)] flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-lg dark:border-sidebar-border"
    >
        <header
            class="flex shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
        >
            <div class="flex min-w-0 items-center gap-1.5">
                <button
                    v-if="selectedTemplate"
                    type="button"
                    class="-ml-1 rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Voltar para a lista"
                    @click="selectedId = null"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <h2 class="truncate text-sm font-semibold text-foreground">
                    {{ selectedTemplate ? selectedTemplate.name : 'Templates' }}
                </h2>
            </div>
            <button
                v-if="!selectedTemplate"
                type="button"
                :disabled="syncing"
                class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
                @click="syncTemplates"
            >
                <RefreshCw
                    class="h-3.5 w-3.5"
                    :class="{ 'animate-spin': syncing }"
                />
                Sincronizar
            </button>
        </header>

        <div
            v-if="showSearch && !selectedTemplate"
            class="relative shrink-0 border-b border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
        >
            <Search
                class="pointer-events-none absolute top-1/2 left-5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
            />
            <input
                ref="searchInput"
                v-model="query"
                type="search"
                placeholder="Buscar template"
                class="w-full rounded-md border border-sidebar-border/70 bg-background py-1.5 pr-2 pl-7 text-xs placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-sidebar-border"
            />
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div
                v-if="!templates.length"
                class="space-y-2 px-4 py-6 text-center"
            >
                <p class="text-xs text-muted-foreground">
                    Nenhum template ativo para esta instância.
                </p>
                <p class="text-xs text-muted-foreground">
                    Cadastre no Gerenciador de Negócios da Meta e clique em
                    Sincronizar.
                </p>
            </div>

            <p
                v-else-if="!selectedTemplate && !visibleTemplates.length"
                class="px-4 py-6 text-center text-xs text-muted-foreground"
            >
                Nenhum template corresponde a “{{ query }}”.
            </p>

            <ul v-else-if="!selectedTemplate" class="py-1">
                <li v-for="template in visibleTemplates" :key="template.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-muted"
                        @click="selectedId = template.id"
                    >
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-xs font-medium text-foreground"
                                >{{ template.name }}</span
                            >
                            <span
                                v-if="template.language"
                                class="block text-[0.65rem] text-muted-foreground"
                                >{{ template.language }}</span
                            >
                        </span>
                        <span
                            v-if="template.category"
                            class="shrink-0 rounded px-1.5 py-0.5 text-[0.6rem] font-semibold tracking-wide uppercase"
                            :class="categoryClasses(template.category)"
                        >
                            {{ template.category }}
                        </span>
                    </button>
                </li>
            </ul>

            <div v-else class="space-y-3 p-3">
                <p
                    class="rounded-lg bg-muted/50 p-2.5 text-xs whitespace-pre-wrap text-foreground"
                >
                    {{ selectedTemplate.preview || 'Sem pré-visualização.' }}
                </p>

                <label
                    v-for="field in pendingFields"
                    :key="field.path"
                    class="block space-y-1"
                >
                    <span
                        class="text-[0.65rem] font-medium text-muted-foreground"
                    >
                        {{ field.label
                        }}<span v-if="field.required" class="text-rose-500">
                            *</span
                        >
                    </span>
                    <input
                        v-model="fieldValues[field.path]"
                        type="text"
                        :placeholder="field.example ?? ''"
                        class="w-full rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-xs placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-sidebar-border"
                    />
                </label>
            </div>
        </div>

        <p
            v-if="error"
            class="shrink-0 border-t border-rose-200 bg-rose-50 px-3 py-2 text-[0.7rem] text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-400"
        >
            {{ error }}
        </p>
        <p
            v-else-if="notice"
            class="shrink-0 border-t border-sidebar-border/70 bg-muted/40 px-3 py-2 text-[0.7rem] text-muted-foreground dark:border-sidebar-border"
        >
            {{ notice }}
        </p>

        <footer
            v-if="selectedTemplate"
            class="shrink-0 border-t border-sidebar-border/70 p-2 dark:border-sidebar-border"
        >
            <button
                type="button"
                :disabled="sending"
                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                @click="submit"
            >
                <Loader2 v-if="sending" class="h-3.5 w-3.5 animate-spin" />
                Enviar
            </button>
        </footer>
    </div>
</template>
