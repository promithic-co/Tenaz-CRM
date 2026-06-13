<script setup lang="ts">
import { watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { updateFilters } from '@/actions/App/Http/Controllers/ContactListController';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import FilterBuilder from './FilterBuilder.vue';
import type { FiltersJson } from '@/types/filters';

const props = defineProps<{
    open: boolean;
    listId: number;
    initialFilters: FiltersJson;
    statuses: Array<{ value: string; label: string }>;
    agents: Array<{ id: number; nome: string }>;
    instances: Array<{ id: number; label: string }>;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'saved'): void;
}>();

const form = useForm({ filters_json: props.initialFilters });

// Reset form state when dialog reopened
watch(
    () => props.open,
    (v) => {
        if (v) {
            form.filters_json = JSON.parse(JSON.stringify(props.initialFilters));
            form.clearErrors();
        }
    },
);

const onSave = () => {
    form.patch(updateFilters.url({ list: props.listId }), {
        preserveScroll: true,
        // Backend flashes 'success' → Show.vue flashSuccess banner renders the message.
        onSuccess: () => {
            emit('saved');
            emit('update:open', false);
            router.reload({ only: ['list', 'filterChips', 'flash'] });
        },
        // Errors surface via form.errors automatically — no client toast needed.
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-5xl w-full p-6 md:max-w-5xl max-md:w-full max-md:h-full">
            <DialogHeader>
                <DialogTitle>Editar filtros da lista</DialogTitle>
                <DialogDescription>
                    Alterações são salvas sem disparar a campanha. A lista resolve no próximo dispatch.
                </DialogDescription>
            </DialogHeader>

            <FilterBuilder
                v-model="form.filters_json"
                :statuses="statuses"
                :agents="agents"
                :instances="instances"
            />

            <!-- Inline error surface — no toast library -->
            <p v-if="form.hasErrors" class="text-sm text-destructive">
                Não foi possível salvar os filtros. Tente de novo.
            </p>

            <DialogFooter>
                <Button variant="outline" type="button" @click="emit('update:open', false)">Cancelar</Button>
                <Button :disabled="form.processing" type="button" @click="onSave">Salvar filtros</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
