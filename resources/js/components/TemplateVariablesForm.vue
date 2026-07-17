<script setup lang="ts">
export type TemplateVariableField = {
    key: string;
    label: string;
    type?: 'text' | 'textarea' | 'select';
    required?: boolean;
    max?: number;
    placeholder?: string;
    help?: string;
    options?: { value: string; label: string }[];
};

type Props = {
    fields: TemplateVariableField[];
    modelValue: Record<string, string>;
    errors?: Record<string, string>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Record<string, string>): void;
}>();

function setValue(key: string, value: string): void {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function errorFor(key: string): string | undefined {
    return props.errors?.[`variables.${key}`];
}
</script>

<template>
    <div v-if="fields.length" class="space-y-4">
        <div v-for="field in fields" :key="field.key">
            <label class="mb-1.5 block text-sm font-medium text-foreground">
                {{ field.label }}
                <span
                    v-if="!field.required"
                    class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </label>

            <textarea
                v-if="field.type === 'textarea'"
                :value="modelValue[field.key] ?? ''"
                :maxlength="field.max"
                :placeholder="field.placeholder"
                rows="3"
                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                @input="
                    setValue(
                        field.key,
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            ></textarea>

            <select
                v-else-if="field.type === 'select'"
                :value="modelValue[field.key] ?? ''"
                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                @change="
                    setValue(
                        field.key,
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="" disabled>Selecione...</option>
                <option
                    v-for="option in field.options ?? []"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <input
                v-else
                :value="modelValue[field.key] ?? ''"
                type="text"
                :maxlength="field.max"
                :placeholder="field.placeholder"
                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                @input="
                    setValue(field.key, ($event.target as HTMLInputElement).value)
                "
            />

            <p v-if="field.help" class="mt-1 text-xs text-muted-foreground">
                {{ field.help }}
            </p>
            <p v-if="errorFor(field.key)" class="mt-1 text-xs text-red-500">
                {{ errorFor(field.key) }}
            </p>
        </div>
    </div>
</template>
