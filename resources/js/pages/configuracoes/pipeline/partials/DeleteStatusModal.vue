<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

type Status = {
    slug: string;
    label: string;
};

const props = defineProps<{
    open: boolean;
    status: Status | null;
    leadCount: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'deleted', slug: string): void;
}>();

function close() {
    emit('update:open', false);
}

function confirmDelete() {
    if (!props.status) return;

    router.delete(`/configuracoes/pipeline/statuses/${props.status.slug}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('deleted', props.status!.slug);
            close();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Deletar Status</DialogTitle>
                <DialogDescription v-if="status">
                    Tem certeza que deseja deletar o status "<strong>{{ status.label }}</strong>"?
                </DialogDescription>
            </DialogHeader>

            <div v-if="leadCount > 0" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                Este status possui <strong>{{ leadCount }}</strong> lead(s) atribuído(s).
                Mova todos os leads para outro status antes de deletar.
            </div>

            <div v-else class="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                Esta ação é irreversível. O status será removido do pipeline.
            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="close">Cancelar</Button>
                <Button
                    v-if="leadCount === 0"
                    variant="destructive"
                    type="button"
                    @click="confirmDelete"
                >
                    Deletar Status
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
