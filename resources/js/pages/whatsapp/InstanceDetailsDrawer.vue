<script setup lang="ts">
import { AlertTriangle, Check, Copy, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    checkedAtLabel,
    entityLabel,
    healthChip,
    healthStatusOf,
    nameStatusLabel,
    portfolioLimitLabel,
    restrictionLabel,
    throughputLabel,
    verificationLabel,
} from '@/composables/useMetaHealth';
import type { WhatsappInstanceSummary } from '@/types';

const props = defineProps<{
    instance: WhatsappInstanceSummary;
    open: boolean;
    csrf: string;
    refreshing?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    delete: [];
    refresh: [];
    updated: [Partial<WhatsappInstanceSummary>];
}>();

const copiedKey = ref<string | null>(null);

async function copy(key: string, value: string | null): Promise<void> {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) {
                copiedKey.value = null;
            }
        }, 1500);
    } catch {
        // Clipboard access may be unavailable outside secure browser contexts.
    }
}

/**
 * The Meta access token, phrased as what it costs the operator: when it lapses
 * the number stops sending and someone has to reconnect the account. "Token" is
 * not a word an operator has to learn to understand that.
 */
const tokenInfo = computed(() => {
    if (props.instance.meta_token_permanent) {
        return {
            label: 'Não expira',
            hint: null,
            class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        };
    }

    if (!props.instance.meta_token_expires_at) {
        return {
            label: 'Temporário',
            hint: 'Este acesso vai vencer e o número vai parar de enviar. Reconecte a conta pela Meta.',
            class: 'bg-muted text-muted-foreground border-border',
        };
    }

    const expiresAt = new Date(props.instance.meta_token_expires_at);
    const now = new Date();
    const diffMs = expiresAt.getTime() - now.getTime();
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (days < 0) {
        return {
            label: 'Vencido',
            hint: 'O número não envia mais. Reconecte a conta pela Meta.',
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    if (days < 7) {
        return {
            label: `Vence em ${days} dia${days === 1 ? '' : 's'}`,
            hint: 'Reconecte a conta pela Meta antes disso ou o número para de enviar.',
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    if (days < 30) {
        return {
            label: `Vence em ${days} dias`,
            hint: 'Reconecte a conta pela Meta antes disso ou o número para de enviar.',
            class: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
        };
    }

    return {
        label: `Vence em ${days} dias`,
        hint: null,
        class: 'bg-muted text-muted-foreground border-border',
    };
});

const aiModeLabel = computed(() => {
    const mode = props.instance.default_ai_mode;

    if (mode === 'automatic') {
        return 'Automático';
    }

    if (mode === 'manual') {
        return 'Manual';
    }

    if (mode === 'assisted') {
        return 'Assistido';
    }

    if (mode === 'qualify_then_handoff') {
        return 'Qualifica e transfere';
    }

    return mode ?? '-';
});

// ─── Meta health ──────────────────────────────────────────────────────────────

const healthStatus = computed(() =>
    healthStatusOf(props.instance.health_status),
);
const statusChip = computed(() => healthChip(props.instance.health_status));

// The "Conexão com o Tenaz" row only ever means something when it is the thing
// blocking the send; healthy, it is one more line of noise for an operator who
// never configured it in the first place.
const entities = computed(() =>
    (props.instance.health_entities ?? []).filter(
        (entity) =>
            entity.type.toUpperCase() !== 'APP' ||
            healthStatusOf(entity.status) !== 'AVAILABLE',
    ),
);
const restrictions = computed(
    () => props.instance.meta_account_restrictions ?? [],
);
const alerts = computed(() => props.instance.meta_account_alerts ?? []);

const lastChecked = computed(() =>
    checkedAtLabel(props.instance.health_checked_at),
);

const displayNameLabel = computed(() =>
    nameStatusLabel(props.instance.meta_name_status),
);
const portfolioLimit = computed(() =>
    portfolioLimitLabel(props.instance.meta_portfolio_messaging_limit),
);
const throughput = computed(() =>
    throughputLabel(props.instance.meta_throughput_level),
);
const verification = computed(() =>
    verificationLabel(props.instance.meta_code_verification_status),
);

/**
 * Meta's own scoring of how recipients react to this number. It is not
 * decoration: RED auto-pauses every running campaign on the instance
 * (`MetaQualityRiskService`), so the operator needs to see it drifting before
 * the pause lands on them.
 */
const qualityChip = computed(() => {
    const rating = (props.instance.meta_quality_rating ?? '').toUpperCase();

    if (rating === 'GREEN') {
        return {
            label: 'Boa',
            hint: 'Os clientes estão respondendo bem. Pode enviar normalmente.',
            class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        };
    }

    if (rating === 'YELLOW') {
        return {
            label: 'Em atenção',
            hint: 'Clientes começaram a bloquear ou denunciar. Reduza o volume e revise as mensagens.',
            class: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
        };
    }

    if (rating === 'RED') {
        return {
            label: 'Ruim',
            hint: 'Muitas denúncias. As campanhas deste número são pausadas automaticamente até melhorar.',
            class: 'bg-red-500/10 text-red-400 border-red-500/30',
        };
    }

    return null;
});

/**
 * A bare WABA/portfolio ID tells the reader nothing about which account they are
 * looking at, which matters as soon as a promotora runs more than one.
 */
function entityName(type: string): string | null {
    if (type.toUpperCase() === 'WABA') {
        return props.instance.meta_waba_name;
    }

    if (type.toUpperCase() === 'BUSINESS') {
        return props.instance.meta_business_name;
    }

    return null;
}

// ─── Conexão: nome + ativação do número ───────────────────────────────────────
//
// Activating a Meta Cloud number takes a 6 digit two-step PIN. Until now that
// only happened inside the signup flow, so a number that arrived unregistered
// could only be fixed with a hand-written `curl` — which is not something a
// promotora's operator can be asked to do.

const connectionName = ref(props.instance.display_name ?? '');
const pin = ref('');
const savingConnection = ref(false);
const connectionError = ref<string | null>(null);
const connectionSaved = ref<string | null>(null);

watch(
    () => [props.open, props.instance.display_name] as const,
    () => {
        connectionName.value = props.instance.display_name ?? '';
        // Never keep a typed secret around between openings of the drawer.
        pin.value = '';
        connectionError.value = null;
        connectionSaved.value = null;
    },
);

async function saveConnection(): Promise<void> {
    if (savingConnection.value) {
        return;
    }

    savingConnection.value = true;
    connectionError.value = null;
    connectionSaved.value = null;

    const activating = pin.value !== '';

    try {
        const res = await fetch(`/whatsapp/${props.instance.id}/connection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify({
                display_name: connectionName.value,
                pin: pin.value || null,
            }),
        });

        const body = await res.json();

        if (!res.ok) {
            connectionError.value =
                body?.errors?.pin?.[0] ??
                body?.errors?.display_name?.[0] ??
                body?.message ??
                'Não foi possível salvar. Tente de novo.';

            return;
        }

        pin.value = '';
        connectionSaved.value = activating
            ? 'Número ativado na Meta.'
            : 'Conexão atualizada.';
        emit('updated', body);
    } catch {
        connectionError.value =
            'Não foi possível falar com o servidor. Verifique sua conexão e tente de novo.';
    } finally {
        savingConnection.value = false;
    }
}

const providerLabel = 'Meta Cloud';
const providerClass = 'bg-blue-500/10 text-blue-400 border-blue-500/30';
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <span class="truncate">{{ instance.label }}</span>
                </DialogTitle>
                <DialogDescription
                    class="flex flex-wrap items-center gap-1.5 pt-1"
                >
                    <span
                        :class="[
                            'inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-medium',
                            providerClass,
                        ]"
                    >
                        {{ providerLabel }}
                    </span>
                    <span
                        :class="[
                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium',
                            statusChip.class,
                        ]"
                    >
                        {{ statusChip.label }}
                    </span>
                    <span
                        v-if="instance.meta_coexistence"
                        class="inline-flex items-center rounded border border-blue-500/30 bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-400"
                    >
                        Coexistência
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5 py-2">
                <!-- ── Saúde da conta ──────────────────────────────────────── -->
                <section class="space-y-2">
                    <div class="flex items-baseline justify-between gap-2">
                        <h4
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Saúde da conta
                        </h4>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-[11px] text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
                            :disabled="refreshing"
                            @click="emit('refresh')"
                        >
                            <RefreshCw
                                class="h-3 w-3"
                                :class="refreshing ? 'animate-spin' : ''"
                            />
                            {{
                                lastChecked
                                    ? `Verificado ${lastChecked}`
                                    : 'Verificar'
                            }}
                        </button>
                    </div>

                    <p
                        v-if="healthStatus === 'UNKNOWN'"
                        class="rounded-md border border-border bg-muted/40 px-3 py-2 text-[11px] leading-snug text-muted-foreground"
                    >
                        A Meta ainda não respondeu sobre este número. Isso não
                        significa que há um problema — apenas que não há
                        confirmação recente.
                    </p>

                    <!-- Per-entity breakdown: Meta blocks messaging at the number,
                         the WABA, the business portfolio or the app, and only one
                         of them is usually at fault. -->
                    <dl v-if="entities.length" class="space-y-1.5 text-xs">
                        <div
                            v-for="entity in entities"
                            :key="`${entity.type}-${entity.id}`"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="min-w-0 text-muted-foreground">
                                    {{ entityLabel(entity.type) }}
                                    <span
                                        v-if="entityName(entity.type)"
                                        class="block truncate font-medium text-foreground"
                                    >
                                        {{ entityName(entity.type) }}
                                    </span>
                                </dt>
                                <dd>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium',
                                            healthChip(entity.status).class,
                                        ]"
                                    >
                                        {{ healthChip(entity.status).label }}
                                    </span>
                                </dd>
                            </div>
                            <div
                                v-for="reason in entity.reasons"
                                :key="reason.title"
                                class="space-y-0.5 pl-1"
                            >
                                <p
                                    class="text-[11px] leading-snug font-medium text-foreground"
                                >
                                    {{ reason.title }}
                                </p>
                                <p
                                    v-if="reason.detail"
                                    class="text-[11px] leading-snug text-muted-foreground"
                                >
                                    {{ reason.detail }}
                                </p>
                                <p
                                    v-if="reason.action"
                                    class="text-[11px] leading-snug text-foreground/80"
                                >
                                    <span class="font-medium"
                                        >O que fazer:</span
                                    >
                                    {{ reason.action }}
                                </p>
                                <p
                                    v-if="reason.original"
                                    class="text-[10px] leading-snug text-muted-foreground italic"
                                >
                                    Texto original da Meta, ainda sem tradução.
                                </p>
                            </div>
                        </div>
                    </dl>

                    <!-- Active restrictions from the account_update webhook -->
                    <div
                        v-if="restrictions.length"
                        class="space-y-1 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2"
                    >
                        <p
                            class="flex items-center gap-1.5 text-[11px] font-semibold text-red-400"
                        >
                            <AlertTriangle class="h-3.5 w-3.5" />
                            Restrições ativas
                        </p>
                        <p
                            v-for="restriction in restrictions"
                            :key="restriction.type"
                            class="text-[11px] leading-snug text-red-400"
                        >
                            {{ restrictionLabel(restriction.type) }}
                        </p>
                    </div>

                    <!-- Capability alerts from the account_alerts webhook -->
                    <div
                        v-if="alerts.length"
                        class="space-y-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2"
                    >
                        <p
                            v-for="alert in alerts"
                            :key="alert.type ?? alert.description ?? ''"
                            class="text-[11px] leading-snug text-amber-400"
                        >
                            {{ alert.description ?? alert.type }}
                        </p>
                    </div>

                    <dl class="space-y-1.5 text-xs">
                        <div
                            v-if="displayNameLabel"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Nome de exibição
                            </dt>
                            <dd class="font-medium text-foreground">
                                {{ displayNameLabel }}
                            </dd>
                        </div>
                        <div
                            v-if="portfolioLimit"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Limite de envio
                            </dt>
                            <dd class="text-right font-medium text-foreground">
                                {{ portfolioLimit }}
                                <span
                                    class="block text-[11px] font-normal text-muted-foreground"
                                >
                                    teto do portfólio, compartilhado por todos
                                    os números
                                </span>
                            </dd>
                        </div>
                        <div
                            v-if="throughput"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Velocidade de envio
                            </dt>
                            <dd class="font-medium text-foreground">
                                {{ throughput }}
                            </dd>
                        </div>
                        <div
                            v-if="qualityChip"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Reputação do número
                            </dt>
                            <dd class="text-right">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium',
                                        qualityChip.class,
                                    ]"
                                >
                                    {{ qualityChip.label }}
                                </span>
                                <span
                                    class="mt-0.5 block text-[11px] leading-snug font-normal text-muted-foreground"
                                >
                                    {{ qualityChip.hint }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Acesso à conta
                            </dt>
                            <dd class="text-right">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium',
                                        tokenInfo.class,
                                    ]"
                                >
                                    {{ tokenInfo.label }}
                                </span>
                                <span
                                    v-if="tokenInfo.hint"
                                    class="mt-0.5 block text-[11px] leading-snug font-normal text-muted-foreground"
                                >
                                    {{ tokenInfo.hint }}
                                </span>
                            </dd>
                        </div>
                        <div
                            v-if="instance.meta_account_review_status"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Revisão da conta
                            </dt>
                            <dd class="font-medium text-foreground">
                                {{ instance.meta_account_review_status }}
                            </dd>
                        </div>
                        <div
                            v-if="instance.meta_ban_state"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Estado de banimento
                            </dt>
                            <dd class="font-medium text-red-400">
                                {{ instance.meta_ban_state }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- ── Conexão ─────────────────────────────────────────────── -->
                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Conexão
                    </h4>

                    <div class="space-y-1.5">
                        <Label
                            for="connection-name"
                            class="text-xs text-muted-foreground"
                        >
                            Nome da instância
                        </Label>
                        <Input
                            id="connection-name"
                            v-model="connectionName"
                            maxlength="100"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            for="connection-pin"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            Código PIN
                            <span
                                v-if="instance.has_registration_pin"
                                class="inline-flex items-center rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-400"
                            >
                                salvo
                            </span>
                        </Label>
                        <Input
                            id="connection-pin"
                            v-model="pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="6"
                            placeholder="••••••"
                        />
                        <p
                            class="text-[11px] leading-snug text-muted-foreground"
                        >
                            São os 6 dígitos da verificação em duas etapas deste
                            número no WhatsApp. Preencha apenas se precisar
                            ativar o número na Meta — para só trocar o nome,
                            deixe em branco.
                        </p>
                    </div>

                    <p
                        v-if="connectionError"
                        class="rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-[11px] leading-snug text-red-400"
                    >
                        {{ connectionError }}
                    </p>
                    <p
                        v-else-if="connectionSaved"
                        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[11px] leading-snug text-emerald-400"
                    >
                        {{ connectionSaved }}
                    </p>

                    <Button
                        type="button"
                        size="sm"
                        class="w-full"
                        :disabled="savingConnection || !connectionName.trim()"
                        @click="saveConnection"
                    >
                        {{ savingConnection ? 'Salvando…' : 'Salvar conexão' }}
                    </Button>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Identificação
                    </h4>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Telefone</dt>
                            <dd class="font-medium text-foreground">
                                {{ instance.phone_number ?? '-' }}
                            </dd>
                        </div>
                        <div
                            v-if="instance.meta_verified_name"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Nome da conta
                                <span class="block text-[11px]"
                                    >que o cliente vê</span
                                >
                            </dt>
                            <dd
                                class="truncate text-right font-medium text-foreground"
                            >
                                {{ instance.meta_verified_name }}
                            </dd>
                        </div>

                        <!-- The WABA is shown by name; its ID only matters when
                             someone is pasting it into Meta support, so it lives
                             behind the copy button instead of on screen. -->
                        <div
                            v-if="instance.meta_waba_id"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Conta WhatsApp Business
                            </dt>
                            <dd class="flex min-w-0 items-baseline gap-1">
                                <span
                                    class="truncate font-medium text-foreground"
                                >
                                    {{
                                        instance.meta_waba_name ??
                                        instance.meta_waba_id
                                    }}
                                </span>
                                <button
                                    type="button"
                                    class="shrink-0 self-center rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :title="
                                        copiedKey === 'waba'
                                            ? 'Copiado!'
                                            : 'Copiar identificador da conta'
                                    "
                                    @click="copy('waba', instance.meta_waba_id)"
                                >
                                    <Check
                                        v-if="copiedKey === 'waba'"
                                        class="h-3.5 w-3.5 text-emerald-400"
                                    />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </button>
                            </dd>
                        </div>

                        <div
                            v-if="verification"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">Verificação</dt>
                            <dd class="font-medium text-foreground">
                                {{ verification }}
                            </dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Total de leads
                            </dt>
                            <dd
                                class="font-medium text-foreground tabular-nums"
                            >
                                {{ instance.leads_count }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-2">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Atendimento por IA
                    </h4>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Agente</dt>
                            <dd class="truncate font-medium text-foreground">
                                {{
                                    instance.agent_name ??
                                    'Nenhum agente atribuído'
                                }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-muted-foreground">Modo padrão</dt>
                            <dd class="font-medium text-foreground">
                                {{ aiModeLabel }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    @click="emit('update:open', false)"
                    >Fechar</Button
                >
                <Button
                    variant="destructive"
                    size="sm"
                    @click="
                        emit('delete');
                        emit('update:open', false);
                    "
                    >Remover</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
