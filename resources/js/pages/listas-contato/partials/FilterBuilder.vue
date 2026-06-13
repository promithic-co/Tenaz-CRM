<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { useFilterPreview } from '@/composables/useFilterPreview';
import FilterPreview from './FilterPreview.vue';
import RuleRow from './RuleRow.vue';
import type { FilterRule, FiltersJson } from '@/types/filters';

const props = defineProps<{
    modelValue: FiltersJson;
    statuses: Array<{ value: string; label: string }>;
    agents: Array<{ id: number; nome: string }>;
    instances: Array<{ id: number; label: string }>;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: FiltersJson): void;
}>();

// Reactive ref for the composable (wraps modelValue)
const filtersRef = computed(() => props.modelValue);

const previewState = useFilterPreview(filtersRef);

function addRule(): void {
    const newRule: FilterRule = { field: 'status', op: 'in', value: [] };
    emit('update:modelValue', {
        ...props.modelValue,
        rules: [...props.modelValue.rules, newRule],
    });
}

function updateRule(index: number, rule: FilterRule): void {
    const rules = [...props.modelValue.rules];
    rules[index] = rule;
    emit('update:modelValue', { ...props.modelValue, rules });
}

function removeRule(index: number): void {
    const rules = props.modelValue.rules.filter((_, i) => i !== index);
    emit('update:modelValue', { ...props.modelValue, rules });
}
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left column: Filters -->
        <div class="flex flex-col gap-4">
            <h3 class="text-lg font-semibold text-foreground">Filtros</h3>

            <!-- Match: fixed AND-only (Phase 51 scope) -->
            <p class="text-xs text-muted-foreground">Todos os filtros devem ser atendidos (E).</p>

            <!-- Rule rows -->
            <div
                v-if="modelValue.rules.length > 0"
                class="flex flex-col space-y-3"
            >
                <RuleRow
                    v-for="(rule, idx) in modelValue.rules"
                    :key="idx"
                    :rule="rule"
                    :index="idx"
                    :statuses="statuses"
                    :agents="agents"
                    :instances="instances"
                    @update="(r) => updateRule(idx, r)"
                    @remove="removeRule(idx)"
                />
            </div>

            <div v-else class="py-3 text-sm text-muted-foreground">
                Nenhum filtro adicionado. Clique no botão abaixo para começar.
            </div>

            <!-- Add filter button -->
            <div>
                <Button variant="outline" type="button" @click="addRule">
                    + Adicionar filtro
                </Button>
            </div>
        </div>

        <!-- Right column: Preview (sticky on lg) -->
        <div class="lg:sticky lg:top-6 lg:self-start">
            <FilterPreview
                :state="previewState"
                :rules-count="modelValue.rules.length"
            />
        </div>
    </div>
</template>
