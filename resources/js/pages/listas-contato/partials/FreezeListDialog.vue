<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { freeze } from '@/actions/App/Http/Controllers/ContactListController';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    open: boolean;
    listId: number;
    count: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
}>();

const processing = ref(false);
const errorMessage = ref<string | null>(null);

const onConfirm = () => {
    processing.value = true;
    errorMessage.value = null;
    router.post(
        freeze.url({ list: props.listId }),
        {},
        {
            preserveScroll: true,
            // Backend flashes 'success' ('Lista congelada — X leads no snapshot.').
            // Show.vue flashSuccess banner renders it after reload.
            onSuccess: () => {
                emit('update:open', false);
                router.reload({ only: ['list', 'filterChips', 'flash'] });
            },
            onError: () => {
                errorMessage.value = 'Erro ao atualizar a lista. Veja os logs ou tente de novo.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Congelar lista?</DialogTitle>
                <DialogDescription>
                    Essa lista vai virar estática com {{ count }} leads no snapshot atual.
                    Filtros serão descartados e não atualizam mais automaticamente.
                    Não dá pra reverter.
                </DialogDescription>
            </DialogHeader>

            <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>

            <DialogFooter>
                <Button variant="outline" type="button" @click="emit('update:open', false)">Cancelar</Button>
                <Button :disabled="processing" type="button" @click="onConfirm">Congelar agora</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
