<script setup lang="ts">
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Button from '@/components/ui/button/Button.vue';
import TagInput from '@/components/TagInput.vue';
import type { FilterField, FilterOp, FilterRule, FilterValue } from '@/types/filters';

interface Tag {
    id: number;
    name: string;
    slug: string;
    color?: string | null;
    is_hot?: boolean;
}

const props = defineProps<{
    rule: FilterRule;
    index: number;
    statuses: Array<{ value: string; label: string }>;
    agents: Array<{ id: number; nome: string }>;
    instances: Array<{ id: number; label: string }>;
}>();

const emit = defineEmits<{
    (e: 'update', rule: FilterRule): void;
    (e: 'remove'): void;
}>();

// Field label map (51-UI-SPEC verbatim)
const FIELD_LABELS: Record<FilterField, string> = {
    tags: 'Tags',
    tag_is_hot: 'Tag quente (🔥)',
    tag_source: 'Origem da tag',
    status: 'Status do lead',
    agent_id: 'Agente IA',
    whatsapp_instance_id: 'Instância WhatsApp',
    last_interaction_at: 'Última interação',
    created_at: 'Data de criação',
    has_open_ticket: 'Tem ticket aberto',
};

// Operator label map (51-UI-SPEC verbatim)
const OP_LABELS: Record<FilterOp, string> = {
    includes_all: 'tem todas',
    includes_any: 'tem qualquer uma',
    excludes: 'não tem nenhuma',
    eq: 'é',
    ne: 'não é',
    in: 'está em',
    not_in: 'não está em',
    older_than_days: 'há mais de',
    within_last_days: 'nos últimos',
    contains: 'contém',
};

// Ops available per field
const FIELD_OPS: Record<FilterField, FilterOp[]> = {
    tags: ['includes_all', 'includes_any', 'excludes'],
    tag_is_hot: ['eq'],
    tag_source: ['eq'],
    status: ['in', 'not_in'],
    agent_id: ['eq'],
    whatsapp_instance_id: ['eq'],
    last_interaction_at: ['older_than_days', 'within_last_days'],
    created_at: ['older_than_days', 'within_last_days'],
    has_open_ticket: ['eq'],
};

const availableOps = computed(() => FIELD_OPS[props.rule.field] ?? ['eq']);

const defaultValueForField = (field: FilterField): FilterValue => {
    switch (field) {
        case 'tags':
            return [];
        case 'tag_is_hot':
        case 'has_open_ticket':
            return true;
        case 'tag_source':
            return 'manual';
        case 'status':
            return [];
        case 'agent_id':
        case 'whatsapp_instance_id':
            return null;
        case 'last_interaction_at':
        case 'created_at':
            return 30;
        default:
            return null;
    }
};

function onFieldChange(newField: string): void {
    const field = newField as FilterField;
    const ops = FIELD_OPS[field] ?? ['eq'];
    emit('update', {
        field,
        op: ops[0],
        value: defaultValueForField(field),
    });
}

function onOpChange(newOp: string): void {
    emit('update', { ...props.rule, op: newOp as FilterOp });
}

function onValueChange(newValue: FilterValue): void {
    emit('update', { ...props.rule, value: newValue });
}

// Days suffix fields
const hasDaysSuffix = computed(() =>
    props.rule.op === 'older_than_days' || props.rule.op === 'within_last_days',
);

// Tag source options
const tagSourceOptions = [
    { value: 'manual', label: 'Manual' },
    { value: 'ai', label: 'IA' },
    { value: 'import', label: 'Importação' },
    { value: 'system', label: 'Sistema' },
];
</script>

<template>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_2fr_auto] sm:gap-4 items-start">
        <!-- Field select -->
        <Select :model-value="rule.field" @update:model-value="(v) => onFieldChange(v as string)">
            <SelectTrigger class="h-9 w-full">
                <SelectValue :placeholder="FIELD_LABELS[rule.field]" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="(label, key) in FIELD_LABELS"
                    :key="key"
                    :value="key"
                >
                    {{ label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Operator select -->
        <Select :model-value="rule.op" @update:model-value="(v) => onOpChange(v as string)">
            <SelectTrigger class="h-9 w-full">
                <SelectValue :placeholder="OP_LABELS[rule.op]" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="op in availableOps" :key="op" :value="op">
                    {{ OP_LABELS[op] }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Value control (varies by field) -->
        <div class="flex items-center gap-2">
            <!-- tags → TagInput -->
            <TagInput
                v-if="rule.field === 'tags'"
                :model-value="(rule.value as Tag[])"
                placeholder="Buscar tags…"
                @update:model-value="onValueChange"
            />

            <!-- tag_is_hot → Checkbox -->
            <div
                v-else-if="rule.field === 'tag_is_hot'"
                class="flex items-center gap-2"
            >
                <Checkbox
                    :id="`rule-hot-${index}`"
                    :checked="Boolean(rule.value)"
                    @update:checked="onValueChange"
                />
                <Label :for="`rule-hot-${index}`" class="text-sm">Tag quente (🔥)</Label>
            </div>

            <!-- tag_source → Select -->
            <Select
                v-else-if="rule.field === 'tag_source'"
                :model-value="String(rule.value ?? 'manual')"
                @update:model-value="(v) => onValueChange(String(v))"
            >
                <SelectTrigger class="h-9 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="opt in tagSourceOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- status → multi-select via native select (multiple) or simple select -->
            <Select
                v-else-if="rule.field === 'status'"
                :model-value="Array.isArray(rule.value) ? (rule.value as string[])[0] ?? '' : String(rule.value ?? '')"
                @update:model-value="(v) => onValueChange([String(v)])"
            >
                <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Selecionar status…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="s in statuses" :key="s.value" :value="s.value">
                        {{ s.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- agent_id → Select -->
            <Select
                v-else-if="rule.field === 'agent_id'"
                :model-value="rule.value !== null ? String(rule.value) : ''"
                @update:model-value="(v) => onValueChange(Number(v))"
            >
                <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Selecionar agente…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="a in agents" :key="a.id" :value="String(a.id)">
                        {{ a.nome }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- whatsapp_instance_id → Select -->
            <Select
                v-else-if="rule.field === 'whatsapp_instance_id'"
                :model-value="rule.value !== null ? String(rule.value) : ''"
                @update:model-value="(v) => onValueChange(Number(v))"
            >
                <SelectTrigger class="h-9 w-full">
                    <SelectValue placeholder="Selecionar instância…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="inst in instances" :key="inst.id" :value="String(inst.id)">
                        {{ inst.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- last_interaction_at / created_at → number input with day suffix -->
            <template
                v-else-if="rule.field === 'last_interaction_at' || rule.field === 'created_at'"
            >
                <Input
                    type="number"
                    min="1"
                    max="3650"
                    class="h-9 w-24"
                    :model-value="Number(rule.value ?? 30)"
                    @update:model-value="(v) => onValueChange(Number(v))"
                />
                <span v-if="hasDaysSuffix" class="text-sm text-muted-foreground whitespace-nowrap">
                    dias
                </span>
            </template>

            <!-- has_open_ticket → Checkbox -->
            <div
                v-else-if="rule.field === 'has_open_ticket'"
                class="flex items-center gap-2"
            >
                <Checkbox
                    :id="`rule-ticket-${index}`"
                    :checked="Boolean(rule.value)"
                    @update:checked="onValueChange"
                />
                <Label :for="`rule-ticket-${index}`" class="text-sm">Tem ticket aberto</Label>
            </div>

            <!-- Fallback -->
            <Input
                v-else
                class="h-9 w-full"
                :model-value="String(rule.value ?? '')"
                @update:model-value="onValueChange"
            />
        </div>

        <!-- Remove button -->
        <Button
            variant="ghost"
            size="icon"
            type="button"
            :aria-label="`Remover filtro ${index + 1}`"
            class="hover:text-destructive mt-0 h-9 w-9 shrink-0"
            @click="$emit('remove')"
        >
            <Trash2 class="size-4" />
            <span class="sr-only">Remover filtro</span>
        </Button>
    </div>
</template>
