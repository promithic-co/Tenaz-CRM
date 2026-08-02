<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next';
import type { CustomFieldOption } from '@/lib/custom-fields';

/**
 * Options for a `select` field. Only the visible label is edited: the stored value
 * is derived from it on the server, so existing rows keep pointing at the same
 * option even when the wording changes.
 */
const props = defineProps<{
    modelValue: CustomFieldOption[];
    disabled?: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', options: CustomFieldOption[]): void;
}>();

function updateLabel(index: number, label: string): void {
    const next = props.modelValue.map((option, i) =>
        i === index ? { ...option, label } : option,
    );
    emit('update:modelValue', next);
}

function addOption(): void {
    emit('update:modelValue', [...props.modelValue, { value: '', label: '' }]);
}

function removeOption(index: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, i) => i !== index),
    );
}
</script>

<template>
    <div class="space-y-1.5">
        <div
            v-for="(option, index) in modelValue"
            :key="index"
            class="flex items-center gap-1.5"
        >
            <input
                :value="option.label"
                :disabled="disabled"
                type="text"
                maxlength="60"
                placeholder="Texto da opção"
                class="h-8 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                @input="
                    updateLabel(
                        index,
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
            <button
                type="button"
                :disabled="disabled"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-input text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
                title="Remover opção"
                aria-label="Remover opção"
                @click="removeOption(index)"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <button
            type="button"
            :disabled="disabled"
            class="flex h-8 items-center gap-1.5 rounded-md border border-dashed border-input px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
            @click="addOption"
        >
            <Plus class="h-3.5 w-3.5" />
            Adicionar opção
        </button>

        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
    </div>
</template>
