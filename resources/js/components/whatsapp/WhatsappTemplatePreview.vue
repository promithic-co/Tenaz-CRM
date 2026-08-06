<script setup lang="ts">
import { computed } from 'vue';
import type { WhatsappTemplateMedia } from '@/types/whatsapp-template';

const props = withDefaults(
    defineProps<{
        text: string;
        media?: WhatsappTemplateMedia | null;
        imageUrl?: string | null;
        imageAlt?: string;
        compact?: boolean;
    }>(),
    {
        media: null,
        imageUrl: null,
        imageAlt: 'Imagem do template',
        compact: false,
    },
);

const resolvedImageUrl = computed(
    () => props.imageUrl ?? props.media?.preview_url ?? null,
);
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-emerald-950/10 bg-[#efeae2] shadow-sm dark:border-white/10 dark:bg-slate-900"
    >
        <div
            v-if="resolvedImageUrl"
            class="relative overflow-hidden bg-muted"
            :class="compact ? 'max-h-36' : 'max-h-64'"
        >
            <img
                :src="resolvedImageUrl"
                :alt="imageAlt"
                class="h-full w-full object-cover"
                :class="{ 'opacity-45 grayscale': media?.state === 'expired' }"
            />
            <span
                v-if="media?.state === 'expired'"
                class="absolute inset-x-2 bottom-2 rounded-md bg-background/90 px-2 py-1 text-center text-[0.65rem] font-semibold text-amber-700 shadow-sm dark:text-amber-300"
            >
                Imagem expirada
            </span>
        </div>

        <div class="p-2.5">
            <div
                class="rounded-lg rounded-tl-sm bg-white px-3 py-2 text-sm whitespace-pre-wrap text-slate-900 shadow-sm dark:bg-slate-800 dark:text-slate-100"
            >
                {{ text || 'Sem pré-visualização.' }}
            </div>
        </div>
    </div>
</template>
