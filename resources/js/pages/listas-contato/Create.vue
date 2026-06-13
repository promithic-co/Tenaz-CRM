<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import FilterBuilder from './partials/FilterBuilder.vue';
import { store } from '@/actions/App/Http/Controllers/ContactListController';
import type { BreadcrumbItem } from '@/types';
import type { FiltersJson } from '@/types/filters';

type ListType = 'estatica' | 'dinamica';

const props = defineProps<{
    statuses: Array<{ value: string; label: string }>;
    agents: Array<{ id: number; nome: string }>;
    instances: Array<{ id: number; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Listas de Contato', href: '/listas-contato' },
    { title: 'Nova lista', href: '/listas-contato/create' },
];

// Type toggle state
const listType = ref<ListType>('estatica');
const pendingType = ref<ListType | null>(null);
const confirmDialogOpen = ref(false);

// Form state
const form = useForm({
    nome: '',
    descricao: '',
    is_dynamic: false as boolean,
    filters_json: { version: 1, match: 'all', rules: [] } as FiltersJson,
});

// D-02: Inertia automatically follows the backend redirect.
// Backend redirects dynamic → Show, static → Index with flash success.
// DO NOT call router.visit on success — backend drives the destination.
const onSubmit = () => {
    form.post(store.url());
};

// Determine if the current type has "data" that would be lost on switch
function hasDataForType(type: ListType): boolean {
    if (type === 'estatica') {
        // static fields (nothing to lose on create page — CSV added later)
        return false;
    }
    if (type === 'dinamica') {
        return (form.filters_json.rules as unknown[]).length > 0;
    }
    return false;
}

function tryChangeType(newType: ListType): void {
    if (newType === listType.value) {
        return;
    }
    if (hasDataForType(listType.value)) {
        pendingType.value = newType;
        confirmDialogOpen.value = true;
    } else {
        applyTypeChange(newType);
    }
}

function applyTypeChange(newType: ListType): void {
    listType.value = newType;
    form.is_dynamic = newType === 'dinamica';

    // Reset filters when switching to static
    if (newType === 'estatica') {
        form.filters_json = { version: 1, match: 'all', rules: [] };
    }
}

function confirmTypeChange(): void {
    if (pendingType.value) {
        applyTypeChange(pendingType.value);
        pendingType.value = null;
    }
    confirmDialogOpen.value = false;
}

function cancelTypeChange(): void {
    pendingType.value = null;
    confirmDialogOpen.value = false;
}

const submitLabel = computed(() =>
    listType.value === 'dinamica' ? 'Criar lista dinâmica' : 'Criar lista estática',
);
</script>

<template>
    <Head title="Nova lista de contatos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-4 sm:p-6">
            <!-- Page title -->
            <div>
                <h1 class="text-2xl font-semibold leading-tight text-foreground">
                    Nova lista de contatos
                </h1>
            </div>

            <form @submit.prevent="onSubmit" class="flex flex-col gap-8">
                <!-- Section: common fields -->
                <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Nome -->
                        <div class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                            <Label for="nome">
                                Nome <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="nome"
                                v-model="form.nome"
                                type="text"
                                placeholder="Ex: Leads SIAPE Janeiro"
                                required
                                :aria-invalid="!!form.errors.nome"
                            />
                            <p v-if="form.errors.nome" class="text-xs text-destructive">
                                {{ form.errors.nome }}
                            </p>
                        </div>

                        <!-- Descrição -->
                        <div class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                            <Label for="descricao">Descrição</Label>
                            <Input
                                id="descricao"
                                v-model="form.descricao"
                                type="text"
                                placeholder="Opcional"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section: Tipo de lista -->
                <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 sm:p-6">
                    <h2 class="text-lg font-semibold text-foreground">Tipo de lista</h2>

                    <div class="flex flex-col gap-3 sm:flex-row sm:gap-6">
                        <!-- Estática option -->
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-muted/40"
                            :class="{ 'border-primary bg-primary/5': listType === 'estatica' }"
                        >
                            <input
                                type="radio"
                                name="tipo"
                                value="estatica"
                                :checked="listType === 'estatica'"
                                class="mt-0.5 accent-primary"
                                @change="tryChangeType('estatica')"
                            />
                            <div class="flex flex-col gap-0.5">
                                <span class="text-sm font-semibold text-foreground">Estática — importar CSV ou adicionar manualmente</span>
                                <span class="text-xs text-muted-foreground">
                                    Contatos fixos. Você define a lista uma vez.
                                </span>
                            </div>
                        </label>

                        <!-- Dinâmica option -->
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-muted/40"
                            :class="{ 'border-primary bg-primary/5': listType === 'dinamica' }"
                        >
                            <input
                                type="radio"
                                name="tipo"
                                value="dinamica"
                                :checked="listType === 'dinamica'"
                                class="mt-0.5 accent-primary"
                                @change="tryChangeType('dinamica')"
                            />
                            <div class="flex flex-col gap-0.5">
                                <span class="text-sm font-semibold text-foreground">Dinâmica — filtros que resolvem no disparo</span>
                                <span class="text-xs text-muted-foreground">
                                    Leads calculados automaticamente no momento do envio.
                                </span>
                            </div>
                        </label>
                    </div>

                    <p v-if="form.errors.is_dynamic" class="text-xs text-destructive">
                        {{ form.errors.is_dynamic }}
                    </p>
                </div>

                <!-- Section: FilterBuilder (only for dinâmica) -->
                <div
                    v-if="listType === 'dinamica'"
                    class="rounded-xl border border-border bg-card p-4 sm:p-6"
                >
                    <FilterBuilder
                        v-model="form.filters_json"
                        :statuses="statuses"
                        :agents="agents"
                        :instances="instances"
                    />
                    <p v-if="form.errors.filters_json" class="mt-2 text-xs text-destructive">
                        {{ form.errors.filters_json }}
                    </p>
                </div>

                <!-- Section: Static placeholder (only for estática) -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-10 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        Contatos serão adicionados após criar a lista via importação CSV ou manualmente.
                    </p>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end gap-3">
                    <a
                        href="/listas-contato"
                        class="rounded-md border border-input px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted"
                    >
                        Cancelar
                    </a>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Criando...' : submitLabel }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>

    <!-- Type change confirmation dialog -->
    <Dialog v-model:open="confirmDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Trocar tipo?</DialogTitle>
                <DialogDescription>
                    Os dados preenchidos serão descartados.
                </DialogDescription>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Ao trocar o tipo de lista, os dados preenchidos no modo atual serão perdidos.
                Deseja continuar?
            </p>
            <DialogFooter>
                <Button variant="outline" type="button" @click="cancelTypeChange">
                    Cancelar
                </Button>
                <Button type="button" @click="confirmTypeChange">
                    Confirmar troca
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
