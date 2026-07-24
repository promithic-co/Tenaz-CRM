<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

/**
 * One collapsible block of the lead details panel. Which blocks an operator
 * keeps open is a personal habit, so the state is remembered per section in the
 * browser rather than reset on every conversation.
 */
type Props = {
    sectionKey: string;
    title: string;
    defaultOpen?: boolean;
    tone?: 'default' | 'danger';
};

const props = withDefaults(defineProps<Props>(), {
    defaultOpen: false,
    tone: 'default',
});

const storageKey = `conversas:panel-section:${props.sectionKey}`;
const open = ref(props.defaultOpen);

onMounted(() => {
    const stored = localStorage.getItem(storageKey);

    if (stored !== null) {
        open.value = stored === 'open';
    }
});

function toggle(): void {
    open.value = !open.value;
    localStorage.setItem(storageKey, open.value ? 'open' : 'closed');
}
</script>

<template>
    <section
        :class="[
            'rounded-lg border',
            tone === 'danger'
                ? 'border-rose-500/30 bg-rose-500/5'
                : 'border-sidebar-border/70 bg-background/40 dark:border-sidebar-border',
        ]"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left"
            :aria-expanded="open"
            @click="toggle"
        >
            <span
                :class="[
                    'flex items-center gap-2 text-xs font-semibold',
                    tone === 'danger' ? 'text-rose-500' : 'text-muted-foreground',
                ]"
            >
                <slot name="icon" />
                {{ title }}
            </span>
            <span class="flex items-center gap-1.5">
                <slot name="meta" />
                <ChevronDown
                    :class="[
                        'h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform',
                        open ? 'rotate-180' : '',
                    ]"
                />
            </span>
        </button>
        <div v-if="open" class="px-3 pb-3">
            <slot />
        </div>
    </section>
</template>
