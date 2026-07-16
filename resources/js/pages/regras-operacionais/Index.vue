<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

// ─── Types ──────────────────────────────────────────────────────────────────

type Produtos = {
    novo: boolean;
    refin: boolean;
    port: boolean;
    rmc: boolean;
    rcc: boolean;
};

type InstituicaoConfig = {
    codigo: string | null;
    sigla: string;
    nome: string;
    ativo: boolean;
    produtos: Produtos;
};

type RegrasGlobais = {
    idade_minima: number;
    idade_maxima: number;
    valor_minimo_liberado_novo: number;
    valor_minimo_liberado_refin: number;
    valor_minimo_parcela_portabilidade: number;
    percentual_minimo_pago_portabilidade: number;
};

type RegrasEspecies = {
    aceita_invalidez_abaixo_60: boolean;
    aceita_loas_emprestimo: boolean;
    aceita_loas_cartao: boolean;
};

type Rules = {
    instituicoes_config: InstituicaoConfig[];
    regras_globais: RegrasGlobais;
    regras_especies: RegrasEspecies;
};

type AgentRef = { id: number; name: string };
type Props = { agent: AgentRef; rules: Rules; flash: string | null };
const props = defineProps<Props>();

// ─── Setup ──────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agentes', href: '/agentes' },
    { title: props.agent.name, href: `/agentes/${props.agent.id}/config` },
    {
        title: 'Regras Operacionais',
        href: `/agentes/${props.agent.id}/regras-operacionais`,
    },
];

const activeTab = ref<'bancos' | 'limites' | 'especies'>('bancos');

const form = useForm<Rules>({
    instituicoes_config: props.rules.instituicoes_config.map((b) => ({
        ...b,
        produtos: { ...b.produtos },
    })),
    regras_globais: { ...props.rules.regras_globais },
    regras_especies: { ...props.rules.regras_especies },
});

const PRODUTO_LABELS: Record<keyof Produtos, string> = {
    novo: 'Novo',
    refin: 'Refin',
    port: 'Port',
    rmc: 'RMC',
    rcc: 'RCC',
};

function submit() {
    form.put(`/agentes/${props.agent.id}/regras-operacionais`);
}

function toggleBanco(banco: InstituicaoConfig) {
    if (!banco.ativo) {
        // desativar o banco desativa todos os produtos
        Object.keys(banco.produtos).forEach((k) => {
            banco.produtos[k as keyof Produtos] = false;
        });
    }
}
</script>

<template>
    <Head title="Regras Operacionais" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl p-3 sm:p-4">
            <!-- Flash -->
            <div
                v-if="flash"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash }}
            </div>

            <!-- Header -->
            <div class="mb-5">
                <h1 class="text-base font-semibold text-foreground">
                    Regras Operacionais
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Configure os bancos, produtos e critérios mínimos que o
                    agente usará para qualificar os leads.
                </p>
            </div>

            <!-- Tabs -->
            <div
                class="mb-5 flex gap-1 border-b border-sidebar-border/70 dark:border-sidebar-border"
            >
                <button
                    v-for="tab in [
                        { id: 'bancos', label: 'Bancos e Produtos' },
                        { id: 'limites', label: 'Limites Mínimos' },
                        { id: 'especies', label: 'Espécies Sensíveis' },
                    ]"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id as any"
                    :class="[
                        '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                        activeTab === tab.id
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- ── ABA 1: Bancos e Produtos ─────────────────────────────── -->
                <div v-show="activeTab === 'bancos'">
                    <div
                        class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    >
                        <div
                            class="border-b border-sidebar-border/70 bg-muted/30 px-5 py-3 dark:border-sidebar-border"
                        >
                            <p class="text-xs text-muted-foreground">
                                Ative os bancos com os quais você tem código e
                                selecione os produtos que opera em cada um.
                            </p>
                        </div>

                        <!-- Header cols -->
                        <div
                            class="grid items-center gap-3 border-b border-sidebar-border/40 px-5 py-2 text-xs font-semibold text-muted-foreground dark:border-sidebar-border/40"
                            style="
                                grid-template-columns: 1fr auto auto auto auto auto auto;
                            "
                        >
                            <span>Instituição</span>
                            <span
                                v-for="(label, key) in PRODUTO_LABELS"
                                :key="key"
                                class="w-12 text-center"
                                >{{ label }}</span
                            >
                            <span class="w-16 text-center">Ativo</span>
                        </div>

                        <!-- Banco rows -->
                        <div
                            v-for="(banco, idx) in form.instituicoes_config"
                            :key="banco.sigla"
                            :class="[
                                'grid items-center gap-3 px-5 py-3 transition-colors',
                                idx % 2 === 0 ? '' : 'bg-muted/20',
                                banco.ativo ? '' : 'opacity-50',
                            ]"
                            style="
                                grid-template-columns: 1fr auto auto auto auto auto auto;
                            "
                        >
                            <div>
                                <span
                                    class="text-sm font-medium text-foreground"
                                    >{{ banco.nome }}</span
                                >
                                <span
                                    v-if="banco.codigo"
                                    class="ml-2 text-xs text-muted-foreground"
                                    >{{ banco.codigo }}</span
                                >
                            </div>

                            <!-- Toggle por produto -->
                            <div
                                v-for="(_, prodKey) in PRODUTO_LABELS"
                                :key="prodKey"
                                class="flex w-12 justify-center"
                            >
                                <button
                                    type="button"
                                    :disabled="!banco.ativo"
                                    @click="
                                        banco.produtos[
                                            prodKey as keyof Produtos
                                        ] =
                                            !banco.produtos[
                                                prodKey as keyof Produtos
                                            ]
                                    "
                                    :class="[
                                        'flex h-5 w-5 items-center justify-center rounded border-2 transition-colors',
                                        banco.produtos[
                                            prodKey as keyof Produtos
                                        ] && banco.ativo
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input bg-background',
                                    ]"
                                >
                                    <svg
                                        v-if="
                                            banco.produtos[
                                                prodKey as keyof Produtos
                                            ] && banco.ativo
                                        "
                                        class="h-3 w-3"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="3"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <!-- Toggle banco ativo -->
                            <div class="flex w-16 justify-center">
                                <button
                                    type="button"
                                    @click="
                                        banco.ativo = !banco.ativo;
                                        toggleBanco(banco);
                                    "
                                    :class="[
                                        'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                        banco.ativo ? 'bg-primary' : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200',
                                            banco.ativo
                                                ? 'translate-x-4'
                                                : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── ABA 2: Limites Mínimos ───────────────────────────────── -->
                <div v-show="activeTab === 'limites'">
                    <div
                        class="space-y-6 rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <p class="text-sm text-muted-foreground">
                            Valores mínimos que o agente exige para considerar
                            um produto viável. Operações abaixo do limite são
                            ignoradas.
                        </p>

                        <!-- Idade -->
                        <div>
                            <h3
                                class="mb-3 text-sm font-semibold text-foreground"
                            >
                                Idade do Beneficiário
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-muted-foreground"
                                        >Idade mínima</label
                                    >
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model.number="
                                                form.regras_globais.idade_minima
                                            "
                                            type="number"
                                            min="18"
                                            max="100"
                                            class="w-20 rounded-md border border-input bg-background px-3 py-2 text-center text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >anos</span
                                        >
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-muted-foreground"
                                        >Idade máxima</label
                                    >
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model.number="
                                                form.regras_globais.idade_maxima
                                            "
                                            type="number"
                                            min="18"
                                            max="100"
                                            class="w-20 rounded-md border border-input bg-background px-3 py-2 text-center text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >anos</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="border-sidebar-border/40" />

                        <!-- Empréstimo Novo -->
                        <div>
                            <h3
                                class="mb-3 text-sm font-semibold text-foreground"
                            >
                                Empréstimo Novo
                            </h3>
                            <div>
                                <label
                                    class="mb-1 block text-sm text-muted-foreground"
                                    >Valor mínimo liberado</label
                                >
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-muted-foreground"
                                        >R$</span
                                    >
                                    <input
                                        v-model.number="
                                            form.regras_globais
                                                .valor_minimo_liberado_novo
                                        "
                                        type="number"
                                        min="0"
                                        step="50"
                                        class="w-28 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Margem que deve liberar ao menos este valor
                                    para ser ofertada
                                </p>
                            </div>
                        </div>

                        <hr class="border-sidebar-border/40" />

                        <!-- Refinanciamento -->
                        <div>
                            <h3
                                class="mb-3 text-sm font-semibold text-foreground"
                            >
                                Refinanciamento
                            </h3>
                            <div>
                                <label
                                    class="mb-1 block text-sm text-muted-foreground"
                                    >Troco mínimo por contrato</label
                                >
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-muted-foreground"
                                        >R$</span
                                    >
                                    <input
                                        v-model.number="
                                            form.regras_globais
                                                .valor_minimo_liberado_refin
                                        "
                                        type="number"
                                        min="0"
                                        step="50"
                                        class="w-28 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Contratos que liberam menos que isso são
                                    ignorados
                                </p>
                            </div>
                        </div>

                        <hr class="border-sidebar-border/40" />

                        <!-- Portabilidade -->
                        <div>
                            <h3
                                class="mb-3 text-sm font-semibold text-foreground"
                            >
                                Portabilidade
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-muted-foreground"
                                        >Parcela mínima</label
                                    >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >R$</span
                                        >
                                        <input
                                            v-model.number="
                                                form.regras_globais
                                                    .valor_minimo_parcela_portabilidade
                                            "
                                            type="number"
                                            min="0"
                                            step="10"
                                            class="w-24 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >/mês</span
                                        >
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm text-muted-foreground"
                                        >Mínimo de prazo pago</label
                                    >
                                    <div class="flex items-center gap-2">
                                        <input
                                            :value="
                                                Math.round(
                                                    form.regras_globais
                                                        .percentual_minimo_pago_portabilidade *
                                                        100,
                                                )
                                            "
                                            @input="
                                                form.regras_globais.percentual_minimo_pago_portabilidade =
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ) / 100
                                            "
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="5"
                                            class="w-20 rounded-md border border-input bg-background px-3 py-2 text-center text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >%</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Percentual do prazo total que deve estar
                                        pago
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── ABA 3: Espécies Sensíveis ───────────────────────────── -->
                <div v-show="activeTab === 'especies'">
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                    >
                        <p class="mb-5 text-sm text-muted-foreground">
                            Controle se o agente deve abordar beneficiários com
                            espécies que exigem atenção especial. Em caso de
                            dúvida, mantenha desativado e avalie caso a caso com
                            o cliente.
                        </p>

                        <div class="space-y-4">
                            <!-- Invalidez <60 -->
                            <div
                                class="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-muted/20 px-4 py-3 dark:border-sidebar-border"
                            >
                                <div>
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        Invalidez abaixo de 60 anos
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Esp. 32 (e similares) com cliente menor
                                        de 60 anos. Muito restrito na maioria
                                        dos bancos.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="
                                        form.regras_especies.aceita_invalidez_abaixo_60 =
                                            !form.regras_especies
                                                .aceita_invalidez_abaixo_60
                                    "
                                    :class="[
                                        'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                        form.regras_especies
                                            .aceita_invalidez_abaixo_60
                                            ? 'bg-primary'
                                            : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200',
                                            form.regras_especies
                                                .aceita_invalidez_abaixo_60
                                                ? 'translate-x-4'
                                                : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>

                            <!-- LOAS Empréstimo -->
                            <div
                                class="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-muted/20 px-4 py-3 dark:border-sidebar-border"
                            >
                                <div>
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        LOAS — Empréstimo (Esp. 87 e 88)
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        ATENÇÃO: suspenso na maioria dos bancos
                                        em 2026. Ative apenas se tiver banco
                                        habilitado (Qualibank, Quero+, CBA
                                        Caixa).
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="
                                        form.regras_especies.aceita_loas_emprestimo =
                                            !form.regras_especies
                                                .aceita_loas_emprestimo
                                    "
                                    :class="[
                                        'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                        form.regras_especies
                                            .aceita_loas_emprestimo
                                            ? 'bg-primary'
                                            : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200',
                                            form.regras_especies
                                                .aceita_loas_emprestimo
                                                ? 'translate-x-4'
                                                : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>

                            <!-- LOAS Cartão -->
                            <div
                                class="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-muted/20 px-4 py-3 dark:border-sidebar-border"
                            >
                                <div>
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        LOAS — Cartão (Esp. 87 e 88)
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Cartão RMC/RCC para beneficiários LOAS.
                                        Disponível em BMG, PAN, BRB e outros.
                                        Recomendado manter ativo.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="
                                        form.regras_especies.aceita_loas_cartao =
                                            !form.regras_especies
                                                .aceita_loas_cartao
                                    "
                                    :class="[
                                        'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                        form.regras_especies.aceita_loas_cartao
                                            ? 'bg-primary'
                                            : 'bg-input',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200',
                                            form.regras_especies
                                                .aceita_loas_cartao
                                                ? 'translate-x-4'
                                                : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Errors -->
                <div
                    v-if="Object.keys(form.errors).length"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20"
                >
                    <p
                        v-for="(error, key) in form.errors"
                        :key="key"
                        class="text-xs text-red-600 dark:text-red-400"
                    >
                        {{ error }}
                    </p>
                </div>

                <!-- Save -->
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar regras' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
