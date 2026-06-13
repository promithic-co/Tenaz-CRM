<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Trash2, Info } from 'lucide-vue-next';
import InstanceDetailsDrawer from './InstanceDetailsDrawer.vue';

type Provider = 'evolution' | 'meta_cloud';

type Instance = {
    id: number;
    name: string;
    display_name: string | null;
    label: string;
    api_url: string;
    phone_number: string | null;
    provider: Provider;

    meta_waba_id: string | null;
    meta_phone_number_id: string | null;
    meta_quality_rating: string | null;
    meta_token_permanent: boolean;
    meta_token_expires_at: string | null;
    meta_coexistence: boolean;

    agent_id: number | null;
    agent_name: string | null;
    default_ai_mode: string | null;

    leads_count: number;

    has_proxy: boolean;
    proxy_host: string | null;
    proxy_port: number | null;
};

type ConnectionState = 'open' | 'connecting' | 'close' | 'loading';

const props = defineProps<{
    instance: Instance;
    csrf: string;
}>();

const emit = defineEmits<{ delete: [] }>();

// ─── Meta Cloud is always cloud-connected — no QR needed ─────────────────────

const isMetaCloud = computed(() => props.instance.provider === 'meta_cloud');

// ─── State ────────────────────────────────────────────────────────────────────

const state   = ref<ConnectionState>(isMetaCloud.value ? 'open' : 'loading');
const qrCode  = ref<string | null>(null);
const loading = ref(false);
const error   = ref<string | null>(null);
const showDetails = ref(false);

let pollInterval: ReturnType<typeof setInterval> | null = null;

// ─── Polling ─────────────────────────────────────────────────────────────────

function stopPolling(): void {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

function startPolling(): void {
    stopPolling();
    let ticks = 0;
    pollInterval = setInterval(async () => {
        try {
            ticks++;
            const res  = await fetch(`/whatsapp/${props.instance.id}/status`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            const next = data.state as ConnectionState;

            if (next === 'open')  { state.value = 'open';  qrCode.value = null; stopPolling(); return; }
            if (next === 'close') { state.value = 'close'; qrCode.value = null; stopPolling(); return; }

            if ((next === 'connecting' || state.value === 'connecting') && ticks % 5 === 0) {
                const qrRes  = await fetch(`/whatsapp/${props.instance.id}/connect`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': props.csrf },
                });
                const qrData = await qrRes.json();
                if (qrData.base64 && ! qrData.error) {
                    qrCode.value = qrData.base64;
                }
            }
        } catch { /* ignore transient errors */ }
    }, 3000);
}

// ─── Actions ─────────────────────────────────────────────────────────────────

async function loadStatus(): Promise<void> {
    if (isMetaCloud.value) { return; } // always open

    try {
        const res   = await fetch(`/whatsapp/${props.instance.id}/status`, { headers: { Accept: 'application/json' } });
        const data  = await res.json();
        state.value = (data.state as ConnectionState) || 'close';
    } catch {
        state.value = 'close';
    }
}

async function connect(): Promise<void> {
    loading.value = true;
    error.value   = null;

    try {
        const res  = await fetch(`/whatsapp/${props.instance.id}/connect`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': props.csrf },
        });
        const data = await res.json();

        if (data.error) {
            error.value = data.error;
            return;
        }

        if (data.base64) {
            qrCode.value = data.base64;
            state.value  = 'connecting';
            startPolling();
        } else {
            await loadStatus();
        }
    } catch {
        error.value = 'Erro ao conectar. Verifique se a API está acessível.';
    } finally {
        loading.value = false;
    }
}

async function disconnect(): Promise<void> {
    loading.value = true;
    error.value   = null;

    try {
        const res  = await fetch(`/whatsapp/${props.instance.id}/disconnect`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': props.csrf },
        });
        const data = await res.json();

        if (data.success) {
            state.value  = 'close';
            qrCode.value = null;
            stopPolling();
        } else {
            error.value = 'Não foi possível desconectar.';
        }
    } catch {
        error.value = 'Erro ao desconectar.';
    } finally {
        loading.value = false;
    }
}

function cancelConnecting(): void {
    state.value  = 'close';
    qrCode.value = null;
    stopPolling();
}

// ─── Provider badge ───────────────────────────────────────────────────────────

const providerLabel = computed(() => {
    if (props.instance.provider === 'meta_cloud') { return 'Meta Cloud'; }
    return 'Evolution';
});

const providerClass = computed(() => {
    if (props.instance.provider === 'meta_cloud') { return 'bg-blue-500/10 text-blue-400'; }
    return 'bg-purple-500/10 text-purple-400';
});

onMounted(loadStatus);
onUnmounted(stopPolling);
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border bg-card p-4">
        <!-- Header row: name + status badge -->
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold leading-tight">{{ instance.label }}</h3>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <p class="truncate font-mono text-xs text-muted-foreground">{{ instance.name }}</p>
                    <span :class="['inline-flex shrink-0 rounded px-1 py-0.5 text-[10px] font-medium leading-none', providerClass]">
                        {{ providerLabel }}
                    </span>
                </div>
            </div>
            <span
                :class="[
                    'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                    state === 'open'
                        ? 'bg-emerald-500/10 text-emerald-400'
                        : state === 'connecting' || state === 'loading'
                          ? 'bg-yellow-500/10 text-yellow-400'
                          : 'bg-red-500/10 text-red-400',
                ]"
            >
                <span
                    :class="[
                        'h-1.5 w-1.5 shrink-0 rounded-full',
                        state === 'open'
                            ? 'bg-emerald-400'
                            : state === 'connecting' || state === 'loading'
                              ? 'animate-pulse bg-yellow-400'
                              : 'bg-red-400',
                    ]"
                />
                {{ state === 'open' ? 'Conectado' : state === 'connecting' ? 'Aguardando' : state === 'loading' ? '...' : 'Desconectado' }}
            </span>
        </div>

        <!-- Phone number (primary visible identifier) -->
        <p v-if="instance.phone_number" class="-mt-1 truncate text-sm font-medium text-foreground">
            {{ instance.phone_number }}
        </p>
        <p v-else class="-mt-1 truncate text-xs italic text-muted-foreground">
            Número não disponível
        </p>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ error }}
        </div>

        <!-- ── Meta Cloud: QR Code area not needed, just footer ─────────────── -->
        <template v-if="isMetaCloud">
            <div class="mt-auto flex items-center justify-between gap-2 pt-1">
                <button
                    class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-red-400"
                    title="Remover instância"
                    @click="emit('delete')"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
                <button
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="showDetails = true"
                >
                    <Info class="h-3.5 w-3.5" />
                    Detalhes
                </button>
            </div>
        </template>

        <!-- ── Evolution flow ──────────────────────────────────────────────── -->
        <template v-else>
            <!-- Loading skeleton -->
            <div v-if="state === 'loading'" class="animate-pulse">
                <div class="h-7 w-24 rounded-lg bg-muted" />
            </div>

            <!-- QR Code -->
            <div v-else-if="state === 'connecting'" class="space-y-3">
                <p class="text-xs text-muted-foreground">
                    WhatsApp → <strong class="text-foreground">Dispositivos conectados</strong> → escaneie o QR.
                </p>

                <div class="flex justify-center">
                    <div class="rounded-xl border border-sidebar-border/70 bg-white p-2">
                        <img
                            v-if="qrCode"
                            :src="qrCode.startsWith('data:') ? qrCode : `data:image/png;base64,${qrCode}`"
                            alt="QR Code WhatsApp"
                            class="h-44 w-44 object-contain"
                        />
                        <div v-else class="flex h-44 w-44 items-center justify-center">
                            <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    </div>
                </div>

                <p class="text-center text-[10px] text-muted-foreground">Verificando a cada 3s...</p>

                <div class="flex justify-center">
                    <button
                        class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                        @click="cancelConnecting"
                    >
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Footer: delete icon + details + connect/disconnect button -->
            <div v-else class="mt-auto flex items-center justify-between gap-2 pt-1">
                <button
                    class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-red-400"
                    title="Remover instância"
                    @click="emit('delete')"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
                <div class="flex items-center gap-2">
                    <button
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="showDetails = true"
                    >
                        <Info class="h-3.5 w-3.5" />
                        Detalhes
                    </button>
                    <button
                        v-if="state === 'open'"
                        :disabled="loading"
                        class="rounded-md border border-red-500/30 px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10 disabled:opacity-50"
                        @click="disconnect"
                    >
                        {{ loading ? 'Desconectando...' : 'Desconectar' }}
                    </button>
                    <button
                        v-else
                        :disabled="loading"
                        class="rounded-md border border-emerald-500/30 px-3 py-1.5 text-xs font-medium text-emerald-400 transition-colors hover:bg-emerald-500/10 disabled:opacity-50"
                        @click="connect"
                    >
                        {{ loading ? 'Conectando...' : 'Conectar' }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Details drawer -->
        <InstanceDetailsDrawer
            :instance="instance"
            :open="showDetails"
            :state="state"
            @update:open="(v: boolean) => (showDetails = v)"
            @delete="emit('delete')"
        />
    </div>
</template>
