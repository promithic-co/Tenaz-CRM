<script setup lang="ts">
import { computed } from 'vue';
import { Lock } from 'lucide-vue-next';

type Status = {
    slug: string;
    label: string;
    color: string;
    is_canonical: boolean;
    position: number;
};

type Transition = {
    from: string;
    to: string;
};

type Props = {
    statuses: Status[];
    transitions: Transition[];
    canonicalSlugs: string[];
    editable?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    editable: true,
});

const emit = defineEmits<{
    (e: 'add', from: string, to: string): void;
    (e: 'remove', from: string, to: string): void;
}>();

function hasTransition(from: string, to: string): boolean {
    return props.transitions.some(t => t.from === from && t.to === to);
}

function isProtected(from: string, to: string): boolean {
    return props.canonicalSlugs.includes(from) && props.canonicalSlugs.includes(to) && hasTransition(from, to);
}

function toggle(from: string, to: string) {
    if (!props.editable || from === to) return;
    if (isProtected(from, to)) return;

    if (hasTransition(from, to)) {
        emit('remove', from, to);
    } else {
        emit('add', from, to);
    }
}

const sortedStatuses = computed(() =>
    [...props.statuses].sort((a, b) => a.position - b.position),
);
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 bg-background px-2 py-1.5 text-left text-muted-foreground">
                        De \ Para
                    </th>
                    <th
                        v-for="to in sortedStatuses"
                        :key="to.slug"
                        class="px-2 py-1.5 text-center text-muted-foreground font-normal max-w-[80px] truncate"
                        :title="to.label"
                    >
                        {{ to.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="from in sortedStatuses"
                    :key="from.slug"
                    class="border-t border-border/50"
                >
                    <td class="sticky left-0 z-10 bg-background px-2 py-1.5 font-medium text-foreground/80 max-w-[100px] truncate" :title="from.label">
                        {{ from.label }}
                    </td>
                    <td
                        v-for="to in sortedStatuses"
                        :key="to.slug"
                        class="px-2 py-1.5 text-center"
                    >
                        <!-- Self-transition diagonal — disabled -->
                        <span
                            v-if="from.slug === to.slug"
                            class="inline-block size-4 rounded bg-muted/50"
                        />

                        <!-- Protected canonical transition — read-only -->
                        <span
                            v-else-if="isProtected(from.slug, to.slug)"
                            class="inline-flex size-5 items-center justify-center rounded bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                            title="Transição canônica — usada pela IA, não pode ser removida"
                        >
                            <Lock class="size-2.5" />
                        </span>

                        <!-- Editable cell -->
                        <button
                            v-else
                            type="button"
                            :class="[
                                'size-5 rounded border transition-colors',
                                hasTransition(from.slug, to.slug)
                                    ? 'border-primary bg-primary/20 text-primary'
                                    : 'border-border bg-transparent text-transparent hover:border-primary/50',
                                !editable ? 'cursor-default' : 'cursor-pointer',
                            ]"
                            :title="hasTransition(from.slug, to.slug)
                                ? `Remover transição: ${from.label} → ${to.label}`
                                : `Adicionar transição: ${from.label} → ${to.label}`"
                            :disabled="!editable"
                            @click="toggle(from.slug, to.slug)"
                        >
                            <span v-if="hasTransition(from.slug, to.slug)" class="text-[10px] font-bold">✓</span>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
