<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Phone } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import VoiceInstanceController from '@/actions/App/Http/Controllers/VoiceInstanceController';

type WhatsappInstance = { id: number; name: string; display_name: string | null };

type VoiceInstance = {
    id: number;
    name: string;
    display_name: string | null;
    whatsapp_instance_id: number | null;
    greeting_template: string | null;
    post_call_message: string | null;
    active: boolean;
    whatsapp_instance: WhatsappInstance | null;
};

type Props = {
    instances: VoiceInstance[];
    whatsappInstances: WhatsappInstance[];
    twilioPhoneNumber: string | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Voz', href: '/voz' },
    { title: 'Instâncias de Voz', href: '/voz' },
];

// Create form
const showCreateDialog = ref(false);

const createForm = useForm({
    name: '',
    display_name: '',
    whatsapp_instance_id: '' as string | number,
    greeting_template: '',
    post_call_message: '',
    active: true,
});

function submitCreate(): void {
    createForm.post(VoiceInstanceController.store().url, {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
    });
}

// Edit form
const editingInstance = ref<VoiceInstance | null>(null);

const editForm = useForm({
    name: '',
    display_name: '',
    whatsapp_instance_id: '' as string | number,
    greeting_template: '',
    post_call_message: '',
    active: true,
});

function openEdit(instance: VoiceInstance): void {
    editingInstance.value = instance;
    editForm.name = instance.name;
    editForm.display_name = instance.display_name ?? '';
    editForm.whatsapp_instance_id = instance.whatsapp_instance_id ?? '';
    editForm.greeting_template = instance.greeting_template ?? '';
    editForm.post_call_message = instance.post_call_message ?? '';
    editForm.active = instance.active;
}

function submitEdit(): void {
    if (!editingInstance.value) { return; }
    editForm.put(VoiceInstanceController.update(editingInstance.value.id).url, {
        onSuccess: () => {
            editingInstance.value = null;
        },
    });
}

// Delete
const deleteConfirmId = ref<number | null>(null);
const deleteForm = useForm({});

function deleteInstance(): void {
    if (deleteConfirmId.value === null) { return; }
    deleteForm.delete(VoiceInstanceController.destroy(deleteConfirmId.value).url, {
        onSuccess: () => { deleteConfirmId.value = null; },
    });
}
</script>

<template>
    <Head title="Instâncias de Voz" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Instâncias de Voz</span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ instances.length }}</span>
                        <div
                            v-if="props.twilioPhoneNumber"
                            class="flex items-center gap-2 rounded-md bg-muted/60 px-3 py-1.5 text-xs text-muted-foreground"
                        >
                            <Phone class="h-3.5 w-3.5" />
                            <span>Número de saída: <span class="font-mono font-medium text-foreground">{{ props.twilioPhoneNumber }}</span></span>
                        </div>
                    </div>
                    <button
                        class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="showCreateDialog = true"
                    >
                        + Nova Instância
                    </button>
                </div>

                <!-- Table -->
                <table class="w-full text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">WhatsApp Vinculado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground">Status</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="instance in instances"
                            :key="instance.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ instance.display_name ?? instance.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ instance.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ instance.whatsapp_instance?.display_name ?? instance.whatsapp_instance?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        instance.active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ instance.active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        @click="openEdit(instance)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        class="rounded px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                        @click="deleteConfirmId = instance.id"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="instances.length === 0"
                    :icon="Phone"
                    title="Nenhuma instância de voz"
                    description="Crie um perfil de voz para configurar templates e iniciar campanhas de ligação."
                >
                    <button
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="showCreateDialog = true"
                    >
                        Nova Instância
                    </button>
                </EmptyState>
            </div>
        </div>
    </AppLayout>

    <!-- Create Dialog -->
    <Dialog :open="showCreateDialog" @update:open="(v) => { if (!v) showCreateDialog = false; }">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Nova Instância de Voz</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-foreground">Nome (identificador) <span class="text-red-500">*</span></label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="Ex: twilio-principal"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-500">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-foreground">Nome exibido</label>
                        <input
                            v-model="createForm.display_name"
                            type="text"
                            placeholder="Ex: Twilio Principal"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Instância WhatsApp vinculada</label>
                    <select
                        v-model="createForm.whatsapp_instance_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Nenhuma</option>
                        <option v-for="wa in whatsappInstances" :key="wa.id" :value="wa.id">
                            {{ wa.display_name ?? wa.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Template de saudação</label>
                    <textarea
                        v-model="createForm.greeting_template"
                        rows="2"
                        placeholder="Olá {nome}, aqui é a Tenaz CRM..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="createForm.errors.greeting_template" class="mt-1 text-xs text-red-500">{{ createForm.errors.greeting_template }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Mensagem pós-ligação (WhatsApp)</label>
                    <textarea
                        v-model="createForm.post_call_message"
                        rows="2"
                        placeholder="Olá {nome}, conforme falamos no telefone..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="createForm.active"
                        type="checkbox"
                        id="create-active"
                        class="rounded border-input text-primary focus:ring-primary"
                    />
                    <label for="create-active" class="text-sm text-foreground">Instância ativa</label>
                </div>
            </form>

            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    @click="showCreateDialog = false"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="createForm.processing"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    @click="submitCreate"
                >
                    {{ createForm.processing ? 'Criando...' : 'Criar Instância' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Dialog -->
    <Dialog :open="editingInstance !== null" @update:open="(v) => { if (!v) editingInstance = null; }">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Editar Instância de Voz</DialogTitle>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-foreground">Nome (identificador) <span class="text-red-500">*</span></label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-foreground">Nome exibido</label>
                        <input
                            v-model="editForm.display_name"
                            type="text"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Instância WhatsApp vinculada</label>
                    <select
                        v-model="editForm.whatsapp_instance_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option value="">Nenhuma</option>
                        <option v-for="wa in whatsappInstances" :key="wa.id" :value="wa.id">
                            {{ wa.display_name ?? wa.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Template de saudação</label>
                    <textarea
                        v-model="editForm.greeting_template"
                        rows="2"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Mensagem pós-ligação (WhatsApp)</label>
                    <textarea
                        v-model="editForm.post_call_message"
                        rows="2"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="editForm.active"
                        type="checkbox"
                        id="edit-active"
                        class="rounded border-input text-primary focus:ring-primary"
                    />
                    <label for="edit-active" class="text-sm text-foreground">Instância ativa</label>
                </div>
            </form>

            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    @click="editingInstance = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="editForm.processing"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    @click="submitEdit"
                >
                    {{ editForm.processing ? 'Salvando...' : 'Salvar Alterações' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirm Dialog -->
    <Dialog :open="deleteConfirmId !== null" @update:open="(v) => { if (!v) deleteConfirmId = null; }">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Excluir Instância</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">Tem certeza que deseja excluir esta instância de voz? Esta ação não pode ser desfeita.</p>
            <DialogFooter>
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    @click="deleteConfirmId = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="deleteForm.processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    @click="deleteInstance"
                >
                    {{ deleteForm.processing ? 'Excluindo...' : 'Excluir' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
