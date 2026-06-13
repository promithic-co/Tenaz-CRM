<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

const COLORS = ['gray', 'red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink'] as const;

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

const props = defineProps<{
    open: boolean;
}>();

type CreatedStatus = {
    slug: string;
    label: string;
    color: string;
    is_terminal: boolean;
    is_canonical: boolean;
    position: number;
};

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'created', status: CreatedStatus): void;
}>();

const form = useForm({
    name: '',
    color: 'gray' as string,
});

const slugPreview = computed(() => {
    return form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
});

function close() {
    emit('update:open', false);
    form.reset();
}

function submit() {
    form.post('/configuracoes/pipeline/statuses', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            emit('created', ((page as any).props?.status ?? {}) as CreatedStatus);
            close();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Adicionar Status</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <!-- Name -->
                <div class="space-y-1">
                    <Label for="status-name">Nome</Label>
                    <Input
                        id="status-name"
                        v-model="form.name"
                        placeholder="Ex: Aguardando Documento"
                        autofocus
                    />
                    <p v-if="slugPreview" class="text-xs text-muted-foreground">
                        Slug: <code class="font-mono">{{ slugPreview }}</code>
                    </p>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Color -->
                <div class="space-y-1">
                    <Label>Cor</Label>
                    <div class="flex gap-2">
                        <button
                            v-for="c in COLORS"
                            :key="c"
                            type="button"
                            :class="[
                                'size-5 rounded-full transition-transform',
                                colorDot[c],
                                form.color === c ? 'ring-2 ring-offset-1 ring-foreground/40 scale-125' : 'hover:scale-110',
                            ]"
                            :title="c"
                            @click="form.color = c"
                        />
                    </div>
                </div>

            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="close">Cancelar</Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    @click="submit"
                >
                    {{ form.processing ? 'Adicionando...' : 'Adicionar Status' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
