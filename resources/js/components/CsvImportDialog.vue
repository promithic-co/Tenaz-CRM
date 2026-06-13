<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { importCsv } from '@/actions/App/Http/Controllers/ContactListController';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { FileText } from 'lucide-vue-next';

const props = defineProps<{
    listId: number;
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);

const form = useForm({
    file: null as File | null,
});

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        form.file = target.files[0];
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) { return `${bytes} B`; }
    if (bytes < 1024 * 1024) { return `${(bytes / 1024).toFixed(1)} KB`; }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function submitImport(): void {
    form.post(importCsv.url(props.listId), {
        forceFormData: true,
        onSuccess: () => {
            emit('update:open', false);
            selectedFile.value = null;
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function handleClose(value: boolean): void {
    if (!value) {
        selectedFile.value = null;
        form.reset();
        form.clearErrors();
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
    emit('update:open', value);
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Importar CSV</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitImport" class="flex flex-col gap-4">
                <p class="text-sm text-muted-foreground">
                    O arquivo deve ter uma coluna <strong>telefone</strong> (ou <strong>phone</strong>) e opcionalmente <strong>nome</strong> (ou <strong>name</strong>).
                </p>

                <!-- File input area -->
                <div
                    class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-input p-6 transition-colors hover:border-primary/50 hover:bg-muted/30"
                    @click="fileInput?.click()"
                >
                    <FileText class="h-8 w-8 text-muted-foreground/50" />
                    <div v-if="selectedFile" class="text-center">
                        <p class="text-sm font-medium text-foreground">{{ selectedFile.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedFile.size) }}</p>
                    </div>
                    <div v-else class="text-center">
                        <p class="text-sm text-muted-foreground">Clique para selecionar um arquivo</p>
                        <p class="text-xs text-muted-foreground">.csv ou .txt</p>
                    </div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.txt"
                        class="hidden"
                        @change="onFileChange"
                    />
                </div>

                <p v-if="form.errors.file" class="text-xs text-red-500">{{ form.errors.file }}</p>

                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                        @click="handleClose(false)"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || !selectedFile"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Importando...' : 'Importar' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
