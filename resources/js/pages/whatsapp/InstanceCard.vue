<script setup lang="ts">
import { Info, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    healthChip,
    healthStatusOf,
    portfolioLimitLabel,
} from '@/composables/useMetaHealth';
import type { WhatsappInstanceSummary } from '@/types';
import InstanceDetailsDrawer from './InstanceDetailsDrawer.vue';

const props = defineProps<{
    instance: WhatsappInstanceSummary;
    csrf: string;
    refreshing?: boolean;
}>();

const emit = defineEmits<{
    delete: [];
    refresh: [];
    updated: [Partial<WhatsappInstanceSummary>];
}>();

const showDetails = ref(false);

const status = computed(() => healthStatusOf(props.instance.health_status));
const chip = computed(() => healthChip(props.instance.health_status));

// The first reason Meta gave is the actionable one ("nome de exibição em
// análise", "falta forma de pagamento); the drawer lists the rest with the
// detail and the fix.
const headlineReason = computed<string | null>(
    () => props.instance.health_reasons?.[0]?.title ?? null,
);

const portfolioLimit = computed(() =>
    portfolioLimitLabel(props.instance.meta_portfolio_messaging_limit),
);

const providerLabel = 'Meta Cloud';
const providerClass = 'bg-blue-500/10 text-blue-400';
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border bg-card p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="truncate text-sm leading-tight font-semibold">
                    {{ props.instance.label }}
                </h3>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <p class="truncate font-mono text-xs text-muted-foreground">
                        {{ props.instance.name }}
                    </p>
                    <span
                        :class="[
                            'inline-flex shrink-0 rounded px-1 py-0.5 text-[10px] leading-none font-medium',
                            providerClass,
                        ]"
                    >
                        {{ providerLabel }}
                    </span>
                </div>
            </div>
            <span
                :class="[
                    'inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
                    chip.class,
                ]"
            >
                <span
                    :class="[
                        'h-1.5 w-1.5 shrink-0 rounded-full',
                        chip.dot,
                        status === 'BLOCKED' ? 'animate-pulse' : '',
                    ]"
                />
                {{ chip.label }}
            </span>
        </div>

        <p
            v-if="props.instance.phone_number"
            class="-mt-1 truncate text-sm font-medium text-foreground"
        >
            {{ props.instance.phone_number }}
        </p>
        <p v-else class="-mt-1 truncate text-xs text-muted-foreground italic">
            Número não disponível
        </p>

        <p
            v-if="portfolioLimit"
            class="-mt-2 truncate text-[11px] text-muted-foreground"
            title="Conversas iniciadas em 24h. Teto do portfólio empresarial, compartilhado por todos os números dele."
        >
            {{ portfolioLimit }}
            <span class="opacity-70">· portfólio</span>
        </p>

        <p
            v-if="headlineReason && status !== 'AVAILABLE'"
            class="line-clamp-2 rounded-md border px-2 py-1.5 text-[11px] leading-snug"
            :class="chip.class"
            :title="headlineReason"
        >
            {{ headlineReason }}
        </p>

        <div class="mt-auto flex items-center justify-between gap-2 pt-1">
            <button
                class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-red-400"
                title="Remover instância"
                @click="emit('delete')"
            >
                <Trash2 class="h-4 w-4" />
            </button>
            <div class="flex items-center gap-1.5">
                <button
                    class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
                    title="Atualizar status na Meta"
                    :disabled="props.refreshing"
                    @click="emit('refresh')"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="props.refreshing ? 'animate-spin' : ''"
                    />
                </button>
                <button
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="showDetails = true"
                >
                    <Info class="h-3.5 w-3.5" />
                    Detalhes
                </button>
            </div>
        </div>

        <InstanceDetailsDrawer
            :instance="props.instance"
            :open="showDetails"
            :csrf="props.csrf"
            :refreshing="props.refreshing"
            @update:open="(v: boolean) => (showDetails = v)"
            @delete="emit('delete')"
            @refresh="emit('refresh')"
            @updated="(payload) => emit('updated', payload)"
        />
    </div>
</template>
