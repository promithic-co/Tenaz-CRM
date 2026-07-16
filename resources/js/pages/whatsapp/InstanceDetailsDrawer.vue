<script setup lang="ts">
import { Check, Copy, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConnectionState = 'open' | 'connecting' | 'close' | 'loading';

type Instance = {
    id: number;
    name: string;
    display_name: string | null;
    label: string;
    api_url: string;
    phone_number: string | null;
    provider: 'meta_cloud';

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

const props = defineProps<{
    instance: Instance;
    open: boolean;
    state: ConnectionState;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    delete: [];
}>();

const copiedKey = ref<string | null>(null);

async function copy(key: string, value: string | null): Promise<void> {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) {
                copiedKey.value = null;
            }
        }, 1500);
    } catch {
        // Clipboard access may be unavailable outside secure browser contexts.
    }
}

const qualityChip = computed(() => {
    const rating = (props.instance.meta_quality_rating ?? '').toUpperCase();

    if (rating === 'GREEN') {
        return {
            label: 'Alta',
            class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        };
    }

    if (rating === 'YELLOW') {
        return {
            label: 'Média',
            class: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
        };
    }

    if (rating === 'RED') {
        return {
            label: 'Baixa',
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    return {
        label: 'Sem dados',
        class: 'bg-muted text-muted-foreground border-border',
    };
});

const tokenInfo = computed(() => {
    if (props.instance.meta_token_permanent) {
        return {
            label: 'Permanente',
            class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        };
    }

    if (!props.instance.meta_token_expires_at) {
        return {
            label: 'Temporário',
            class: 'bg-muted text-muted-foreground border-border',
        };
    }

    const expiresAt = new Date(props.instance.meta_token_expires_at);
    const now = new Date();
    const diffMs = expiresAt.getTime() - now.getTime();
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (days < 0) {
        return {
            label: 'Token expirado',
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    if (days < 7) {
        return {
            label: `Expira em ${days}d`,
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    if (days < 30) {
        return {
            label: `Expira em ${days}d`,
            class: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
        };
    }

    return {
        label: `Expira em ${days}d`,
        class: 'bg-muted text-muted-foreground border-border',
    };
});

const aiModeLabel = computed(() => {
    const mode = props.instance.default_ai_mode;

    if (mode === 'automatic') {
        return 'Automático';
    }

    if (mode === 'manual') {
        return 'Manual';
    }

    if (mode === 'assisted') {
        return 'Assistido';
    }

    if (mode === 'qualify_then_handoff') {
        return 'Qualifica e transfere';
    }

    return mode ?? '-';
});

const statusLabel = computed(() => {
    if (props.state === 'open') {
        return 'Conectado';
    }

    if (props.state === 'connecting') {
        return 'Aguardando';
    }

    if (props.state === 'loading') {
        return '...';
    }

    return 'Desconectado';
});

const statusClass = computed(() => {
    if (props.state === 'open') {
        return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30';
    }

    if (props.state === 'connecting' || props.state === 'loading') {
        return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30';
    }

    return 'bg-red-500/10 text-red-400 border-red-500/30';
});

const providerLabel = 'Meta Cloud';
const providerClass = 'bg-blue-500/10 text-blue-400 border-blue-500/30';
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <span class="truncate">{{ instance.label }}</span>
                </DialogTitle>
                <DialogDescription
                    class="flex flex-wrap items-center gap-1.5 pt-1"
                >
                    <span
                        :class="[
                            'inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-medium',
                            providerClass,
                        ]"
                    >
                        {{ providerLabel }}
                    </span>
                    <span
                        :class="[
                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium',
                            statusClass,
                        ]"
                    >
                        {{ statusLabel }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5 py-2">
                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Identificação
                    </h4>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Telefone</dt>
                            <dd class="font-medium text-foreground">
                                {{ instance.phone_number ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Nome interno</dt>
                            <dd class="truncate font-mono text-foreground">
                                {{ instance.name }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Meta Cloud
                    </h4>

                    <dl class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">WABA ID</dt>
                            <dd class="flex min-w-0 items-center gap-1.5">
                                <span
                                    class="truncate font-mono text-foreground"
                                    >{{ instance.meta_waba_id ?? '-' }}</span
                                >
                                <button
                                    v-if="instance.meta_waba_id"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :title="
                                        copiedKey === 'waba'
                                            ? 'Copiado!'
                                            : 'Copiar'
                                    "
                                    @click="copy('waba', instance.meta_waba_id)"
                                >
                                    <Check
                                        v-if="copiedKey === 'waba'"
                                        class="h-3.5 w-3.5 text-emerald-400"
                                    />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </button>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Phone Number ID
                            </dt>
                            <dd class="flex min-w-0 items-center gap-1.5">
                                <span
                                    class="truncate font-mono text-foreground"
                                    >{{
                                        instance.meta_phone_number_id ?? '-'
                                    }}</span
                                >
                                <button
                                    v-if="instance.meta_phone_number_id"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :title="
                                        copiedKey === 'phone'
                                            ? 'Copiado!'
                                            : 'Copiar'
                                    "
                                    @click="
                                        copy(
                                            'phone',
                                            instance.meta_phone_number_id,
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copiedKey === 'phone'"
                                        class="h-3.5 w-3.5 text-emerald-400"
                                    />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </button>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Qualidade</dt>
                            <dd>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium',
                                        qualityChip.class,
                                    ]"
                                >
                                    {{ qualityChip.label }}
                                </span>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Token</dt>
                            <dd>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium',
                                        tokenInfo.class,
                                    ]"
                                >
                                    {{ tokenInfo.label }}
                                </span>
                            </dd>
                        </div>

                        <div
                            v-if="instance.meta_coexistence"
                            class="flex items-center justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">Modo</dt>
                            <dd>
                                <span
                                    class="inline-flex items-center rounded border border-blue-500/30 bg-blue-500/10 px-2 py-0.5 text-[11px] font-medium text-blue-400"
                                >
                                    Coexistência
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Configuração de IA
                    </h4>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Agente</dt>
                            <dd class="truncate font-medium text-foreground">
                                {{
                                    instance.agent_name ??
                                    'Nenhum agente atribuído'
                                }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Modo padrão</dt>
                            <dd class="font-medium text-foreground">
                                {{ aiModeLabel }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Estatísticas
                    </h4>
                    <div
                        class="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2"
                    >
                        <Users class="h-4 w-4 text-muted-foreground" />
                        <div class="flex-1">
                            <p class="text-xs text-muted-foreground">
                                Total de leads
                            </p>
                            <p
                                class="text-lg font-semibold text-foreground tabular-nums"
                            >
                                {{ instance.leads_count }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    @click="emit('update:open', false)"
                    >Fechar</Button
                >
                <Button
                    variant="destructive"
                    size="sm"
                    @click="
                        emit('delete');
                        emit('update:open', false);
                    "
                    >Remover</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
