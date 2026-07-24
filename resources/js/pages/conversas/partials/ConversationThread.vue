<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Bot,
    Clock,
    ExternalLink,
    FileText,
    MessageSquare,
    PanelRight,
    Paperclip,
    Phone,
    RefreshCw,
    Reply,
    Send,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import echo from '@/echo';
import { returnToAi } from '@/routes/atendimentos';
import { claim, send } from '@/routes/conversas';
import type {
    ActiveConversation,
    ConversationSessionOpenReason,
    ConversationSessionSummary,
    Message,
    WhatsappTemplateOption,
} from '../types';
import TemplatePickerPopover from './TemplatePickerPopover.vue';

type Props = {
    conversation: ActiveConversation | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    back: [];
    details: [];
}>();

const templatePickerOpen = ref(false);

const availableTemplates = computed<WhatsappTemplateOption[]>(
    () => props.conversation?.whatsappTemplates ?? [],
);

function toggleTemplatePicker(): void {
    templatePickerOpen.value = !templatePickerOpen.value;
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
    if (
        freeEntryDeadline.value !== null &&
        freeEntryDeadline.value > now.value
    ) {
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

const sessionReasonLabels: Record<ConversationSessionOpenReason, string> = {
    first_contact: 'Primeiro contato',
    reengagement_after_terminal: 'Retorno após conclusão',
    reengagement_after_inactivity: 'Retorno após inatividade',
    campaign: 'Campanha',
    manual: 'Atendimento manual',
};

// Index sessions by id so a divider can label each atendimento with its number/reason.
const sessionMap = computed<Map<number, ConversationSessionSummary>>(() => {
    const map = new Map<number, ConversationSessionSummary>();
    for (const session of props.conversation?.sessions ?? []) {
        map.set(session.id, session);
    }
    return map;
});

type ThreadItem =
    | {
          kind: 'divider';
          key: string;
          sessionId: number;
          session: ConversationSessionSummary | null;
      }
    | { kind: 'message'; key: string | number; msg: Message };

// Interleave a session divider before the first message of each atendimento so the
// operator can see where one service cycle ends and the next begins. Messages with no
// session_id (pre-timeline history) never emit a divider.
const threadItems = computed<ThreadItem[]>(() => {
    const items: ThreadItem[] = [];
    let lastSessionId: number | null = null;

    messages.value.forEach((msg, idx) => {
        const sessionId = msg.session_id ?? null;

        if (sessionId !== null && sessionId !== lastSessionId) {
            items.push({
                kind: 'divider',
                key: `divider-${sessionId}`,
                sessionId,
                session: sessionMap.value.get(sessionId) ?? null,
            });
        }

        lastSessionId = sessionId;
        items.push({ kind: 'message', key: msg.id ?? `idx-${idx}`, msg });
    });

    return items;
});

function dividerLabel(item: Extract<ThreadItem, { kind: 'divider' }>): string {
    if (!item.session) {
        return 'Atendimento';
    }
    return `Atendimento #${item.session.number}`;
}

function dividerReason(
    item: Extract<ThreadItem, { kind: 'divider' }>,
): string | null {
    if (!item.session) {
        return null;
    }
    return sessionReasonLabels[item.session.open_reason] ?? null;
}

// Inbound stays neutral on the left; everything the tenant sent is accented on the right —
// blue when a human wrote it, green when the AI did, so an operator can audit the agent's
// replies without reading the author label.
function bubbleClasses(msg: Message): string {
    if (msg.role === 'user') {
        return 'rounded-bl-sm bg-muted text-foreground';
    }

    return msg.role === 'operator'
        ? 'rounded-br-sm bg-blue-600 text-white'
        : 'rounded-br-sm bg-emerald-600/15 text-foreground';
}

function bubbleMetaClasses(msg: Message): string {
    return msg.role === 'operator' ? 'text-blue-200' : 'text-muted-foreground';
}
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

const claimForm = useForm({});
const returnToAiForm = useForm({});

const aiModeLabels: Record<string, string> = {
    automatic: 'IA automatica',
    manual: 'Manual',
    assisted: 'IA assistida',
    qualify_then_handoff: 'IA qualifica',
};

const handoffStateLabels: Record<string, string> = {
    waiting_human: 'Aguardando atendimento',
    human_active: 'Em atendimento',
    waiting_customer: 'Aguardando cliente',
    ai_active: 'IA ativa',
    closed: 'Encerrado',
};

/**
 * The one action the header offers. Taking an unowned conversation always comes
 * first — that is the move an operator makes dozens of times a day. Handing it
 * back to the AI only appears once the conversation is actually in human hands.
 */
const primaryAction = computed<'claim' | 'return_to_ai' | null>(() => {
    if (!props.conversation) {
        return null;
    }

    if (!props.conversation.lead.assigned_user_id) {
        return 'claim';
    }

    return props.conversation.handoff_actions.includes('return_to_ai')
        ? 'return_to_ai'
        : null;
});

const aiLabel = computed<string>(() => {
    const lead = props.conversation?.lead;

    if (!lead) {
        return '';
    }

    if (props.conversation?.pausado) {
        return 'IA pausada';
    }

    return aiModeLabels[lead.effective_ai_mode] ?? lead.effective_ai_mode;
});

/** Time left on Meta's 24h service window, or null when there is nothing to count down. */
const windowRemaining = computed<string | null>(() => {
    if (serviceDeadline.value === null || windowClosed.value) {
        return null;
    }

    const minutes = Math.floor((serviceDeadline.value - now.value) / 60_000);

    if (minutes <= 0) {
        return null;
    }

    const hours = Math.floor(minutes / 60);

    return hours > 0 ? `${hours}h ${minutes % 60}min` : `${minutes}min`;
});

function submitPrimaryAction(): void {
    if (!props.conversation) {
        return;
    }

    if (primaryAction.value === 'claim') {
        claimForm.post(claim.url({ lead: props.conversation.lead.id }), {
            preserveScroll: true,
        });

        return;
    }

    const ticketId = props.conversation.active_handoff?.id;

    if (primaryAction.value === 'return_to_ai' && ticketId) {
        returnToAiForm.post(returnToAi.url({ ticket: ticketId }), {
            preserveScroll: true,
        });
    }
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
            class="shrink-0 border-b border-sidebar-border/70 px-2 sm:px-5 dark:border-sidebar-border"
        >
            <div class="flex items-center gap-2 pt-2">
                <button
                    type="button"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground lg:hidden"
                    aria-label="Voltar para conversas"
                    @click="emit('back')"
                >
                    <ArrowLeft class="size-5" />
                </button>
                <p
                    class="min-w-0 flex-1 truncate text-sm font-semibold text-foreground"
                >
                    {{ conversation.lead.nome }}
                </p>

                <button
                    v-if="primaryAction"
                    type="button"
                    :disabled="claimForm.processing || returnToAiForm.processing"
                    :class="[
                        'flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-3 text-xs font-medium text-white transition-colors disabled:opacity-50',
                        primaryAction === 'claim'
                            ? 'bg-blue-600 hover:bg-blue-700'
                            : 'bg-sky-600 hover:bg-sky-700',
                    ]"
                    :title="
                        primaryAction === 'claim'
                            ? 'Assume a conversa e pausa a IA'
                            : 'Encerra o atendimento humano e devolve o lead para a IA'
                    "
                    @click="submitPrimaryAction"
                >
                    <UserCheck
                        v-if="primaryAction === 'claim'"
                        class="h-3.5 w-3.5"
                    />
                    <Bot v-else class="h-3.5 w-3.5" />
                    {{
                        primaryAction === 'claim'
                            ? 'Assumir'
                            : 'Devolver para IA'
                    }}
                </button>

                <button
                    type="button"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Detalhes do contato"
                    @click="emit('details')"
                >
                    <PanelRight class="size-5" />
                </button>
            </div>

            <div
                class="flex flex-wrap items-center gap-x-2 gap-y-1 pt-1 pb-2 text-[11px] text-muted-foreground"
            >
                <StatusBadge :status="conversation.lead.status" />
                <span class="text-muted-foreground/40">·</span>
                <span>{{
                    handoffStateLabels[conversation.handoff_state] ??
                    conversation.handoff_state
                }}</span>
                <span class="text-muted-foreground/40">·</span>
                <span
                    :class="
                        conversation.lead.assigned_user_name
                            ? 'text-foreground'
                            : ''
                    "
                >
                    {{
                        conversation.lead.assigned_user_name ?? 'Sem atendente'
                    }}
                </span>
                <span class="text-muted-foreground/40">·</span>
                <span :class="conversation.pausado ? 'text-amber-500' : ''">{{
                    aiLabel
                }}</span>
                <template v-if="windowRemaining">
                    <span class="text-muted-foreground/40">·</span>
                    <span
                        class="flex items-center gap-1"
                        title="Tempo restante da janela de 24h do WhatsApp"
                    >
                        <Clock class="h-3 w-3" />
                        {{ windowRemaining }}
                    </span>
                </template>
                <span
                    v-else-if="windowClosed"
                    class="flex items-center gap-1 text-amber-500"
                    title="Janela de 24h fechada — so e possivel enviar template"
                >
                    <Clock class="h-3 w-3" />
                    Janela fechada
                </span>
            </div>
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

            <template v-for="item in threadItems" :key="item.key">
                <div
                    v-if="item.kind === 'divider'"
                    class="flex items-center gap-2 py-1"
                >
                    <span class="h-px flex-1 bg-sidebar-border/70" />
                    <span
                        class="flex items-center gap-1.5 rounded-full border border-sidebar-border/70 bg-muted/60 px-3 py-0.5 text-xs font-medium text-muted-foreground dark:border-sidebar-border"
                    >
                        {{ dividerLabel(item) }}
                        <span
                            v-if="item.session?.is_returning"
                            class="rounded-full bg-violet-500/10 px-1.5 text-[10px] font-medium text-violet-500 dark:text-violet-400"
                        >
                            Retornante
                        </span>
                        <span
                            v-else-if="dividerReason(item)"
                            class="text-muted-foreground/70"
                        >
                            · {{ dividerReason(item) }}
                        </span>
                    </span>
                    <span class="h-px flex-1 bg-sidebar-border/70" />
                </div>

                <!-- Messaging convention: what the lead sent is inbound (left),
                     everything the tenant sent — operator or AI — is outbound (right). -->
                <div
                    v-else
                    :class="[
                        'flex',
                        item.msg.role === 'user'
                            ? 'justify-start'
                            : 'justify-end',
                    ]"
                >
                    <div
                        :class="[
                            'flex max-w-[88%] flex-col gap-1 sm:max-w-[76%]',
                            item.msg.role === 'user'
                                ? 'items-start'
                                : 'items-end',
                        ]"
                    >
                        <span
                            v-if="item.msg.role === 'operator'"
                            class="px-1 text-xs font-medium text-blue-400"
                            >Operador</span
                        >

                        <div
                            v-if="item.msg.media"
                            class="flex items-center gap-1.5 rounded-full border border-sidebar-border/70 bg-muted/60 px-2.5 py-1 text-xs text-muted-foreground dark:border-sidebar-border"
                        >
                            <FileText class="h-3.5 w-3.5" />
                            <span>{{
                                mediaLabels[item.msg.media.type] ?? 'Midia'
                            }}</span>
                            <span
                                v-if="item.msg.media.duration_secs"
                                class="text-muted-foreground/70"
                                >{{ item.msg.media.duration_secs }}s</span
                            >
                            <span
                                v-else-if="item.msg.media.filename"
                                class="max-w-32 truncate text-muted-foreground/70"
                                >{{ item.msg.media.filename }}</span
                            >
                            <span v-else class="text-muted-foreground/70">{{
                                formatBytes(item.msg.media.size_bytes)
                            }}</span>
                        </div>

                        <div
                            :class="[
                                'rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                                bubbleClasses(item.msg),
                            ]"
                        >
                            <template v-if="item.msg.template">
                                <p
                                    v-if="item.msg.template.header?.text"
                                    class="mb-1 font-semibold whitespace-pre-wrap"
                                >
                                    {{ item.msg.template.header.text }}
                                </p>
                                <p class="whitespace-pre-wrap">
                                    {{
                                        item.msg.template.body ??
                                        item.msg.content
                                    }}
                                </p>
                                <p
                                    v-if="item.msg.template.footer"
                                    :class="[
                                        'mt-1 text-xs',
                                        bubbleMetaClasses(item.msg),
                                    ]"
                                >
                                    {{ item.msg.template.footer }}
                                </p>
                            </template>
                            <p v-else class="whitespace-pre-wrap">
                                {{ item.msg.content }}
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-xs',
                                    bubbleMetaClasses(item.msg),
                                ]"
                            >
                                {{ item.msg.hora }}
                            </p>

                            <div
                                v-if="item.msg.template?.buttons.length"
                                :class="[
                                    'mt-2 -mb-0.5 flex flex-col border-t pt-1',
                                    item.msg.role === 'operator'
                                        ? 'border-blue-400/40'
                                        : 'border-sidebar-border/70',
                                ]"
                            >
                                <span
                                    v-for="(button, buttonIdx) in item.msg
                                        .template.buttons"
                                    :key="buttonIdx"
                                    :class="[
                                        'flex items-center justify-center gap-1.5 py-1.5 text-center text-sm font-medium',
                                        buttonIdx > 0
                                            ? item.msg.role === 'operator'
                                                ? 'border-t border-blue-400/40'
                                                : 'border-t border-sidebar-border/70'
                                            : '',
                                        item.msg.role === 'operator'
                                            ? 'text-blue-100'
                                            : 'text-sky-500',
                                    ]"
                                >
                                    <ExternalLink
                                        v-if="button.type === 'URL'"
                                        class="h-3.5 w-3.5 shrink-0"
                                    />
                                    <Phone
                                        v-else-if="
                                            button.type === 'PHONE_NUMBER'
                                        "
                                        class="h-3.5 w-3.5 shrink-0"
                                    />
                                    <Reply
                                        v-else
                                        class="h-3.5 w-3.5 shrink-0"
                                    />
                                    {{ button.text }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
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
                        Para falar de novo, envie um template aprovado ou
                        aguarde o cliente responder.
                    </p>
                </div>
            </div>
            <button
                v-if="templatesAvailable"
                type="button"
                data-template-picker-trigger
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-amber-700"
                @click="toggleTemplatePicker"
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
            <!-- Anchor for the picker: the popover opens above this button, so it stays reachable
                 whether the composer is live or locked behind a closed 24h window. -->
            <div v-if="templatesAvailable" class="relative shrink-0">
                <button
                    type="button"
                    data-template-picker-trigger
                    :aria-expanded="templatePickerOpen"
                    class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-muted hover:text-foreground"
                    :class="
                        templatePickerOpen
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground'
                    "
                    aria-label="Enviar template"
                    @click="toggleTemplatePicker"
                >
                    <FileText class="h-4 w-4" />
                </button>

                <TemplatePickerPopover
                    v-if="conversation"
                    :open="templatePickerOpen"
                    :lead-id="conversation.lead.id"
                    :templates="availableTemplates"
                    @close="templatePickerOpen = false"
                    @sent="onTemplateSent"
                />
            </div>
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
