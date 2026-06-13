<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { SlidersHorizontal, Sparkles } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { BreadcrumbItem } from '@/types';

type Settings = {
    agent_niche: string;
    agent_name: string;
    company_name: string;
    agent_personality: string;
    agent_greeting: string;
};

type AgentSpecialization = {
    value: string;
    label: string;
    description: string;
    badge_classes: string;
};

type Props = { settings: Settings; specializations: AgentSpecialization[]; flash: string | null; agent?: { id: number; name: string } | null };
const props = defineProps<Props>();

const configPath = computed(() => props.agent ? `/agentes/${props.agent.id}/config` : '/agente');

const breadcrumbs: BreadcrumbItem[] = props.agent
    ? [
        { title: 'Agentes', href: '/agentes' },
        { title: props.agent.name, href: configPath.value },
        { title: 'Configuração do Agente', href: configPath.value },
    ]
    : [{ title: 'Configuração do Agente', href: '/agente' }];

const form = useForm({
    agent_niche: props.settings.agent_niche,
    agent_name: props.settings.agent_name,
    company_name: props.settings.company_name,
    agent_personality: props.settings.agent_personality,
    agent_greeting: props.settings.agent_greeting,
});

const selectedSpecialization = computed(() => props.specializations.find((item) => item.value === form.agent_niche) ?? props.specializations[0]);
</script>

<template>
    <Head title="Configuração do Agente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl p-4 lg:p-8">
            <!-- Flash -->
            <div v-if="flash" class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400">
                {{ flash }}
            </div>

            <!-- Errors -->
            <div v-if="Object.keys(form.errors).length" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-900/50 dark:bg-red-900/20">
                <p v-for="(error, key) in form.errors" :key="key" class="text-sm font-medium text-red-600 dark:text-red-400">
                    {{ error }}
                </p>
            </div>

            <form @submit.prevent="form.post(configPath)" class="relative flex flex-col gap-6">

                <!-- Perfil do Agente -->
                <div class="rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border sm:p-8">
                    <h2 class="mb-6 text-lg font-semibold text-foreground tracking-tight">Perfil do Agente</h2>

                    <div class="mb-6">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">EspecializaÃ§Ã£o ativa</label>
                        <select
                            v-model="form.agent_niche"
                            class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50"
                        >
                            <option v-for="specialization in specializations" :key="specialization.value" :value="specialization.value">
                                {{ specialization.label }}
                            </option>
                        </select>
                        <div v-if="selectedSpecialization" class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                :class="selectedSpecialization.badge_classes"
                            >
                                {{ selectedSpecialization.label }}
                            </span>
                            <p class="text-xs text-muted-foreground">{{ selectedSpecialization.description }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Nome do agente</label>
                            <input v-model="form.agent_name" type="text" maxlength="50"
                                class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50" />
                            <p class="mt-1.5 text-xs text-muted-foreground">Como o agente se apresenta ao cliente</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Nome da empresa</label>
                            <input v-model="form.company_name" type="text" maxlength="100"
                                class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50" />
                            <p class="mt-1.5 text-xs text-muted-foreground">Nome que aparece na apresentação</p>
                        </div>
                    </div>

                    <hr class="my-6 border-sidebar-border/50" />

                    <div class="space-y-6">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Personalidade</label>
                            <input v-model="form.agent_personality" type="text" maxlength="200"
                                placeholder="Ex: calorosa e empática"
                                class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50" />
                            <p class="mt-1.5 text-xs text-muted-foreground">Diretriz de tom e linguagem do bot</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Saudação Inicial</label>
                            <input v-model="form.agent_greeting" type="text" maxlength="300"
                                placeholder="Ex: Diga olá, apresente-se como consultora e pergunte como pode ajudar"
                                class="w-full rounded-lg border border-input bg-background px-4 py-2.5 text-sm text-foreground shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50" />
                            <p class="mt-1.5 text-xs text-muted-foreground">Instrução de como o agente inicia a conversa</p>
                        </div>
                    </div>
                </div>

                <!-- Managed for you callout -->
                <Alert class="border-primary/20 bg-primary/5 dark:border-primary/20">
                    <Sparkles class="h-4 w-4 text-primary" />
                    <AlertTitle class="text-foreground">Configuração de IA gerenciada pela plataforma</AlertTitle>
                    <AlertDescription class="text-muted-foreground">
                        O modelo de IA, parâmetros e ajustes técnicos são otimizados automaticamente pela equipe da plataforma para o seu segmento. Você cuida da personalidade; a gente cuida da inteligência.
                    </AlertDescription>
                </Alert>

                <!-- Link Regras Operacionais -->
                <a
                    :href="agent ? `/agentes/${agent.id}/regras-operacionais` : '/agentes'"
                    class="group relative overflow-hidden rounded-2xl border border-primary/20 bg-primary/5 p-6 shadow-sm transition-all hover:border-primary/40 hover:bg-primary/10 hover:shadow-md dark:border-primary/20"
                >
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-primary/10 blur-2xl transition-all group-hover:bg-primary/20" />
                    <div class="relative flex items-center gap-5">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                            <SlidersHorizontal class="h-6 w-6" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-foreground transition-colors group-hover:text-primary">Regras Operacionais</h3>
                            <p class="mt-1 text-xs text-muted-foreground">Bancos parceiros, produtos habilitados e critérios mínimos</p>
                        </div>
                        <div class="ml-2 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 transition-transform group-hover:translate-x-1"><path d="m9 18 6-6-6-6"/></svg>
                        </div>
                    </div>
                </a>

                <!-- Sticky Footer Save -->
                <div class="sticky bottom-0 z-20 -mx-4 -mb-4 mt-2 border-t border-sidebar-border/50 bg-background/80 p-4 shadow-[0_-10px_40px_-10px_rgba(0,0,0,0.1)] backdrop-blur-xl dark:border-sidebar-border/80 lg:-mx-8 lg:-mb-8 lg:p-6">
                    <div class="mx-auto flex max-w-3xl justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-primary px-8 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-50 sm:w-auto"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span>{{ form.processing ? 'Salvando alterações...' : 'Salvar configurações' }}</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
