<script setup lang="ts">
import { ref, computed } from 'vue';
import { Lock, GripVertical, Trash2 } from 'lucide-vue-next';

type Status = {
    slug: string;
    label: string;
    color: string;
    is_terminal: boolean;
    is_canonical: boolean;
    position: number;
};

const COLORS = ['gray', 'red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink'] as const;

const colorBg: Record<string, string> = {
    gray: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    pink: 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300',
};

const colorDot: Record<string, string> = {
    gray: 'bg-gray-400',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-400',
    green: 'bg-emerald-500',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
};

type Props = {
    status: Status;
    leadCount?: number;
    editable?: boolean;
    saving?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    leadCount: 0,
    editable: true,
    saving: false,
});

const emit = defineEmits<{
    (e: 'update', slug: string, attrs: Partial<Status>): void;
    (e: 'delete', slug: string): void;
}>();

const editing = ref(false);
const editLabel = ref(props.status.label);

function startEdit() {
    if (!props.editable) return;
    editLabel.value = props.status.label;
    editing.value = true;
}

function commitEdit() {
    editing.value = false;
    const trimmed = editLabel.value.trim();
    if (trimmed && trimmed !== props.status.label) {
        emit('update', props.status.slug, { label: trimmed });
    }
}

function cancelEdit() {
    editing.value = false;
    editLabel.value = props.status.label;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') { commitEdit(); }
    if (event.key === 'Escape') { cancelEdit(); }
}

function selectColor(color: string) {
    if (props.status.color === color) return;
    emit('update', props.status.slug, { color });
}

const canDelete = computed(() => !props.status.is_canonical && (props.leadCount ?? 0) === 0 && props.editable);

const badgeClasses = computed(() => colorBg[props.status.color] ?? colorBg.gray);
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2.5 transition-colors hover:bg-muted/30">
        <!-- Drag handle -->
        <button
            v-if="editable"
            type="button"
            class="cursor-grab touch-none text-muted-foreground/50 hover:text-muted-foreground active:cursor-grabbing"
            aria-label="Reordenar"
        >
            <GripVertical class="size-4" />
        </button>

        <!-- Color badge + label -->
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', badgeClasses]">
                <template v-if="!editing">{{ status.label }}</template>
                <input
                    v-else
                    v-model="editLabel"
                    class="min-w-0 bg-transparent text-xs font-medium outline-none"
                    :style="{ width: Math.max(editLabel.length, 4) + 'ch' }"
                    @blur="commitEdit"
                    @keydown="onKeydown"
                    autofocus
                />
            </span>

            <!-- Edit trigger -->
            <button
                v-if="editable && !editing"
                type="button"
                class="text-[11px] text-muted-foreground/60 hover:text-foreground"
                @click="startEdit"
                title="Renomear label"
            >
                editar
            </button>

            <!-- System badge for canonical -->
            <span
                v-if="status.is_canonical"
                class="inline-flex items-center gap-0.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                :title="'Slug: ' + status.slug + ' — usado pela IA, não pode ser renomeado'"
            >
                <Lock class="size-2.5" />
                Sistema
            </span>
        </div>

        <!-- Lead count -->
        <span class="min-w-[2rem] text-right text-xs text-muted-foreground">
            {{ leadCount }} lead{{ leadCount !== 1 ? 's' : '' }}
        </span>

        <!-- Color picker -->
        <div v-if="editable" class="flex items-center gap-1">
            <button
                v-for="c in COLORS"
                :key="c"
                type="button"
                :class="[
                    'size-3.5 rounded-full transition-transform',
                    colorDot[c],
                    status.color === c ? 'ring-2 ring-offset-1 ring-foreground/40 scale-125' : 'hover:scale-110',
                ]"
                :title="c"
                @click="selectColor(c)"
            />
        </div>
        <!-- Delete button (only custom + no leads) -->
        <button
            v-if="canDelete"
            type="button"
            class="text-muted-foreground/50 transition-colors hover:text-destructive"
            :title="'Deletar status ' + status.slug"
            @click="emit('delete', status.slug)"
        >
            <Trash2 class="size-4" />
        </button>

        <!-- Saving indicator -->
        <span v-if="saving" class="text-xs text-muted-foreground animate-pulse">...</span>
    </div>
</template>
