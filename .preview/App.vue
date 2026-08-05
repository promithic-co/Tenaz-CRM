<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import ConversationThread from '../resources/js/pages/conversas/partials/ConversationThread.vue';
import { conversation } from './fixtures';

const dark = ref(false);
const width = ref<'375' | '768' | 'full'>('full');

watchEffect(() => {
    document.documentElement.classList.toggle('dark', dark.value);
});
</script>

<template>
    <div class="flex h-screen flex-col bg-background">
        <div
            class="flex shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-3 py-2 text-xs"
        >
            <button
                type="button"
                class="rounded-md border border-sidebar-border/70 px-2 py-1 text-foreground"
                @click="dark = !dark"
            >
                {{ dark ? 'Claro' : 'Escuro' }}
            </button>
            <span class="text-muted-foreground">Largura:</span>
            <button
                v-for="option in ['375', '768', 'full'] as const"
                :key="option"
                type="button"
                class="rounded-md border px-2 py-1"
                :class="
                    width === option
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : 'border-sidebar-border/70 text-foreground'
                "
                @click="width = option"
            >
                {{ option === 'full' ? 'Cheia' : `${option}px` }}
            </button>
        </div>

        <div class="flex min-h-0 flex-1 justify-center overflow-hidden">
            <div
                class="flex min-h-0 flex-1 flex-col border-x border-sidebar-border/70"
                :style="width === 'full' ? {} : { maxWidth: `${width}px` }"
            >
                <ConversationThread :conversation="conversation" />
            </div>
        </div>
    </div>
</template>
