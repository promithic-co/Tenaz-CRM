<script setup lang="ts">
import {
    AlertCircle,
    ArrowLeft,
    Clock,
    FileText,
    MessageSquare,
    PanelRight,
    Paperclip,
    RefreshCw,
    Send,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import echo from '@/echo';
import { send } from '@/routes/conversas';
import type {
    ActiveConversation,
    Message,
    WhatsappTemplateOption,
} from '../types';
import TemplateSendDialog from './TemplateSendDialog.vue';

type Props = {
    conversation: ActiveConversation | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    back: [];
    details: [];
}>();

const templateDialogOpen = ref(false);

const availableTemplates = computed<WhatsappTemplateOption[]>(
    () => props.conversation?.whatsappTemplates ?? [],
);

function openTemplateDialog(): void {
    templateDialogOpen.value = true;
}

function onTemplateSent(message: Message): void {
    upsertMessage(message);
    // Sending a template reopens the 24h window; unlock the composer immediately.
    serviceDeadline.value = Date.now() + SERVICE_WINDOW_MS;
    scrollToBottom();
}

const SERVICE_WINDOW_MS = 24 * 60 * 60 * 1000;

// A ticking clock drives the live window countdown so the composer locks the moment the 24h
// window lapses, without a page reload. 30s granularity is plenty for a 24h window.
const now = ref(Date.now());
let clockTimer: ReturnType<typeof setInterval> | null = null;

function toMs(value: string | null | undefined): number | null {
    if (!value) {
        return null;
    }
    const ms = new Date(value).getTime();
    return Number.isNaN(ms) ? null : ms;
}

// Service-window deadline is reactive: it starts from the server payload and is pushed forward
// locally when a new inbound message arrives (mirrors Meta reopening the 24h window on reply).
const serviceDeadline = ref<number | null>(
    toMs(props.conversation?.conversationWindow?.service_window.expires_at),
);
const freeEntryDeadline = ref<number | null>(
    toMs(props.conversation?.conversationWindow?.free_entry_point.expires_at),
);

const windowClosed = computed<boolean>(() => {
    const w = props.conversation?.conversationWindow;
    if (!w) {
        // No window signal (e.g. non-Meta provider) → never lock.
        return false;
    }

    // A live free-entry-point window keeps the conversation open.
    if (freeEntryDeadline.value !== null && freeEntryDeadline.value > now.value) {
        return false;
    }

    if (serviceDeadline.value !== null) {
        return serviceDeadline.value <= now.value;
    }

    // No deadline known — trust the server's requirement flag (no inbound ⇒ locked).
    return w.template_required === true;
});

const templatesAvailable = computed<boolean>(
    () =>
        (props.conversation?.whatsappTemplatesEnabled ?? false) &&
        (props.conversation?.whatsappTemplates?.length ?? 0) > 0,
);

const messages = ref<Message[]>(
    props.conversation ? [...props.conversation.mensagens] : [],
);
const messageText = ref('');
const selectedFile = ref<File | null>(null);
const filePreviewUrl = ref<string | null>(null);
const sending = ref(false);
const sendError = ref<string | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const mediaLabels: Record<string, string> = {
    audio: 'Audio',
    image: 'Imagem',
    document: 'Documento',
    video: 'Video',
    sticker: 'Figurinha',
    unknown: 'Midia',
};

let channel: ReturnType<typeof echo.private> | null = null;

onMounted(() => {
    scrollToBottom();

    clockTimer = setInterval(() => {
        now.value = Date.now();
    }, 30_000);

    if (!props.conversation) {
        return;
    }

    channel = echo.private(`conversation.${props.conversation.lead.id}`);
    channel.listen('.message.new', (event: { message: Message }) => {
        upsertMessage(event.message);
        reopenWindowForInbound(event.message);
        scrollToBottom();
    });
});

onUnmounted(() => {
    clearFile();

    if (clockTimer) {
        clearInterval(clockTimer);
        clockTimer = null;
    }

    if (channel && props.conversation) {
        channel.stopListening('.message.new');
        echo.leave(`conversation.${props.conversation.lead.id}`);
    }
});

// A fresh inbound reopens Meta's 24h service window; recompute the deadline locally so the
// composer unlocks immediately without waiting for a page reload.
function reopenWindowForInbound(message: Message): void {
    if (message.role === 'user') {
        serviceDeadline.value = Date.now() + SERVICE_WINDOW_MS;
    }
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1048576) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function scrollToBottom(): void {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
}

function echoConnectionIsReady(): boolean {
    return echo.connectionStatus() === 'connected';
}

function upsertMessage(message: Message): void {
    const idx =
        message.id !== undefined
            ? messages.value.findIndex(
                  (existingMessage) => existingMessage.id === message.id,
              )
            : -1;

    if (idx >= 0) {
        messages.value[idx] = message;
    } else {
        messages.value.push(message);
    }
}

function onFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    selectedFile.value = file;
    filePreviewUrl.value = file.type.startsWith('image/')
        ? URL.createObjectURL(file)
        : null;
}

function clearFile(): void {
    selectedFile.value = null;

    if (filePreviewUrl.value) {
        URL.revokeObjectURL(filePreviewUrl.value);
        filePreviewUrl.value = null;
    }

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function extractErrorMessage(data: unknown, status: number): string {
    if (data && typeof data === 'object') {
        const d = data as {
            message?: unknown;
            errors?: Record<string, unknown>;
        };
        if (typeof d.message === 'string' && d.message.length > 0) {
            return d.message;
        }
        if (d.errors && typeof d.errors === 'object') {
            const first = Object.values(d.errors)[0];
            if (Array.isArray(first) && typeof first[0] === 'string') {
                return first[0];
            }
        }
    }
    if (status === 422) {
        return 'Mensagem inválida. Revise o conteúdo.';
    }
    if (status === 403) {
        return 'Sem permissão para enviar nesta conversa.';
    }
    if (status === 429) {
        return 'Muitas requisições. Aguarde e tente novamente.';
    }
    if (status >= 500) {
        return 'Erro no servidor. Tente novamente em instantes.';
    }
    return `Falha ao enviar (HTTP ${status}).`;
}

async function sendMessage(): Promise<void> {
    if (!props.conversation || sending.value) {
        return;
    }

    if (!messageText.value.trim() && !selectedFile.value) {
        return;
    }

    if (windowClosed.value) {
        sendError.value =
            'A janela de 24h está fechada. Envie um template aprovado ou aguarde o cliente responder.';
        return;
    }

    sending.value = true;
    sendError.value = null;

    const formData = new FormData();

    if (messageText.value.trim()) {
        formData.append('content', messageText.value.trim());
    }

    if (selectedFile.value) {
        formData.append('file', selectedFile.value);
    }

    try {
        const socketId = echo.socketId();
        const headers: Record<string, string> = {
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        };

        if (socketId) {
            headers['X-Socket-ID'] = socketId;
        }

        const response = await fetch(
            send.url({ lead: props.conversation.lead.id }),
            {
                method: 'POST',
                headers,
                body: formData,
            },
        );

        const data = await response.json().catch(() => ({}));

        if (response.ok && data.message) {
            messageText.value = '';
            clearFile();
            sendError.value = null;

            if (socketId || !echoConnectionIsReady()) {
                upsertMessage(data.message as Message);
                scrollToBottom();
            }
        } else {
            sendError.value = extractErrorMessage(data, response.status);
        }
    } catch (error) {
        sendError.value =
            'Sem conexão com o servidor. Verifique sua internet e tente novamente.';
        console.error('Failed to send message:', error);
    } finally {
        sending.value = false;
    }
}

function dismissError(): void {
    sendError.value = null;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}
</script>

<template>
    <section
        v-if="conversation"
        class="flex min-h-0 min-w-0 flex-col bg-background/40"
    >
        <header
            class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-2 sm:h-16 sm:px-5 dark:border-sidebar-border"
        >
            <button
                type="button"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground lg:hidden"
                aria-label="Voltar para conversas"
                @click="emit('back')"
            >
                <ArrowLeft class="size-5" />
            </button>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-foreground">
                    {{ conversation.lead.nome }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ conversation.lead.whatsapp }}
                </p>
            </div>
            <button
                type="button"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground xl:hidden"
                aria-label="Abrir detalhes do contato"
                @click="emit('details')"
            >
                <PanelRight class="size-5" />
            </button>
        </header>

        <div
            ref="messagesContainer"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-5"
        >
            <div
                v-if="!messages.length"
                class="flex h-full items-center justify-center text-sm text-muted-foreground"
            >
                Nenhuma mensagem ainda.
            </div>

            <div
                v-for="(msg, idx) in messages"
                :key="msg.id ?? idx"
                :class="[
                    'flex',
                    msg.role === 'user' ? 'justify-end' : 'justify-start',
                ]"
            >
                <div
                    :class="[
                        'flex max-w-[88%] flex-col gap-1 sm:max-w-[76%]',
                        msg.role === 'user' ? 'items-end' : 'items-start',
                    ]"
                >
                    <span
                        v-if="msg.role === 'operator'"
                        class="px-1 text-xs font-medium text-blue-400"
                        >Operador</span
                    >

                    <div
                        v-if="msg.media"
                        class="flex items-center gap-1.5 rounded-full border border-sidebar-border/70 bg-muted/60 px-2.5 py-1 text-xs text-muted-foreground dark:border-sidebar-border"
                    >
                        <FileText class="h-3.5 w-3.5" />
                        <span>{{
                            mediaLabels[msg.media.type] ?? 'Midia'
                        }}</span>
                        <span
                            v-if="msg.media.duration_secs"
                            class="text-muted-foreground/70"
                            >{{ msg.media.duration_secs }}s</span
                        >
                        <span
                            v-else-if="msg.media.filename"
                            class="max-w-32 truncate text-muted-foreground/70"
                            >{{ msg.media.filename }}</span
                        >
                        <span v-else class="text-muted-foreground/70">{{
                            formatBytes(msg.media.size_bytes)
                        }}</span>
                    </div>

                    <div
                        :class="[
                            'rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                            msg.role === 'user'
                                ? 'rounded-br-sm bg-primary text-primary-foreground'
                                : msg.role === 'operator'
                                  ? 'rounded-bl-sm bg-blue-600 text-white'
                                  : 'rounded-bl-sm bg-muted text-foreground',
                        ]"
                    >
                        <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                        <p
                            :class="[
                                'mt-1 text-xs',
                                msg.role === 'user'
                                    ? 'text-primary-foreground/60'
                                    : msg.role === 'operator'
                                      ? 'text-blue-200'
                                      : 'text-muted-foreground',
                            ]"
                        >
                            {{ msg.hora }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="sendError"
            class="flex shrink-0 items-start gap-2 border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-400"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span class="flex-1">{{ sendError }}</span>
            <button
                type="button"
                :disabled="sending"
                class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-white px-2 py-1 text-xs font-medium text-rose-700 transition-colors hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
                @click="sendMessage"
            >
                <RefreshCw
                    class="h-3 w-3"
                    :class="{ 'animate-spin': sending }"
                />
                Tentar novamente
            </button>
            <button
                type="button"
                class="rounded-md p-1 text-rose-700 hover:bg-rose-100 dark:text-rose-300 dark:hover:bg-rose-900/40"
                aria-label="Fechar mensagem de erro"
                @click="dismissError"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <div
            v-if="selectedFile"
            class="flex shrink-0 items-center gap-2 border-t border-sidebar-border/70 bg-muted/30 px-4 py-2 dark:border-sidebar-border"
        >
            <img
                v-if="filePreviewUrl"
                :src="filePreviewUrl"
                class="h-12 w-12 rounded object-cover"
                :alt="selectedFile.name"
            />
            <div
                v-else
                class="flex items-center gap-1.5 rounded bg-muted px-2 py-1"
            >
                <FileText class="h-4 w-4 text-muted-foreground" />
                <span class="max-w-48 truncate text-xs text-muted-foreground">{{
                    selectedFile.name
                }}</span>
            </div>
            <button
                type="button"
                class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="clearFile"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <div
            v-if="windowClosed"
            class="flex shrink-0 flex-col gap-2 border-t border-amber-200 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/40 dark:bg-amber-900/15"
        >
            <div class="flex items-start gap-2">
                <Clock
                    class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="text-xs text-amber-800 dark:text-amber-200">
                    <p class="font-semibold">Janela de 24h fechada</p>
                    <p class="text-amber-700/90 dark:text-amber-300/80">
                        Para falar de novo, envie um template aprovado ou aguarde
                        o cliente responder.
                    </p>
                </div>
            </div>
            <button
                v-if="templatesAvailable"
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-amber-700"
                @click="openTemplateDialog"
            >
                <FileText class="h-3.5 w-3.5" />
                Enviar template
            </button>
        </div>

        <div
            class="flex shrink-0 items-end gap-2 border-t border-sidebar-border/70 px-2 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] sm:px-4 sm:py-3 dark:border-sidebar-border"
        >
            <label
                :class="[
                    'flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground transition-colors',
                    windowClosed
                        ? 'cursor-not-allowed opacity-40'
                        : 'cursor-pointer hover:bg-muted hover:text-foreground',
                ]"
            >
                <Paperclip class="h-4 w-4" />
                <input
                    ref="fileInput"
                    type="file"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="hidden"
                    :disabled="windowClosed"
                    @change="onFileSelect"
                />
            </label>
            <button
                v-if="templatesAvailable && !windowClosed"
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Enviar template"
                @click="openTemplateDialog"
            >
                <FileText class="h-4 w-4" />
            </button>
            <textarea
                v-model="messageText"
                rows="1"
                :disabled="windowClosed"
                :placeholder="
                    windowClosed
                        ? 'Janela fechada — envie um template para continuar.'
                        : 'Digite uma mensagem...'
                "
                class="min-h-10 flex-1 resize-none rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-sidebar-border"
                @keydown="onKeydown"
            />
            <button
                type="button"
                :disabled="
                    windowClosed ||
                    sending ||
                    (!messageText.trim() && !selectedFile)
                "
                class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                @click="sendMessage"
            >
                <Send class="h-4 w-4" />
            </button>
        </div>

        <TemplateSendDialog
            v-if="conversation"
            :open="templateDialogOpen"
            :lead-id="conversation.lead.id"
            :templates="availableTemplates"
            @close="templateDialogOpen = false"
            @sent="onTemplateSent"
        />
    </section>

    <section
        v-else
        class="flex min-h-[28rem] min-w-0 flex-col items-center justify-center bg-background/40 p-8 text-center"
    >
        <div
            class="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <MessageSquare class="h-8 w-8" />
        </div>
        <h2 class="mt-4 text-base font-semibold text-foreground">
            Selecione uma conversa
        </h2>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
            Clique em um contato da lista para abrir o historico, responder
            mensagens e controlar a IA.
        </p>
    </section>
</template>
