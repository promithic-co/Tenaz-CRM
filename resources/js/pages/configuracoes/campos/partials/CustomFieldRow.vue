<script setup lang="ts">
import { ChevronDown, ChevronUp, Lock, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { customFieldTypeLabel } from '@/lib/custom-fields';
import type { CustomFieldOption } from '@/lib/custom-fields';
import OptionsEditor from './OptionsEditor.vue';

type Field = {
    id: number;
    slug: string;
    label: string;
    type: string;
    options: CustomFieldOption[];
    is_required: boolean;
    sort_order: number;
    editable: boolean;
    values_count: number;
};

const props = defineProps<{
    field: Field;
    isFirst: boolean;
    isLast: boolean;
    saving?: boolean;
}>();

const emit = defineEmits<{
    (
        event: 'update',
        id: number,
        attrs: {
            label?: string;
            is_required?: boolean;
            options?: CustomFieldOption[];
        },
    ): void;
    (event: 'delete', id: number): void;
    (event: 'move', id: number, direction: -1 | 1): void;
}>();

const label = ref(props.field.label);
const options = ref<CustomFieldOption[]>([...props.field.options]);
const confirmingDelete = ref(false);

watch(
    () => props.field,
    (next) => {
        label.value = next.label;
        options.value = [...next.options];
        confirmingDelete.value = false;
    },
);

const optionsDirty = computed(
    () =>
        JSON.stringify(options.value.map((option) => option.label)) !==
        JSON.stringify(props.field.options.map((option) => option.label)),
);

function commitLabel(): void {
    const next = label.value.trim();

    if (next === '' || next === props.field.label) {
        label.value = props.field.label;
        return;
    }

    emit('update', props.field.id, { label: next });
}
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-3">
        <div class="flex items-start gap-2">
            <!-- Reorder. Buttons rather than drag: the order only matters for how
                 the panel stacks the fields, and two clicks beat a drag library. -->
            <div class="flex shrink-0 flex-col">
                <button
                    type="button"
                    :disabled="isFirst || saving"
                    class="flex h-4 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-30"
                    title="Mover para cima"
                    aria-label="Mover para cima"
                    @click="emit('move', field.id, -1)"
                >
                    <ChevronUp class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    :disabled="isLast || saving"
                    class="flex h-4 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-30"
                    title="Mover para baixo"
                    aria-label="Mover para baixo"
                    @click="emit('move', field.id, 1)"
                >
                    <ChevronDown class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="label"
                        type="text"
                        maxlength="60"
                        :disabled="saving"
                        class="h-8 min-w-40 flex-1 rounded-md border border-input bg-background px-2 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-none disabled:opacity-60"
                        @blur="commitLabel"
                        @keydown.enter.prevent="commitLabel"
                    />
                    <span
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                    >
                        {{ customFieldTypeLabel(field.type) }}
                    </span>
                    <span
                        v-if="!field.editable"
                        class="flex shrink-0 items-center gap-1 text-[10px] text-muted-foreground"
                        title="Preenchido pelas ferramentas do agente"
                    >
                        <Lock class="h-3 w-3" />
                        Só leitura
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground"
                >
                    <code class="rounded bg-muted px-1 font-mono">{{
                        field.slug
                    }}</code>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <input
                            type="checkbox"
                            :checked="field.is_required"
                            :disabled="saving"
                            class="h-3.5 w-3.5 rounded border-input"
                            @change="
                                emit('update', field.id, {
                                    is_required: (
                                        $event.target as HTMLInputElement
                                    ).checked,
                                })
                            "
                        />
                        Destacar como obrigatório
                    </label>
                    <span v-if="field.values_count > 0">
                        {{ field.values_count }}
                        {{ field.values_count === 1 ? 'lead' : 'leads' }}
                        preenchido{{ field.values_count === 1 ? '' : 's' }}
                    </span>
                </div>

                <div v-if="field.type === 'select'" class="space-y-1.5">
                    <OptionsEditor v-model="options" :disabled="saving" />
                    <button
                        v-if="optionsDirty"
                        type="button"
                        :disabled="saving"
                        class="h-7 rounded-md bg-primary px-2.5 text-xs font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                        @click="emit('update', field.id, { options })"
                    >
                        Salvar opções
                    </button>
                </div>
            </div>

            <button
                v-if="!confirmingDelete"
                type="button"
                :disabled="saving"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                title="Remover campo"
                aria-label="Remover campo"
                @click="confirmingDelete = true"
            >
                <Trash2 class="h-4 w-4" />
            </button>
        </div>

        <div
            v-if="confirmingDelete"
            class="mt-2 space-y-2 rounded-md border border-destructive/40 bg-destructive/5 p-2.5"
        >
            <p class="text-xs text-destructive">
                Remover “{{ field.label }}”
                <template v-if="field.values_count > 0">
                    apaga o valor preenchido em
                    {{ field.values_count }}
                    {{ field.values_count === 1 ? 'lead' : 'leads' }}.
                </template>
                <template v-else> Nenhum lead preenchido ainda. </template>
                Filtros de lista que usam
                <code class="font-mono">custom_field:{{ field.slug }}</code>
                deixam de encontrar valores.
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    :disabled="saving"
                    class="h-7 rounded-md bg-destructive px-2.5 text-xs font-medium text-white transition-colors hover:opacity-90 disabled:opacity-50"
                    @click="emit('delete', field.id)"
                >
                    Remover
                </button>
                <button
                    type="button"
                    class="h-7 rounded-md border border-input px-2.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                    @click="confirmingDelete = false"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</template>
