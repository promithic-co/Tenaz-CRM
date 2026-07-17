<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import type { CollectedInformationItem } from '@/types/models';
import type { RouteDefinition } from '@/wayfinder';

const props = withDefaults(
    defineProps<{
        items: CollectedInformationItem[];
        action: RouteDefinition<'patch'>;
        canEdit?: boolean;
        compact?: boolean;
    }>(),
    {
        canEdit: false,
        compact: false,
    },
);

const editing = ref(false);
const form = useForm({
    operation: 'upsert' as 'upsert' | 'delete',
    key: null as string | null,
    label: '',
    value: '',
});
const deleteForm = useForm({
    operation: 'delete' as 'upsert' | 'delete',
    key: '',
});

function startCreate(): void {
    form.reset();
    form.clearErrors();
    form.operation = 'upsert';
    form.key = null;
    editing.value = true;
}

function startEdit(item: CollectedInformationItem): void {
    form.clearErrors();
    form.operation = 'upsert';
    form.key = item.key;
    form.label = item.label;
    form.value = item.value;
    editing.value = true;
}

function cancelEdit(): void {
    editing.value = false;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    form.patch(props.action.url, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

function remove(item: CollectedInformationItem): void {
    deleteForm.key = item.key;
    deleteForm.patch(props.action.url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2">
            <p
                class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Informações do atendimento
            </p>
            <button
                v-if="canEdit && !editing"
                type="button"
                class="inline-flex h-6 items-center gap-1 rounded-md px-1.5 text-[10px] font-medium text-primary transition-colors hover:bg-primary/10"
                @click="startCreate"
            >
                <Plus class="h-3 w-3" />
                Adicionar
            </button>
        </div>

        <form
            v-if="editing"
            class="mt-2 space-y-2 rounded-md border border-primary/25 bg-primary/5 p-2"
            @submit.prevent="submit"
        >
            <input
                v-model="form.label"
                type="text"
                maxlength="60"
                placeholder="Ex.: Melhor horário"
                class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:border-primary"
            />
            <textarea
                v-model="form.value"
                maxlength="500"
                rows="2"
                placeholder="Informação sobre o atendimento"
                class="w-full resize-y rounded-md border border-input bg-background px-2 py-1.5 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:border-primary"
            />
            <p
                v-if="form.errors.label || form.errors.value"
                class="text-[10px] text-destructive"
            >
                {{ form.errors.label || form.errors.value }}
            </p>
            <div class="flex justify-end gap-1">
                <button
                    type="button"
                    class="inline-flex h-7 items-center gap-1 rounded-md px-2 text-[10px] text-muted-foreground hover:bg-muted"
                    @click="cancelEdit"
                >
                    <X class="h-3 w-3" />
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-7 items-center gap-1 rounded-md bg-primary px-2 text-[10px] font-medium text-primary-foreground disabled:opacity-50"
                >
                    <Check class="h-3 w-3" />
                    {{ form.processing ? 'Salvando...' : 'Salvar' }}
                </button>
            </div>
        </form>

        <div
            v-else-if="items.length"
            class="mt-2 space-y-1.5 overflow-y-auto pr-0.5"
            :class="compact ? 'max-h-28' : 'max-h-56'"
        >
            <div
                v-for="item in items"
                :key="item.key"
                class="group rounded-md border border-border/60 bg-muted/25 px-2 py-1.5"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-medium text-muted-foreground"
                        >
                            {{ item.label }}
                        </p>
                        <p class="text-xs break-words text-foreground">
                            {{ item.value }}
                        </p>
                    </div>
                    <div
                        v-if="canEdit"
                        class="flex shrink-0 items-center gap-0.5"
                    >
                        <button
                            type="button"
                            class="rounded p-1 text-muted-foreground opacity-70 transition group-hover:opacity-100 hover:bg-muted hover:text-foreground"
                            :aria-label="`Editar ${item.label}`"
                            @click="startEdit(item)"
                        >
                            <Pencil class="h-3 w-3" />
                        </button>
                        <button
                            type="button"
                            :disabled="deleteForm.processing"
                            class="rounded p-1 text-muted-foreground opacity-70 transition group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive disabled:opacity-40"
                            :aria-label="`Remover ${item.label}`"
                            @click="remove(item)"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p
            v-else
            class="mt-2 rounded-md border border-dashed border-border px-2 py-3 text-center text-[11px] text-muted-foreground"
        >
            Nenhuma informação registrada.
        </p>
    </div>
</template>
