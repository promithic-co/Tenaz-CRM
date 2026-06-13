<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Bot, Smartphone, AlertTriangle, Pencil, Check, X, Power, Archive, RotateCcw, Trash2 } from 'lucide-vue-next';
import EmptyState from '@/components/EmptyState.vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue';
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue';
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue';
import type { BreadcrumbItem } from '@/types';

type Instance = {
    id: number;
    name: string;
    display_name: string | null;
    phone_number: string | null;
};

type Agent = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    is_default: boolean;
    display_agent_name: string | null;
    model: string | null;
    provider: string | null;
    template_slug: string | null;
    agent_niche: string;
    specialization: {
        value: string;
        label: string;
        description: string;
        badge_classes: string;
    };
    instance: Instance | null;
    leads_count: number;
    active_conversations: number;
    converted_count: number;
    conversion_rate: number;
};

type ArchivedAgent = {
    id: number;
    name: string;
    description: string | null;
    deleted_at: string;
};

type Props = {
    agents: Agent[];
    archived_agents: ArchivedAgent[];
    available_instances: Instance[];
    flash: string | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agentes', href: '/agentes' }];

function modelLabel(provider: string | null, model: string | null): string | null {
    if (!model) {
        return null;
    }
    const short = model.split('/').pop() ?? model;
    return short;
}

const templateModeMap: Record<string, { label: string; classes: string }> = {
    'alicia-receptivo': { label: 'Receptivo', classes: 'bg-teal-500/10 text-teal-500' },
    'aria-bulk': { label: 'Prospecção', classes: 'bg-amber-500/10 text-amber-500' },
};

function templateBadge(slug: string | null) {
    if (!slug) return null;
    return templateModeMap[slug] ?? null;
}

function instanceLabel(instance: Instance): string {
    const name = instance.display_name || instance.name;
    return instance.phone_number ? `${name} · ${instance.phone_number}` : name;
}

// Inline edit de nome/descrição
const editingAgentId = ref<number | null>(null);
const editForm = useForm({
    name: '',
    description: '' as string | null,
});
const nameInputRef = ref<HTMLInputElement | null>(null);

function startEditing(agent: Agent) {
    editingAgentId.value = agent.id;
    editForm.name = agent.name;
    editForm.description = agent.description;
    nextTick(() => nameInputRef.value?.focus());
}

function cancelEditing() {
    editingAgentId.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function saveEdit(agent: Agent) {
    editForm.patch(`/agentes/${agent.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingAgentId.value = null;
            editForm.reset();
        },
    });
}

// Archive/Restore
const archiveForm = useForm({});
const showArchiveConfirm = ref<number | null>(null);

function archiveAgent(agent: Agent) {
    archiveForm.delete(`/agentes/${agent.id}`, {
        preserveScroll: true,
        onSuccess: () => { showArchiveConfirm.value = null; },
    });
}

function restoreAgent(id: number) {
    archiveForm.patch(`/agentes/${id}/restore`, {
        preserveScroll: true,
    });
}

// Toggle active/inactive
const toggleForm = useForm({});

function toggleActive(agent: Agent) {
    toggleForm.patch(`/agentes/${agent.id}/toggle-active`, {
        preserveScroll: true,
    });
}

// Modal de troca de instância
const modalOpen = ref(false);
const selectedAgent = ref<Agent | null>(null);

const instanceForm = useForm({
    whatsapp_instance_id: null as number | null,
});

function openInstanceModal(agent: Agent) {
    selectedAgent.value = agent;
    instanceForm.whatsapp_instance_id = agent.instance?.id ?? null;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    selectedAgent.value = null;
    instanceForm.reset();
}

function submitInstanceChange() {
    if (!selectedAgent.value) return;

    instanceForm.patch(`/agentes/${selectedAgent.value.id}/instance`, {
        onSuccess: () => closeModal(),
    });
}

// Instâncias disponíveis para o modal: livres + a atual do agente selecionado
function optionsForAgent(agent: Agent): Instance[] {
    const options = [...props.available_instances];
    if (agent.instance && !options.find((i) => i.id === agent.instance!.id)) {
        options.unshift(agent.instance);
    }
    return options;
}
</script>

<template>
    <Head title="Agentes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 p-4 lg:p-8">
            <div
                v-if="flash"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash }}
            </div>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-semibold text-foreground">Agentes</h1>
                    <p class="text-xs text-muted-foreground">Cada instância WhatsApp deve estar vinculada a um agente.</p>
                </div>
                <Link
                    href="/agentes/create"
                    class="rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                >
                    + Novo agente
                </Link>
            </div>

            <div v-if="agents.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="agent in agents"
                    :key="agent.id"
                    class="flex flex-col rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                >
                    <!-- Header: name + badges (view mode) -->
                    <div v-if="editingAgentId !== agent.id" class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <button
                                type="button"
                                :title="agent.is_active ? 'Pausar agente' : 'Ativar agente'"
                                class="mt-0.5 size-2 shrink-0 rounded-full cursor-pointer transition-colors ring-2 ring-transparent hover:ring-offset-1 hover:ring-offset-background"
                                :class="agent.is_active ? 'bg-emerald-500 hover:ring-emerald-500/50' : 'bg-muted-foreground/40 hover:ring-muted-foreground/30'"
                                @click="toggleActive(agent)"
                            />
                            <div class="min-w-0">
                                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ agent.name }}</p>
                                    <span
                                        v-if="agent.specialization"
                                        class="shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-semibold"
                                        :class="agent.specialization.badge_classes"
                                    >
                                        {{ agent.specialization.label }}
                                    </span>
                                </div>
                                <p
                                    v-if="agent.display_agent_name && agent.display_agent_name !== agent.name"
                                    class="text-xs text-muted-foreground"
                                >
                                    Persona: {{ agent.display_agent_name }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <span
                                v-if="templateBadge(agent.template_slug)"
                                class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                :class="templateBadge(agent.template_slug)!.classes"
                            >
                                {{ templateBadge(agent.template_slug)!.label }}
                            </span>
                            <span
                                v-if="!agent.is_active"
                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                            >
                                Pausado
                            </span>
                            <span
                                v-if="agent.is_default"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                            >
                                Padrão
                            </span>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                title="Editar nome e descrição"
                                @click="startEditing(agent)"
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                                title="Arquivar agente"
                                @click="showArchiveConfirm = agent.id"
                            >
                                <Archive class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <p v-if="editingAgentId !== agent.id && agent.description" class="mt-2 text-xs text-muted-foreground">{{ agent.description }}</p>

                    <!-- Header: name + description (edit mode) -->
                    <div v-if="editingAgentId === agent.id" class="space-y-2">
                        <div>
                            <input
                                ref="nameInputRef"
                                v-model="editForm.name"
                                type="text"
                                maxlength="100"
                                class="w-full rounded-md border border-input bg-background px-2 py-1 text-sm font-semibold text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                                placeholder="Nome do agente"
                                @keyup.enter="saveEdit(agent)"
                                @keyup.escape="cancelEditing"
                            />
                            <p v-if="editForm.errors.name" class="mt-0.5 text-[10px] text-destructive">{{ editForm.errors.name }}</p>
                        </div>
                        <textarea
                            v-model="editForm.description"
                            maxlength="255"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary resize-none"
                            placeholder="Descrição (opcional)"
                            @keyup.escape="cancelEditing"
                        />
                        <div class="flex justify-end gap-1">
                            <button
                                type="button"
                                class="rounded p-1 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                title="Cancelar"
                                @click="cancelEditing"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                :disabled="editForm.processing"
                                class="rounded p-1 text-primary hover:text-primary/80 hover:bg-primary/10 transition-colors disabled:opacity-50"
                                title="Salvar"
                                @click="saveEdit(agent)"
                            >
                                <Check class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Archive confirmation -->
                    <div
                        v-if="showArchiveConfirm === agent.id"
                        class="mt-2 rounded-md border border-destructive/30 bg-destructive/5 p-2.5"
                    >
                        <p class="text-xs text-destructive font-medium">Arquivar este agente?</p>
                        <p class="text-[10px] text-muted-foreground mt-0.5">Conversas existentes serão mantidas. A instância WhatsApp será desvinculada.</p>
                        <div class="flex gap-1.5 mt-2">
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-[10px] bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors"
                                :disabled="archiveForm.processing"
                                @click="archiveAgent(agent)"
                            >
                                {{ archiveForm.processing ? 'Arquivando...' : 'Confirmar' }}
                            </button>
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-[10px] border border-input hover:bg-accent transition-colors"
                                @click="showArchiveConfirm = null"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- Instance info -->
                    <div class="mt-3 text-xs">
                        <div
                            class="flex items-center justify-between gap-1"
                            :class="agent.instance ? 'text-muted-foreground' : 'text-amber-400'"
                        >
                            <div class="flex items-center gap-1.5 min-w-0">
                                <component
                                    :is="agent.instance ? Smartphone : AlertTriangle"
                                    class="h-3.5 w-3.5 shrink-0"
                                />
                                <span v-if="agent.instance" class="truncate">
                                    {{ agent.instance.display_name || agent.instance.name }}
                                    <span v-if="agent.instance.phone_number" class="ml-1 font-mono text-[10px]">
                                        {{ agent.instance.phone_number }}
                                    </span>
                                </span>
                                <span v-else>Sem instância vinculada</span>
                            </div>
                            <button
                                type="button"
                                class="ml-1 shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                title="Alterar instância"
                                @click="openInstanceModal(agent)"
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Metrics row -->
                    <div class="grid grid-cols-3 gap-2 mt-3 mb-3">
                        <div class="flex flex-col items-center rounded-md bg-muted/50 py-2">
                            <span class="text-lg font-bold leading-tight">{{ agent.leads_count ?? 0 }}</span>
                            <span class="text-xs text-muted-foreground">leads</span>
                        </div>
                        <div class="flex flex-col items-center rounded-md bg-muted/50 py-2">
                            <span class="text-lg font-bold leading-tight">{{ agent.active_conversations ?? 0 }}</span>
                            <span class="text-xs text-muted-foreground">ativos</span>
                        </div>
                        <div class="flex flex-col items-center rounded-md bg-muted/50 py-2">
                            <span
                                class="text-lg font-bold leading-tight"
                                :class="(agent.conversion_rate ?? 0) >= 10 ? 'text-emerald-400' : (agent.conversion_rate ?? 0) > 0 ? 'text-amber-400' : 'text-muted-foreground'"
                            >
                                {{ agent.conversion_rate ?? 0 }}%
                            </span>
                            <span class="text-xs text-muted-foreground">conversão</span>
                        </div>
                    </div>

                    <!-- Model pill -->
                    <div v-if="modelLabel(agent.provider, agent.model)" class="mt-2">
                        <span class="text-[10px] text-muted-foreground font-mono">
                            {{ modelLabel(agent.provider, agent.model) }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-wrap gap-2 mt-3">
                        <Link
                            :href="`/agentes/${agent.id}/config`"
                            class="flex-1 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 text-center"
                        >
                            Config
                        </Link>
                        <Link
                            :href="`/agentes/${agent.id}/regras-operacionais`"
                            class="flex-1 rounded-md border border-input px-3 py-1.5 text-xs font-medium hover:bg-accent text-center"
                        >
                            Regras
                        </Link>
                        <Link
                            :href="`/agentes/${agent.id}/follow-up`"
                            class="flex-1 rounded-md border border-input px-3 py-1.5 text-xs font-medium hover:bg-accent text-center"
                        >
                            Follow-up
                        </Link>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                :icon="Bot"
                title="Nenhum agente criado"
                description="Crie seu primeiro agente para começar a qualificar leads via WhatsApp."
            >
                <Link href="/agentes/create" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90">
                    + Novo agente
                </Link>
            </EmptyState>

            <!-- Archived agents -->
            <div v-if="archived_agents.length" class="mt-6">
                <details class="group">
                    <summary class="cursor-pointer text-xs font-medium text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5">
                        <Archive class="h-3.5 w-3.5" />
                        Arquivados ({{ archived_agents.length }})
                    </summary>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="archived in archived_agents"
                            :key="archived.id"
                            class="flex items-center justify-between rounded-lg border border-dashed border-sidebar-border/50 bg-muted/30 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-muted-foreground">{{ archived.name }}</p>
                                <p class="text-[10px] text-muted-foreground/70">Arquivado em {{ archived.deleted_at }}</p>
                            </div>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-md border border-input px-2 py-1 text-[10px] font-medium hover:bg-accent transition-colors"
                                :disabled="archiveForm.processing"
                                @click="restoreAgent(archived.id)"
                            >
                                <RotateCcw class="h-3 w-3" />
                                Restaurar
                            </button>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        <!-- Modal: alterar instância -->
        <Dialog :open="modalOpen" @update:open="(v) => { if (!v) closeModal(); }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Alterar instância — {{ selectedAgent?.name }}</DialogTitle>
                </DialogHeader>

                <div v-if="selectedAgent" class="space-y-4 py-2">
                    <p class="text-xs text-muted-foreground">
                        Selecione a instância WhatsApp que será usada por este agente. Novas mensagens recebidas serão roteadas automaticamente.
                    </p>

                    <div class="space-y-2">
                        <!-- Opção: nenhuma -->
                        <label
                            class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                            :class="instanceForm.whatsapp_instance_id === null
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'border-border hover:bg-muted/50'"
                        >
                            <input
                                type="radio"
                                name="instance_selection"
                                :checked="instanceForm.whatsapp_instance_id === null"
                                class="sr-only"
                                @change="instanceForm.whatsapp_instance_id = null"
                            />
                            <span
                                class="flex size-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                                :class="instanceForm.whatsapp_instance_id === null
                                    ? 'border-primary'
                                    : 'border-muted-foreground/40'"
                            >
                                <span
                                    v-if="instanceForm.whatsapp_instance_id === null"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </span>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nenhuma</p>
                                <p class="text-xs text-muted-foreground/70">Desvincular instância deste agente</p>
                            </div>
                        </label>

                        <!-- Opções de instâncias -->
                        <label
                            v-for="instance in optionsForAgent(selectedAgent)"
                            :key="instance.id"
                            class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                            :class="instanceForm.whatsapp_instance_id === instance.id
                                ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                : 'border-border hover:bg-muted/50'"
                        >
                            <input
                                type="radio"
                                name="instance_selection"
                                :checked="instanceForm.whatsapp_instance_id === instance.id"
                                class="sr-only"
                                @change="instanceForm.whatsapp_instance_id = instance.id"
                            />
                            <span
                                class="flex size-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                                :class="instanceForm.whatsapp_instance_id === instance.id
                                    ? 'border-primary'
                                    : 'border-muted-foreground/40'"
                            >
                                <span
                                    v-if="instanceForm.whatsapp_instance_id === instance.id"
                                    class="size-2 rounded-full bg-primary"
                                />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate">
                                    {{ instance.display_name || instance.name }}
                                    <span v-if="selectedAgent.instance?.id === instance.id" class="ml-1.5 text-[10px] text-primary font-normal">(atual)</span>
                                </p>
                                <p v-if="instance.phone_number" class="text-xs font-mono text-muted-foreground">{{ instance.phone_number }}</p>
                                <p v-else class="text-xs text-muted-foreground">{{ instance.name }}</p>
                            </div>
                        </label>

                        <p
                            v-if="optionsForAgent(selectedAgent).length === 0 && !selectedAgent.instance"
                            class="text-xs text-muted-foreground text-center py-3"
                        >
                            Nenhuma instância disponível. Crie uma em <Link href="/whatsapp" class="text-primary underline">WhatsApp</Link>.
                        </p>
                    </div>

                    <p v-if="instanceForm.errors.whatsapp_instance_id" class="text-xs text-destructive">
                        {{ instanceForm.errors.whatsapp_instance_id }}
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm hover:bg-accent transition-colors"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="instanceForm.processing"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                        @click="submitInstanceChange"
                    >
                        {{ instanceForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
