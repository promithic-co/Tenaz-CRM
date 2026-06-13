<script setup lang="ts">
import { computed } from 'vue';
import { X, Sparkles, Flame } from 'lucide-vue-next';

type Tag = {
    id: number;
    name: string;
    slug?: string;
    color?: string | null;
    is_hot?: boolean;
    source?: 'manual' | 'ai' | 'import' | 'system' | null;
    ai_confidence?: number | null;
};

type Props = {
    tag: Tag;
    removable?: boolean;
    size?: 'sm' | 'md';
};

const props = withDefaults(defineProps<Props>(), {
    removable: false,
    size: 'sm',
});

const emit = defineEmits<{ (e: 'remove', tag: Tag): void }>();

const colorClasses: Record<string, string> = {
    gray: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    pink: 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300',
};

const classes = computed(() => {
    const color = props.tag.color ?? 'gray';
    const base = colorClasses[color] ?? colorClasses.gray;
    const sizing = props.size === 'md'
        ? 'px-2.5 py-1 text-xs'
        : 'px-2 py-0.5 text-[11px]';
    return `${base} ${sizing}`;
});

const SourceIcon = computed(() => {
    switch (props.tag.source) {
        case 'ai': return Sparkles;
        default: return null;
    }
});
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1 rounded-full font-medium', classes]"
        :title="tag.source === 'ai' && tag.ai_confidence != null
            ? `IA · ${Math.round(tag.ai_confidence * 100)}% confiança`
            : tag.source ? `Origem: ${tag.source}` : undefined"
    >
        <component :is="SourceIcon" v-if="SourceIcon" class="size-3" />
        <Flame v-if="tag.is_hot === true" class="size-3 text-orange-600 dark:text-orange-400" title="Sinal forte" />
        <span>{{ tag.name }}</span>
        <button
            v-if="removable"
            type="button"
            class="ml-0.5 -mr-0.5 rounded-full p-0.5 transition-colors hover:bg-black/10 dark:hover:bg-white/10"
            :aria-label="`Remover ${tag.name}`"
            @click.stop="emit('remove', tag)"
        >
            <X class="size-3" />
        </button>
    </span>
</template>
