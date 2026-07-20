<script setup lang="ts">
import { computed } from 'vue';
import type { FollowupState } from '@/pages/conversas/types';

type Props = {
    state: FollowupState;
};
const props = defineProps<Props>();

const statusStyles: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    paused: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    inactive: 'bg-muted text-muted-foreground',
};

const statusLabel = computed(() => {
    switch (props.state.status) {
        case 'active':
            return 'Ativo';
        case 'paused':
            return 'Pausado';
        default:
            return 'Inativo';
    }
});

// Active leads care about when the next attempt fires; paused/inactive leads
// care about why they are not sending. Show one or the other, never both.
const showNextDue = computed(
    () => props.state.status === 'active' && props.state.next_due_at !== null,
);

function formatDue(value: string): string {
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-1.5 text-xs">
        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground">Tentativas</span>
            <span class="font-medium text-foreground"
                >{{ state.count }}/{{ state.max }}</span
            >
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground">Estado</span>
            <span
                :class="[
                    'rounded-full px-2 py-0.5 text-[10px] font-medium',
                    statusStyles[state.status] ?? statusStyles.inactive,
                ]"
                >{{ statusLabel }}</span
            >
        </div>
        <div v-if="showNextDue" class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground">Próximo</span>
            <span class="text-foreground">{{
                formatDue(state.next_due_at!)
            }}</span>
        </div>
        <div v-else class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground">Motivo</span>
            <span class="text-right text-foreground">{{
                state.reason_label
            }}</span>
        </div>
    </div>
</template>
