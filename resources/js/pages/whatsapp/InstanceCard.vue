<script setup lang="ts">
import { ref } from 'vue';
import { Info, Trash2 } from 'lucide-vue-next';
import InstanceDetailsDrawer from './InstanceDetailsDrawer.vue';

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
    csrf: string;
}>();

const emit = defineEmits<{ delete: [] }>();

const showDetails = ref(false);
const state = 'open';
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
                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400"
            >
                <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"
                />
                Conectado
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

        <InstanceDetailsDrawer
            :instance="props.instance"
            :open="showDetails"
            :state="state"
            @update:open="(v: boolean) => (showDetails = v)"
            @delete="emit('delete')"
        />
    </div>
</template>
