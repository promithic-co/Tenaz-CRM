<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Bug, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type Session = {
    id: number;
    label: string;
    status: string;
    created_at: string;
};

type Message = {
    role: 'user' | 'assistant' | 'tool';
    content: string;
    hora: string;
};

type ToolCall = {
    name: string;
    input: unknown;
    output: unknown;
};

type DebugEntry = {
    tokens_in: number | null;
    tokens_out: number | null;
    duration: number | null;
    steps: number | null;
    tool_calls: ToolCall[];
    error?: string;
    model?: string;
};

type ModelOption = { label: string; value: string; hint?: string };
type VendorGroup = { vendor: string; models: ModelOption[] };

// ─── Props ───────────────────────────────────────────────────────────────────

type Props = {
    sessions: Session[];
    defaultModel: string;
    defaultProvider: string;
};
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laboratory', href: '/laboratory' },
    { title: 'Playground', href: '/playground' },
];

// ─── State ───────────────────────────────────────────────────────────────────

const sessions = ref<Session[]>(props.sessions);
const activeId = ref<number | null>(sessions.value[0]?.id ?? null);
const messages = ref<Message[]>([]);
const debugLog = ref<DebugEntry[]>([]);
const input = ref('');
const loading = ref(false);
const showPrompt = ref(false);
const showNewModal = ref(false);
const showDeleteId = ref<number | null>(null);
const showDebugPanel = ref(false);

// New session form
const newLabel = ref('');
const newPrompt = ref('');

// Prompt override editor per session
const promptDraft = ref('');

// Model selector
const MODEL_CATALOG: VendorGroup[] = [
    {
        vendor: 'OpenAI',
        models: [
            { label: 'GPT-5.4 Pro', value: 'gpt-5.4-pro', hint: 'Mais capaz' },
            { label: 'GPT-5.4', value: 'gpt-5.4' },
            {
                label: 'o4-mini',
                value: 'o4-mini',
                hint: 'Raciocínio, econômico',
            },
            { label: 'GPT-4o', value: 'gpt-4o' },
            { label: 'GPT-4o-mini', value: 'gpt-4o-mini', hint: 'Barato' },
        ],
    },
    {
        vendor: 'Anthropic',
        models: [
            {
                label: 'Claude Sonnet 4.6',
                value: 'anthropic/claude-sonnet-4-6',
                hint: 'Agentic premium',
            },
            {
                label: 'Claude Haiku 4.5',
                value: 'anthropic/claude-haiku-4-5',
                hint: 'Rápido e barato',
            },
        ],
    },
    {
        vendor: 'Google',
        models: [
            {
                label: 'Gemini 2.5 Flash',
                value: 'google/gemini-2.5-flash',
                hint: 'Muito rápido',
            },
            {
                label: 'Gemini 3 Flash',
                value: 'google/gemini-3-flash',
                hint: 'Alta capacidade',
            },
        ],
    },
    {
        vendor: 'DeepSeek',
        models: [
            {
                label: 'DeepSeek V3.2',
                value: 'deepseek/deepseek-v3.2',
                hint: 'Custo-benefício',
            },
        ],
    },
    {
        vendor: 'Kimi',
        models: [
            {
                label: 'Kimi K2',
                value: 'moonshotai/kimi-k2',
                hint: 'Raciocínio avançado',
            },
            {
                label: 'Kimi K2.5',
                value: 'moonshotai/kimi-k2.5',
                hint: 'Mais recente',
            },
        ],
    },
];

const selectedModel = ref(props.defaultModel);

function modelLabel(value: string): string {
    for (const group of MODEL_CATALOG) {
        for (const m of group.models) {
            if (m.value === value) return m.label;
        }
    }
    return value;
}

// Bateria de Testes state
const showAutoTestModal = ref(false);
const batchObjective = ref('');
const batchCycles = ref(3);
const batchRounds = ref(5);
const isBatchTesting = ref(false);
const currentBatchCycle = ref(0);
const showEvalModal = ref(false);
const evalReport = ref('');

// Red Team auto-scan state
type AttackVector = {
    category: string;
    scenario: string;
    severity: string;
    target: string;
};
const batchTesterModel = ref('anthropic/claude-sonnet-4-6');
const batchAgentModel = ref(props.defaultModel);
const attackPlan = ref<AttackVector[]>([]);
const isScanning = ref(false);
const scanError = ref('');

async function scanBlindspots() {
    isScanning.value = true;
    scanError.value = '';
    attackPlan.value = [];
    try {
        const res = await fetch('/playground/scan-blindspots', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                tester_model: batchTesterModel.value,
                focus_areas: batchObjective.value || null,
            }),
        });
        const data = await res.json();
        if (data.attacks?.length) {
            attackPlan.value = data.attacks;
        } else {
            scanError.value = data.error || 'Nenhum vetor de ataque gerado.';
        }
    } catch (e) {
        scanError.value = 'Erro de rede ao escanear vulnerabilidades.';
    } finally {
        isScanning.value = false;
    }
}

function removeAttack(index: number) {
    attackPlan.value.splice(index, 1);
}

function severityColor(sev: string): string {
    if (sev === 'high') return 'text-red-500 bg-red-500/10';
    if (sev === 'medium') return 'text-amber-500 bg-amber-500/10';
    return 'text-blue-500 bg-blue-500/10';
}

const chatEnd = ref<HTMLElement | null>(null);

const activeSession = computed(
    () => sessions.value.find((s) => s.id === activeId.value) ?? null,
);

// ─── Load messages when session changes ──────────────────────────────────────

watch(activeId, async (id) => {
    if (!id || isBatchTesting.value) return;
    debugLog.value = [];
    messages.value = [];
    promptDraft.value = '';
    const res = await fetch(`/playground/${id}/messages`);
    if (res.ok) {
        const data = await res.json();
        messages.value = data.messages ?? [];
        promptDraft.value = data.sandbox_system_prompt ?? '';
    }
    showEvalModal.value = false;
    scrollBottom();
});

// ─── Actions ─────────────────────────────────────────────────────────────────

async function createSession() {
    const res = await fetch('/playground', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
        body: JSON.stringify({
            label: newLabel.value || null,
            system_prompt: newPrompt.value || null,
        }),
    });
    if (!res.ok) return;
    const session = await res.json();
    sessions.value.unshift(session);
    activeId.value = session.id;
    showNewModal.value = false;
    newLabel.value = '';
    newPrompt.value = '';
}

async function deleteSession(id: number) {
    const res = await fetch(`/playground/${id}`, {
        method: 'DELETE',
        headers: { 'X-XSRF-TOKEN': csrf() },
    });
    if (!res.ok) return;
    sessions.value = sessions.value.filter((s) => s.id !== id);
    showDeleteId.value = null;
    if (activeId.value === id) {
        activeId.value = sessions.value[0]?.id ?? null;
    }
}

async function resetSession() {
    if (!activeId.value) return;
    const res = await fetch(`/playground/${activeId.value}/reset`, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': csrf() },
    });
    if (res.ok) {
        messages.value = [];
        debugLog.value = [];
    }
}

async function savePrompt() {
    if (!activeId.value) return;
    await fetch(`/playground/${activeId.value}/prompt`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
        body: JSON.stringify({ system_prompt: promptDraft.value }),
    });
    showPrompt.value = false;
}

async function sendMessage() {
    if (!input.value.trim() || !activeId.value || loading.value) return;

    const text = input.value.trim();
    input.value = '';
    loading.value = true;

    // Optimistic user bubble
    messages.value.push({ role: 'user', content: text, hora: now() });
    scrollBottom();

    const res = await fetch(`/playground/${activeId.value}/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
        body: JSON.stringify({
            message: text,
            model_override:
                selectedModel.value !== props.defaultModel
                    ? selectedModel.value
                    : null,
        }),
    });

    const data = await res.json();
    messages.value = data.messages ?? messages.value;

    if (data.debug) {
        debugLog.value.unshift(data.debug);
    }

    loading.value = false;
    scrollBottom();
}

async function startBatchTest() {
    // Use attack plan if available, otherwise require objective
    const hasAttackPlan = attackPlan.value.length > 0;
    if (!hasAttackPlan && !batchObjective.value.trim()) return;

    const cycles = hasAttackPlan
        ? attackPlan.value.length
        : Math.max(1, Math.min(15, Math.floor(Number(batchCycles.value) || 1)));
    const rounds = Math.max(
        1,
        Math.min(15, Math.floor(Number(batchRounds.value) || 1)),
    );
    const testerModel = batchTesterModel.value;
    const agentModel = batchAgentModel.value;

    // Apply agent model for the batch
    selectedModel.value = agentModel;

    showAutoTestModal.value = false;
    isBatchTesting.value = true;

    try {
        for (let cycle = 1; cycle <= cycles; cycle++) {
            if (!isBatchTesting.value) break;
            currentBatchCycle.value = cycle;
            batchCycles.value = cycles;

            // 1. Generate Scenario — from attack plan or via LLM
            let scenario = '';
            if (hasAttackPlan && attackPlan.value[cycle - 1]) {
                const atk = attackPlan.value[cycle - 1];
                scenario = `[${atk.category}] ${atk.scenario}`;
            } else {
                try {
                    const scRes = await fetch('/playground/generate-scenario', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': csrf(),
                        },
                        body: JSON.stringify({
                            objective: batchObjective.value,
                            cycle: cycle,
                            tester_model: testerModel,
                        }),
                    });
                    if (scRes.ok) scenario = (await scRes.json()).scenario;
                    else scenario = 'Cliente insistente (fallback)';
                } catch (e) {
                    scenario = 'Testador genérico (erro)';
                }
            }

            if (!isBatchTesting.value) break;

            // 2. Criar nova sessão Playground dinamicamente
            try {
                const sessRes = await fetch('/playground', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({
                        label: `Ciclo ${cycle}: ${scenario.substring(0, 40)}...`,
                        system_prompt: null,
                    }),
                });
                if (!sessRes.ok) break;
                const newSession = await sessRes.json();
                sessions.value.unshift(newSession);
                activeId.value = newSession.id;
                messages.value = [];
                debugLog.value = [];
                await nextTick();
            } catch (e) {
                break;
            }

            if (!isBatchTesting.value) break;

            messages.value.push({
                role: 'assistant',
                content: `[SISTEMA] Iniciando Ciclo ${cycle}/${cycles}\nAlvo:\n${scenario}`,
                hora: now(),
            });
            scrollBottom();

            // 3. Ping-Pong Loop
            const cycleTokenMetrics = [];

            for (let round = 0; round < rounds; round++) {
                if (!isBatchTesting.value) break;

                loading.value = true;
                let nextMessage = '';

                try {
                    const testerRes = await fetch(
                        `/playground/${activeId.value}/tester-chat`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-XSRF-TOKEN': csrf(),
                            },
                            body: JSON.stringify({
                                persona_prompt: scenario,
                                tester_model: testerModel,
                            }),
                        },
                    );

                    if (testerRes.ok) {
                        nextMessage = (await testerRes.json()).reply;
                    } else {
                        const errorText = await testerRes.text();
                        console.error('TESTER-CHAT ERROR:', errorText);
                        messages.value.push({
                            role: 'assistant',
                            content: `[ERRO] O Agente Testador falhou na rodada ${round + 1}. Abortando ciclo.\nDetalhes: ${errorText}`,
                            hora: now(),
                        });
                        break; // break the loop on error
                    }
                } catch (e) {
                    console.error('JS FETCH ERROR:', e);
                    messages.value.push({
                        role: 'assistant',
                        content: `[ERRO] Falha de rede ao contatar o Agente Testador na rodada ${round + 1}.`,
                        hora: now(),
                    });
                    break;
                }

                loading.value = false;

                if (nextMessage && isBatchTesting.value) {
                    input.value = nextMessage;
                    const prevDebugLen = debugLog.value.length;
                    try {
                        await sendMessage();
                    } catch (e) {
                        console.error('SEND MESSAGE ERROR:', e);
                        messages.value.push({
                            role: 'assistant',
                            content: `[ERRO] Falha ao enviar na rodada ${round + 1}.`,
                            hora: now(),
                        });
                        break;
                    }

                    // Track backend debug metrics for this specific round
                    if (debugLog.value.length > prevDebugLen) {
                        const latestDebug = debugLog.value[0];
                        cycleTokenMetrics.push({
                            round: round + 1,
                            tokens_in: latestDebug.tokens_in ?? 0,
                            tokens_out: latestDebug.tokens_out ?? 0,
                            tool_calls:
                                latestDebug.tool_calls?.map((tc) => tc.name) ??
                                [],
                        });
                    }
                }

                await new Promise((r) => setTimeout(r, 1200));
            }

            // 4. Mudar estado para avaliando
            if (isBatchTesting.value) {
                loading.value = true;
                let evalErrMsg = null;

                try {
                    const evalres = await fetch(
                        `/playground/${activeId.value}/evaluate`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-XSRF-TOKEN': csrf(),
                            },
                            body: JSON.stringify({
                                persona_prompt: scenario,
                                token_metrics: cycleTokenMetrics,
                                evaluator_model: testerModel,
                            }),
                        },
                    );

                    if (!evalres.ok) {
                        evalErrMsg = `[ERRO] A avaliação final falhou. (HTTP Status: ${evalres.status}) - Veja Console / Logs.`;
                    }

                    // Recarrega as mensagens para buscar a Evaluation do DB!
                    const resMsgs = await fetch(
                        `/playground/${activeId.value}/messages`,
                    );
                    if (resMsgs.ok) {
                        messages.value = (await resMsgs.json()).messages ?? [];
                    }

                    if (evalErrMsg) {
                        messages.value.push({
                            role: 'assistant',
                            content: evalErrMsg,
                            hora: now(),
                        });
                    }
                } catch (e) {
                    console.error('EVAL FETCH ERROR', e);
                    const resMsgs = await fetch(
                        `/playground/${activeId.value}/messages`,
                    );
                    if (resMsgs.ok) {
                        messages.value = (await resMsgs.json()).messages ?? [];
                    }
                    messages.value.push({
                        role: 'assistant',
                        content: `[ERRO] A requisição de avaliação causou um erro de rede.`,
                        hora: now(),
                    });
                } finally {
                    loading.value = false;
                    scrollBottom();
                }
            }

            // Pausa entre os ciclos para o usuário digerir o relatório no ecrã
            await new Promise((r) => setTimeout(r, 3000));
        }

        if (isBatchTesting.value) {
            messages.value.push({
                role: 'assistant',
                content:
                    '[SISTEMA] Bateria de Testes Estruturais finalizada com sucesso.',
                hora: now(),
            });
            scrollBottom();
        }
        isBatchTesting.value = false;
        currentBatchCycle.value = 0;
    } catch (batche) {
        console.error('FATAL BATCH ERROR', batche);
        isBatchTesting.value = false;
        currentBatchCycle.value = 0;
        messages.value.push({
            role: 'assistant',
            content: `[ERRO CRÍTICO] Falha fatal no script do navegador: ${batche}`,
            hora: now(),
        });
    }
}

function cancelAutoTest() {
    isBatchTesting.value = false;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function csrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function now(): string {
    return new Date().toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function scrollBottom() {
    nextTick(() => chatEnd.value?.scrollIntoView({ behavior: 'smooth' }));
}

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function totalTokens(entry: DebugEntry): number | null {
    if (entry.tokens_in == null && entry.tokens_out == null) return null;
    return (entry.tokens_in ?? 0) + (entry.tokens_out ?? 0);
}
</script>

<template>
    <Head title="Playground" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- ─── Layout 3 colunas ────────────────────────────────────────── -->
        <div
            class="flex h-[calc(100svh-3.5rem)] min-h-0 overflow-hidden sm:h-[calc(100svh-4rem)] lg:h-[calc(100svh-7.5rem)]"
        >
            <!-- ─ Painel Esquerdo: lista de sessões ─ -->
            <aside
                class="w-full shrink-0 flex-col border-r border-sidebar-border/70 bg-card md:w-56 dark:border-sidebar-border"
                :class="activeSession ? 'hidden md:flex' : 'flex'"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-3 py-3 dark:border-sidebar-border"
                >
                    <span
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >Sessões</span
                    >
                    <button
                        @click="showNewModal = true"
                        class="flex size-10 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:size-8"
                        title="Nova sessão"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto p-1.5">
                    <div
                        v-for="s in sessions"
                        :key="s.id"
                        @click="activeId = s.id"
                        :class="[
                            'group flex cursor-pointer items-center justify-between gap-1 rounded-lg px-2.5 py-2 text-sm transition-colors',
                            activeId === s.id
                                ? 'bg-primary/10 font-medium text-primary'
                                : 'text-muted-foreground hover:bg-muted/60',
                        ]"
                    >
                        <span class="truncate">{{ s.label }}</span>
                        <button
                            @click.stop="showDeleteId = s.id"
                            class="ml-1 flex size-9 shrink-0 items-center justify-center rounded text-muted-foreground opacity-100 transition-all hover:bg-destructive/10 hover:text-destructive sm:size-7 sm:opacity-0 sm:group-hover:opacity-100"
                            title="Excluir"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <p
                        v-if="!sessions.length"
                        class="px-3 py-6 text-center text-xs text-muted-foreground"
                    >
                        Nenhuma sessão.<br />Clique em + para criar.
                    </p>
                </nav>
            </aside>

            <!-- ─ Centro: Chat ─ -->
            <div
                class="min-w-0 flex-1 flex-col overflow-hidden"
                :class="activeSession ? 'flex' : 'hidden md:flex'"
            >
                <!-- Header da sessão -->
                <div
                    class="flex flex-col gap-2 border-b border-sidebar-border/70 bg-card px-2 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:px-4 dark:border-sidebar-border"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <button
                            v-if="activeSession"
                            type="button"
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground md:hidden"
                            aria-label="Voltar para sessoes"
                            @click="activeId = null"
                        >
                            <ArrowLeft class="size-5" />
                        </button>
                        <div v-if="activeSession" class="min-w-0">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ activeSession.label }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Status: {{ activeSession.status }}
                            </p>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Selecione ou crie uma sessão
                        </p>
                    </div>

                    <div
                        class="flex max-w-full items-center gap-2 overflow-x-auto pb-1 sm:pb-0"
                    >
                        <!-- Model selector -->
                        <select
                            v-model="selectedModel"
                            class="h-10 max-w-52 shrink-0 rounded-md border border-sidebar-border/70 bg-background px-2 text-xs text-foreground focus:ring-2 focus:ring-ring focus:outline-none sm:h-8 dark:border-sidebar-border"
                            title="Modelo do Agente"
                        >
                            <optgroup
                                v-for="group in MODEL_CATALOG"
                                :key="group.vendor"
                                :label="group.vendor"
                            >
                                <option
                                    v-for="m in group.models"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label
                                    }}{{ m.hint ? ` — ${m.hint}` : '' }}
                                </option>
                            </optgroup>
                        </select>

                        <button
                            type="button"
                            class="flex min-h-10 shrink-0 items-center gap-1 rounded-md border border-sidebar-border/70 bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground xl:hidden dark:border-sidebar-border"
                            @click="showDebugPanel = true"
                        >
                            <Bug class="size-3.5" />
                            Debug
                        </button>
                        <button
                            @click="showAutoTestModal = true"
                            :disabled="isBatchTesting"
                            class="flex items-center gap-1 rounded-md bg-emerald-600/10 px-3 py-1.5 text-xs font-medium text-emerald-600 transition-colors hover:bg-emerald-600/20 disabled:opacity-50"
                            title="Rodar múltiplos ciclos para testar estabilidade e falhas"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                                />
                            </svg>
                            <span v-if="!isBatchTesting">Ciclos de Teste</span>
                            <template v-else>
                                <span
                                    >{{ currentBatchCycle }}/{{
                                        batchCycles
                                    }}</span
                                >
                                <div
                                    class="h-1.5 w-16 overflow-hidden rounded-full bg-emerald-600/20"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                        :style="{
                                            width: `${(currentBatchCycle / batchCycles) * 100}%`,
                                        }"
                                    />
                                </div>
                            </template>
                        </button>
                        <button
                            v-if="activeSession"
                            @click="showPrompt = true"
                            class="flex items-center gap-1 rounded-md border border-sidebar-border/70 bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:border-sidebar-border"
                            title="Editar instruções desta sessão"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            Instruções
                        </button>
                        <button
                            @click="resetSession"
                            class="flex items-center gap-1 rounded-md border border-sidebar-border/70 bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:border-sidebar-border"
                            title="Reiniciar conversa"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            Reiniciar
                        </button>
                    </div>
                </div>

                <!-- Mensagens -->
                <div
                    class="flex-1 space-y-3 overflow-y-auto px-3 py-3 sm:px-4 sm:py-4"
                    v-if="activeSession"
                >
                    <div
                        v-if="!messages.length"
                        class="flex h-full items-center justify-center"
                    >
                        <div class="text-center">
                            <div
                                class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6 text-primary"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                    />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-foreground">
                                Sessão pronta
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Envie uma mensagem para começar o teste
                            </p>
                        </div>
                    </div>

                    <template v-for="(msg, i) in messages" :key="i">
                        <!-- Tool calls — exibição inline discreta -->
                        <div
                            v-if="msg.role === 'tool'"
                            class="flex justify-center"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border/70 bg-muted/60 px-3 py-1 text-xs text-muted-foreground dark:border-sidebar-border"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3 w-3"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                ferramenta executada
                            </span>
                        </div>

                        <!-- User message -->
                        <div
                            v-else-if="msg.role === 'user'"
                            class="flex justify-end"
                        >
                            <div class="max-w-[88%] sm:max-w-[70%]">
                                <div
                                    class="rounded-2xl rounded-tr-sm bg-primary px-4 py-2.5 text-sm whitespace-pre-wrap text-primary-foreground shadow-sm"
                                >
                                    {{ msg.content }}
                                </div>
                                <p
                                    class="mt-1 text-right text-[10px] text-muted-foreground"
                                >
                                    {{ msg.hora }}
                                </p>
                            </div>
                        </div>

                        <!-- Agent message -->
                        <div v-else class="flex justify-start">
                            <div class="max-w-[88%] sm:max-w-[70%]">
                                <div class="mb-1 flex items-center gap-2">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"
                                            />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                        >Agente</span
                                    >
                                </div>
                                <div
                                    class="rounded-2xl rounded-tl-sm border border-sidebar-border/70 bg-card px-4 py-2.5 text-sm whitespace-pre-wrap text-foreground shadow-sm dark:border-sidebar-border"
                                >
                                    {{ msg.content }}
                                </div>
                                <p
                                    class="mt-1 text-[10px] text-muted-foreground"
                                >
                                    {{ msg.hora }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Typing indicator -->
                    <div v-if="loading" class="flex justify-start">
                        <div
                            class="rounded-2xl rounded-tl-sm border border-sidebar-border/70 bg-card px-4 py-3 dark:border-sidebar-border"
                        >
                            <div class="flex gap-1">
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.3s]"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.15s]"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground"
                                ></span>
                            </div>
                        </div>
                    </div>
                    <div ref="chatEnd" />
                </div>

                <div
                    v-else
                    class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
                >
                    Selecione uma sessão à esquerda ou crie uma nova.
                </div>

                <!-- Input area -->
                <div
                    v-if="activeSession || isBatchTesting"
                    class="relative border-t border-sidebar-border/70 bg-card px-2 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] sm:px-4 sm:py-3 dark:border-sidebar-border"
                >
                    <div
                        v-if="isBatchTesting"
                        class="absolute inset-0 z-10 flex items-center justify-center bg-card/80 backdrop-blur-[1px]"
                    >
                        <button
                            @click="cancelAutoTest"
                            class="rounded-full bg-destructive px-5 py-1.5 text-xs font-medium text-destructive-foreground shadow-sm transition-colors hover:bg-destructive/90"
                        >
                            Parar Bateria de Testes
                        </button>
                    </div>
                    <div
                        class="flex items-end gap-2"
                        :class="{
                            'pointer-events-none opacity-50': isBatchTesting,
                        }"
                    >
                        <textarea
                            v-model="input"
                            @keydown="handleKey"
                            :disabled="loading || isBatchTesting"
                            rows="1"
                            placeholder="Simule o cliente... (Enter para enviar, Shift+Enter para nova linha)"
                            class="max-h-32 flex-1 resize-none overflow-auto rounded-xl border border-input bg-background px-3.5 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:outline-none disabled:opacity-50"
                        />
                        <button
                            @click="sendMessage"
                            :disabled="
                                loading || !input.trim() || isBatchTesting
                            "
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-40"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ─ Painel Direito: Debug ─ -->
            <div
                v-if="showDebugPanel"
                class="fixed inset-0 z-40 bg-black/50 xl:hidden"
                aria-hidden="true"
                @click="showDebugPanel = false"
            />
            <aside
                class="shrink-0 flex-col border-l border-sidebar-border/70 bg-card dark:border-sidebar-border"
                :class="
                    showDebugPanel
                        ? 'fixed inset-y-0 right-0 z-50 flex w-full max-w-sm'
                        : 'hidden w-72 xl:flex'
                "
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Debug
                    </p>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground xl:hidden"
                        aria-label="Fechar painel de debug"
                        @click="showDebugPanel = false"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div
                    class="flex-1 space-y-3 overflow-y-auto p-3"
                    v-if="debugLog.length"
                >
                    <div
                        v-for="(entry, i) in debugLog"
                        :key="i"
                        class="rounded-xl border border-sidebar-border/70 bg-muted/30 p-3 text-xs dark:border-sidebar-border"
                    >
                        <!-- Error state -->
                        <div
                            v-if="entry.error"
                            class="text-red-500 dark:text-red-400"
                        >
                            <p class="mb-1 font-semibold">Erro</p>
                            <p class="font-mono text-[10px] leading-relaxed">
                                {{ entry.error }}
                            </p>
                        </div>

                        <!-- Metrics -->
                        <div v-else>
                            <p
                                v-if="entry.model"
                                class="mb-2 truncate rounded bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                            >
                                {{ entry.model }}
                            </p>
                            <div class="mb-2 grid grid-cols-3 gap-1">
                                <div
                                    class="rounded-lg bg-background p-2 text-center"
                                >
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tokens
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{ totalTokens(entry) ?? '—' }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg bg-background p-2 text-center"
                                >
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Latência
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{
                                            entry.duration
                                                ? entry.duration + 'ms'
                                                : '—'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg bg-background p-2 text-center"
                                >
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Steps
                                    </p>
                                    <p class="font-semibold text-foreground">
                                        {{ entry.steps ?? '—' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="entry.tokens_in != null"
                                class="mb-2 flex gap-2 text-[10px] text-muted-foreground"
                            >
                                <span>↑ {{ entry.tokens_in }} in</span>
                                <span>↓ {{ entry.tokens_out }} out</span>
                            </div>

                            <!-- Tool calls -->
                            <div
                                v-if="entry.tool_calls?.length"
                                class="space-y-1.5"
                            >
                                <p class="font-semibold text-muted-foreground">
                                    Ferramentas
                                </p>
                                <div
                                    v-for="(tc, j) in entry.tool_calls"
                                    :key="j"
                                    class="rounded-lg border border-sidebar-border/70 bg-background p-2 dark:border-sidebar-border"
                                >
                                    <p
                                        class="font-mono font-semibold text-primary"
                                    >
                                        {{ tc.name }}
                                    </p>
                                    <details class="mt-1">
                                        <summary
                                            class="cursor-pointer text-[10px] text-muted-foreground hover:text-foreground"
                                        >
                                            Input
                                        </summary>
                                        <pre
                                            class="mt-1 overflow-auto rounded bg-muted/60 p-1.5 font-mono text-[9px] leading-relaxed text-foreground"
                                            >{{
                                                JSON.stringify(
                                                    tc.input,
                                                    null,
                                                    2,
                                                )
                                            }}</pre
                                        >
                                    </details>
                                    <details v-if="tc.output" class="mt-1">
                                        <summary
                                            class="cursor-pointer text-[10px] text-muted-foreground hover:text-foreground"
                                        >
                                            Output
                                        </summary>
                                        <pre
                                            class="mt-1 overflow-auto rounded bg-muted/60 p-1.5 font-mono text-[9px] leading-relaxed text-foreground"
                                            >{{
                                                JSON.stringify(
                                                    tc.output,
                                                    null,
                                                    2,
                                                )
                                            }}</pre
                                        >
                                    </details>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center gap-2 p-4 text-center"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-8 w-8 text-muted-foreground/30"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                    <p class="text-xs text-muted-foreground">
                        Métricas aparecerão<br />após o primeiro envio
                    </p>
                </div>
            </aside>
        </div>

        <!-- ─── Modal: Nova Sessão ──────────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showNewModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="showNewModal = false"
                />
                <div
                    class="relative z-10 w-full max-w-md rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-xl dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-base font-semibold text-foreground">
                        Nova Sessão de Teste
                    </h2>

                    <label class="mb-1 block text-sm text-muted-foreground"
                        >Apelido da sessão</label
                    >
                    <input
                        v-model="newLabel"
                        type="text"
                        placeholder="Ex: Teste CPF inválido"
                        maxlength="100"
                        class="mb-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                        @keyup.enter="createSession"
                    />

                    <label class="mb-1 block text-sm text-muted-foreground">
                        System prompt personalizado
                        <span class="text-xs"
                            >(opcional — usa o de produção se vazio)</span
                        >
                    </label>
                    <textarea
                        v-model="newPrompt"
                        rows="6"
                        placeholder="Cole aqui instruções alternativas para testar..."
                        class="mb-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    />

                    <div class="flex justify-end gap-2">
                        <button
                            @click="showNewModal = false"
                            class="rounded-lg border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="createSession"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Criar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal: Confirmar Delete ────────────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showDeleteId"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="showDeleteId = null"
                />
                <div
                    class="relative z-10 w-full max-w-sm rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-xl dark:border-sidebar-border"
                >
                    <h2 class="mb-2 text-base font-semibold text-foreground">
                        Excluir sessão?
                    </h2>
                    <p class="mb-5 text-sm text-muted-foreground">
                        O histórico desta conversa será apagado permanentemente.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button
                            @click="showDeleteId = null"
                            class="rounded-lg border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="deleteSession(showDeleteId!)"
                            class="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground transition-colors hover:bg-destructive/90"
                        >
                            Excluir
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal: Configurar Bateria de Testes (Red Team) ────────────── -->
        <Teleport to="body">
            <div
                v-if="showAutoTestModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="showAutoTestModal = false"
                />
                <div
                    class="relative z-10 max-h-[90svh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-sidebar-border/70 bg-card p-4 shadow-xl sm:p-6 dark:border-sidebar-border"
                >
                    <h2 class="mb-1 text-base font-semibold text-foreground">
                        Bateria de Testes (Red Team)
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        O Red Team LLM analisa o sistema do agente e gera
                        vetores de ataque automaticamente.
                    </p>

                    <!-- Model Selectors -->
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-foreground"
                                >Modelo Red Team (atacante)</label
                            >
                            <select
                                v-model="batchTesterModel"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <optgroup
                                    v-for="group in MODEL_CATALOG"
                                    :key="group.vendor"
                                    :label="group.vendor"
                                >
                                    <option
                                        v-for="m in group.models"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label
                                        }}{{ m.hint ? ` — ${m.hint}` : '' }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-foreground"
                                >Modelo do Agente (sendo testado)</label
                            >
                            <select
                                v-model="batchAgentModel"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <optgroup
                                    v-for="group in MODEL_CATALOG"
                                    :key="group.vendor"
                                    :label="group.vendor"
                                >
                                    <option
                                        v-for="m in group.models"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label
                                        }}{{ m.hint ? ` — ${m.hint}` : '' }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- Focus areas (optional) -->
                    <label
                        class="mb-1 block text-xs font-medium text-foreground"
                        >Foco adicional
                        <span class="text-muted-foreground"
                            >(opcional)</span
                        ></label
                    >
                    <textarea
                        v-model="batchObjective"
                        rows="2"
                        placeholder="Ex: Focar em tentativas de injeção de prompt e vazamento de dados..."
                        class="mb-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    />

                    <!-- Scan button -->
                    <button
                        @click="scanBlindspots"
                        :disabled="isScanning"
                        class="mb-4 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-emerald-600/40 bg-emerald-600/5 px-4 py-3 text-sm font-medium text-emerald-600 transition-colors hover:bg-emerald-600/10 disabled:opacity-50"
                    >
                        <svg
                            v-if="isScanning"
                            class="h-4 w-4 animate-spin"
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
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                            />
                        </svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        {{
                            isScanning
                                ? 'Analisando vulnerabilidades...'
                                : 'Escanear Vulnerabilidades do Agente'
                        }}
                    </button>

                    <p v-if="scanError" class="mb-3 text-xs text-red-500">
                        {{ scanError }}
                    </p>

                    <!-- Attack Plan Results -->
                    <div v-if="attackPlan.length" class="mb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-medium text-foreground"
                                >Plano de Ataque ({{
                                    attackPlan.length
                                }}
                                vetores)</label
                            >
                            <span class="text-[10px] text-muted-foreground"
                                >Clique no X para remover vetores</span
                            >
                        </div>
                        <div
                            class="max-h-48 space-y-1.5 overflow-y-auto rounded-lg border border-sidebar-border/70 bg-muted/20 p-2 dark:border-sidebar-border"
                        >
                            <div
                                v-for="(atk, i) in attackPlan"
                                :key="i"
                                class="group flex items-start gap-2 rounded-lg bg-background p-2 text-xs"
                            >
                                <span
                                    :class="[
                                        'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                                        severityColor(atk.severity),
                                    ]"
                                >
                                    {{ atk.severity }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-foreground">
                                        {{ atk.category }}
                                    </p>
                                    <p
                                        class="leading-relaxed text-muted-foreground"
                                    >
                                        {{ atk.scenario }}
                                    </p>
                                </div>
                                <button
                                    @click="removeAttack(i)"
                                    class="shrink-0 rounded p-0.5 text-muted-foreground opacity-0 transition-all group-hover:opacity-100 hover:text-destructive"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Rounds config (always shown) -->
                    <div
                        class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2"
                        :class="{ 'opacity-50': attackPlan.length }"
                    >
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-foreground"
                                >Ciclos
                                <span
                                    v-if="attackPlan.length"
                                    class="text-muted-foreground"
                                    >(auto: {{ attackPlan.length }})</span
                                ></label
                            >
                            <input
                                v-model.number="batchCycles"
                                type="number"
                                min="1"
                                max="15"
                                :disabled="attackPlan.length > 0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none disabled:opacity-50"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-foreground"
                                >Rodadas por Ciclo</label
                            >
                            <input
                                v-model.number="batchRounds"
                                type="number"
                                min="1"
                                max="15"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            @click="
                                showAutoTestModal = false;
                                attackPlan = [];
                            "
                            class="rounded-lg border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="startBatchTest"
                            :disabled="
                                !attackPlan.length && !batchObjective.trim()
                            "
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
                        >
                            Iniciar Bateria{{
                                attackPlan.length
                                    ? ` (${attackPlan.length} vetores)`
                                    : ''
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal: Relatório de Avaliação ──────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showEvalModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="showEvalModal = false"
                />
                <div
                    class="relative z-10 flex h-[80svh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-xl dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border"
                    >
                        <h2 class="text-base font-semibold text-foreground">
                            Resumo da Avaliação
                        </h2>
                        <button
                            @click="showEvalModal = false"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto bg-muted/20 p-6">
                        <div
                            class="prose prose-sm dark:prose-invert max-w-none font-sans whitespace-pre-wrap"
                        >
                            {{ evalReport }}
                        </div>
                    </div>
                    <div
                        class="shrink-0 border-t border-sidebar-border/70 bg-card p-4 text-right dark:border-sidebar-border"
                    >
                        <button
                            @click="showEvalModal = false"
                            class="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal: Editar Instruções (System Prompt) ──────────────── -->
        <Teleport to="body">
            <div
                v-if="showPrompt"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="showPrompt = false"
                />
                <div
                    class="relative z-10 w-full max-w-lg rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-xl dark:border-sidebar-border"
                >
                    <h2 class="mb-4 text-base font-semibold text-foreground">
                        Instruções da Sessão
                    </h2>

                    <label class="mb-1 block text-sm text-muted-foreground">
                        System prompt personalizado
                        <span class="text-xs"
                            >(deixe vazio para usar o de produção)</span
                        >
                    </label>
                    <textarea
                        v-model="promptDraft"
                        rows="10"
                        placeholder="Cole aqui instruções alternativas para testar..."
                        class="mb-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    />

                    <div class="flex justify-end gap-2">
                        <button
                            @click="showPrompt = false"
                            class="rounded-lg border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="savePrompt"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
