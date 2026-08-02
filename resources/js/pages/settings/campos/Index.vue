<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    CUSTOM_FIELD_TYPE_HINTS,
    CUSTOM_FIELD_TYPE_LABELS,
} from '@/lib/custom-fields';
import type { CustomFieldOption, CustomFieldType } from '@/lib/custom-fields';
import {
    destroy as destroyField,
    reorder as reorderFields,
    store as storeField,
    update as updateField,
} from '@/routes/settings/campos';
import type { BreadcrumbItem } from '@/types';
import CustomFieldRow from './partials/CustomFieldRow.vue';
import OptionsEditor from './partials/OptionsEditor.vue';

type Field = {
    id: number;
    slug: string;
    label: string;
    type: string;
    options: CustomFieldOption[];
    is_required: boolean;
    sort_order: number;
    editable: boolean;
    values_count: number;
};

type Props = {
    fields: Field[];
    types: CustomFieldType[];
    max_fields: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'Campos adicionais', href: '/settings/campos' },
];

const savingId = ref<number | null>(null);
const showCreateForm = ref(false);

const atLimit = computed(() => props.fields.length >= props.max_fields);

const createForm = useForm<{
    label: string;
    type: CustomFieldType;
    is_required: boolean;
    options: CustomFieldOption[];
}>({
    label: '',
    type: 'text',
    is_required: false,
    options: [],
});

function submitCreate(): void {
    createForm
        .transform((data) => ({
            ...data,
            options: data.type === 'select' ? data.options : [],
        }))
        .post(storeField.url(), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                showCreateForm.value = false;
            },
        });
}

function update(
    id: number,
    attrs: {
        label?: string;
        is_required?: boolean;
        options?: CustomFieldOption[];
    },
): void {
    savingId.value = id;

    router.patch(updateField.url({ customField: id }), attrs, {
        preserveScroll: true,
        onFinish: () => {
            savingId.value = null;
        },
    });
}

function remove(id: number): void {
    savingId.value = id;

    router.delete(destroyField.url({ customField: id }), {
        preserveScroll: true,
        onFinish: () => {
            savingId.value = null;
        },
    });
}

/**
 * Reorder sends the whole id list rather than a single position, so the server
 * never has to reconcile two clients that moved different rows.
 */
function move(id: number, direction: -1 | 1): void {
    const ids = props.fields.map((field) => field.id);
    const from = ids.indexOf(id);
    const to = from + direction;

    if (from === -1 || to < 0 || to >= ids.length) {
        return;
    }

    ids.splice(to, 0, ...ids.splice(from, 1));
    savingId.value = id;

    router.post(
        reorderFields.url(),
        { ids },
        {
            preserveScroll: true,
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Campos adicionais" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout wide>
            <div class="max-w-3xl space-y-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h1 class="text-lg font-semibold text-foreground">
                            Campos adicionais
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Informações que a sua operação precisa guardar de
                            cada lead. Aparecem no painel da conversa para a
                            equipe preencher.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="atLimit"
                        class="flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                        @click="showCreateForm = !showCreateForm"
                    >
                        <Plus class="h-4 w-4" />
                        Novo campo
                    </button>
                </div>

                <p
                    v-if="atLimit"
                    class="rounded-lg border border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground"
                >
                    Você atingiu o limite de {{ max_fields }} campos. Remova um
                    campo antes de criar outro.
                </p>

                <!-- Create form -->
                <form
                    v-if="showCreateForm && !atLimit"
                    class="space-y-3 rounded-lg border border-border bg-card p-4"
                    @submit.prevent="submitCreate"
                >
                    <div class="space-y-1.5">
                        <label
                            for="new-field-label"
                            class="text-xs font-medium text-foreground"
                        >
                            Nome do campo
                        </label>
                        <input
                            id="new-field-label"
                            v-model="createForm.label"
                            type="text"
                            maxlength="60"
                            placeholder="Ex.: Renda mensal"
                            class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        />
                        <p
                            v-if="createForm.errors.label"
                            class="text-xs text-rose-500"
                        >
                            {{ createForm.errors.label }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new-field-type"
                            class="text-xs font-medium text-foreground"
                        >
                            Tipo
                        </label>
                        <select
                            id="new-field-type"
                            v-model="createForm.type"
                            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm text-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                        >
                            <option
                                v-for="type in types"
                                :key="type"
                                :value="type"
                            >
                                {{ CUSTOM_FIELD_TYPE_LABELS[type] }}
                            </option>
                        </select>
                        <p class="text-[11px] text-muted-foreground">
                            {{ CUSTOM_FIELD_TYPE_HINTS[createForm.type] }}
                            O tipo não muda depois de criado.
                        </p>
                    </div>

                    <div
                        v-if="createForm.type === 'select'"
                        class="space-y-1.5"
                    >
                        <span class="text-xs font-medium text-foreground"
                            >Opções</span
                        >
                        <OptionsEditor
                            v-model="createForm.options"
                            :error="createForm.errors.options"
                        />
                    </div>

                    <label
                        class="flex cursor-pointer items-center gap-2 text-xs text-foreground"
                    >
                        <input
                            v-model="createForm.is_required"
                            type="checkbox"
                            class="h-3.5 w-3.5 rounded border-input"
                        />
                        Destacar como obrigatório no painel
                    </label>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="h-9 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition-colors hover:opacity-90 disabled:opacity-50"
                        >
                            {{
                                createForm.processing
                                    ? 'Criando…'
                                    : 'Criar campo'
                            }}
                        </button>
                        <button
                            type="button"
                            class="h-9 rounded-lg border border-input px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                            @click="showCreateForm = false"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>

                <!-- Empty state -->
                <div
                    v-if="fields.length === 0"
                    class="rounded-lg border border-dashed border-border px-4 py-8 text-center"
                >
                    <p class="text-sm font-medium text-foreground">
                        Nenhum campo adicional ainda
                    </p>
                    <p
                        class="mx-auto mt-1 max-w-md text-sm text-muted-foreground"
                    >
                        Crie campos para o que a sua equipe sempre pergunta e
                        hoje escreve nas notas — renda, imóvel de interesse,
                        matrícula. Eles viram filtros de lista e campos do
                        painel.
                    </p>
                </div>

                <div v-else class="space-y-2">
                    <CustomFieldRow
                        v-for="(field, index) in fields"
                        :key="field.id"
                        :field="field"
                        :is-first="index === 0"
                        :is-last="index === fields.length - 1"
                        :saving="savingId === field.id"
                        @update="update"
                        @delete="remove"
                        @move="move"
                    />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
