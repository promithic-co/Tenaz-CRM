<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { statusLabel, statusClasses } from '@/lib/lead-status';

const props = defineProps<{
    currentStatus: string;
    availableTransitions: string[];
    leadId: number;
}>();

const emit = defineEmits<{
    (event: 'updated', status: string): void;
}>();

const selected = ref<string>(props.currentStatus);
const saving = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.currentStatus,
    (next) => {
        selected.value = next;
    },
);

function onUpdate(next: string | number | boolean | null) {
    const value = String(next ?? '');
    if (!value || value === props.currentStatus) {
        return;
    }

    const previous = props.currentStatus;
    selected.value = value;
    saving.value = true;
    error.value = null;

    router.post(
        `/leads/${props.leadId}/status`,
        { status: value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['conversation'],
            onSuccess: () => {
                emit('updated', value);
            },
            onError: (errors) => {
                selected.value = previous;
                error.value = typeof errors?.status === 'string' ? errors.status : 'Não foi possível alterar o status.';
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col items-stretch gap-1">
        <Select :model-value="selected" :disabled="saving" @update:model-value="(v) => onUpdate(v as string)">
            <SelectTrigger
                :class="[
                    'h-8 w-full justify-between gap-2 rounded-full border px-3 py-0.5 text-xs font-medium whitespace-nowrap focus:ring-0',
                    statusClasses(selected),
                    saving ? 'opacity-60' : '',
                ]"
            >
                <SelectValue>{{ statusLabel(selected) }}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="currentStatus">
                    {{ statusLabel(currentStatus) }}
                </SelectItem>
                <SelectItem
                    v-for="slug in availableTransitions"
                    :key="slug"
                    :value="slug"
                    :disabled="slug === currentStatus"
                >
                    {{ statusLabel(slug) }}
                </SelectItem>
            </SelectContent>
        </Select>
        <p v-if="error" class="text-[10px] text-red-500">{{ error }}</p>
    </div>
</template>
