<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import InstanceCard from './InstanceCard.vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type Instance = {
    id: number;
    name: string;
    display_name: string | null;
    label: string;
    api_url: string;
    phone_number: string | null;
    provider: 'meta_cloud';

    meta_waba_id: string | null;
    meta_phone_number_id: string | null;
    meta_quality_rating: string | null;
    meta_token_permanent: boolean;
    meta_token_expires_at: string | null;
    meta_coexistence: boolean;

    agent_id: number | null;
    agent_name: string | null;
    default_ai_mode: string | null;

    leads_count: number;

    has_proxy: boolean;
    proxy_host: string | null;
    proxy_port: number | null;
};

type Props = {
    instances: Instance[];
    flash: string | null;
    return_to: string | null;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'WhatsApp', href: '/whatsapp' },
];

// ─── Add Instance ─────────────────────────────────────────────────────────────

const showAddDialog = ref(false);

const addForm = useForm({
    display_name: '',
    name: '',
    provider: 'meta_cloud' as const,
    meta_signup_token: '',
    meta_pin: '',
});

// ─── Meta Embedded Signup ─────────────────────────────────────────────────────
//
// Stage flow:
//   'select'     → user picks connection mode + clicks Continue (launches FB.login)
//   'processing' → FB popup is open or backend is exchanging the code for a token
//   'confirm'    → token cached, ready to create the instance (optional PIN)
//
// Note about modes: 'new' and 'migrate' use the SAME Meta Login Configuration
// (META_APP_CONFIG_ID, no featureType). Facebook decides between activating a
// new number vs. migrating an existing WABA inside the popup itself. Only
// 'coexistence' needs a separate config_id (META_APP_CONFIG_ID_COEXISTENCE
// with featureType=coexist), which is why we still ask the user up front.

type MetaMode = 'new' | 'migrate' | 'coexistence';
type MetaStage = 'select' | 'processing' | 'confirm';

const metaStage = ref<MetaStage>('select');
const metaProcessing = ref(false);
const metaError = ref('');
const metaPhoneNumberId = ref('');
const metaWabaId = ref('');
const metaMode = ref<MetaMode>('new');

const metaModeLabel: Record<MetaMode, string> = {
    new: 'Número novo (OTP)',
    migrate: 'Migrar número existente (OTP)',
    coexistence: 'Coexistência (QR code)',
};

const metaConfigId = () =>
    metaMode.value === 'coexistence'
        ? ((window as any).__META_CONFIG_ID_COEXISTENCE__ ?? '')
        : ((window as any).__META_CONFIG_ID__ ?? '');

const needsPin = () => metaMode.value !== 'coexistence';

function loadFbSdk(): Promise<void> {
    return new Promise((resolve, reject) => {
        const appId = (window as any).__META_APP_ID__;
        if (!appId) {
            reject(new Error('META_APP_ID não configurado.'));
            return;
        }

        if ((window as any).FB) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://connect.facebook.net/en_US/sdk.js';
        script.onerror = () =>
            reject(new Error('Falha ao carregar o SDK do Facebook.'));
        script.onload = () => {
            (window as any).FB.init({
                appId,
                autoLogAppEvents: true,
                xfbml: false,
                version: 'v23.0',
            });
            resolve();
        };
        document.head.appendChild(script);
    });
}

function launchEmbeddedSignup(): void {
    metaError.value = '';

    if (!metaConfigId()) {
        metaError.value =
            'Configuração do Meta (META_APP_CONFIG_ID) não definida no servidor. Contate o suporte.';
        return;
    }

    // If SDK already loaded, call FB.login() synchronously to preserve the user-gesture context
    // (async/await before FB.login() causes popup blockers to reject the window)
    if ((window as any).FB) {
        openFbLogin();
        return;
    }

    metaProcessing.value = true;
    metaStage.value = 'processing';
    loadFbSdk()
        .then(() => openFbLogin())
        .catch((err: any) => {
            metaError.value =
                err?.message ??
                'Não foi possível carregar o SDK do Facebook. Verifique sua conexão.';
            metaProcessing.value = false;
            metaStage.value = 'select';
        });
}

function openFbLogin(): void {
    metaProcessing.value = true;
    metaStage.value = 'processing';
    (window as any).FB.login(
        (response: any) => {
            if (response.authResponse?.code) {
                handleSignupCode(response.authResponse.code);
            } else {
                metaError.value = 'Signup cancelado ou sem autorização.';
                metaProcessing.value = false;
                metaStage.value = 'select';
            }
        },
        {
            config_id: metaConfigId(),
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                featureType: metaMode.value === 'coexistence' ? 'coexist' : '',
                sessionInfoVersion: '3',
            },
        },
    );
}

// Pre-load FB SDK on mount so FB.login() fires synchronously on click (avoid popup blockers)
onMounted(() => {
    loadFbSdk().catch(() => {});
});

async function handleSignupCode(code: string): Promise<void> {
    try {
        const csrfToken = csrf();
        const res = await fetch('/whatsapp/meta/embedded-signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                code,
                waba_id: metaWabaId.value || '',
                phone_number_id: metaPhoneNumberId.value || '',
                mode: metaMode.value,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            metaError.value = data.message || 'Falha ao processar o signup.';
            metaStage.value = 'select';
            return;
        }

        addForm.meta_signup_token = data.signup_token;
        metaPhoneNumberId.value = data.phone_number_id || '';
        metaWabaId.value = data.waba_id || '';
        metaStage.value = 'confirm';
    } catch {
        metaError.value = 'Erro de rede ao processar o signup.';
        metaStage.value = 'select';
    } finally {
        metaProcessing.value = false;
    }
}

// Listen for the WABA/phone IDs from the JS SDK FINISH event
if (typeof window !== 'undefined') {
    window.addEventListener('message', (event) => {
        if (
            event.origin !== 'https://www.facebook.com' &&
            event.origin !== 'https://web.facebook.com'
        )
            return;
        try {
            const data =
                typeof event.data === 'string'
                    ? JSON.parse(event.data)
                    : event.data;
            if (
                data?.type === 'WA_EMBEDDED_SIGNUP' &&
                data?.event === 'FINISH'
            ) {
                metaPhoneNumberId.value = data?.data?.phone_number_id ?? '';
                metaWabaId.value = data?.data?.waba_id ?? '';
            }
        } catch {
            /* ignore */
        }
    });
}

function resetMetaState(): void {
    metaStage.value = 'select';
    metaProcessing.value = false;
    metaError.value = '';
    metaPhoneNumberId.value = '';
    metaWabaId.value = '';
    metaMode.value = 'new';
    addForm.meta_signup_token = '';
    addForm.meta_pin = '';
}

function submitAdd(): void {
    addForm.post('/whatsapp', {
        onSuccess: () => {
            addForm.reset();
            showAddDialog.value = false;
            resetMetaState();
        },
    });
}

function closeDialog(): void {
    showAddDialog.value = false;
    addForm.reset();
    addForm.clearErrors();
    resetMetaState();
}

// ─── Delete ───────────────────────────────────────────────────────────────────

const confirmDeleteId = ref<number | null>(null);

function deleteInstance(id: number): void {
    router.delete(`/whatsapp/${id}`);
    confirmDeleteId.value = null;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function csrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Head title="WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 lg:p-8">
            <!-- Flash -->
            <div
                v-if="flash"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ flash }}
            </div>

            <!-- Return to onboarding CTA (only from allowlisted prop) -->
            <div
                v-if="return_to === '/onboarding'"
                class="flex items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3"
            >
                <Link
                    href="/onboarding"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    ← Voltar ao onboarding
                </Link>
                <span class="text-xs text-muted-foreground"
                    >Conecte uma instância e volte para concluir a
                    configuração.</span
                >
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base font-semibold text-foreground">
                                WhatsApp
                            </h1>
                            <Badge
                                v-if="instances.length"
                                variant="outline"
                                class="tabular-nums"
                            >
                                {{ instances.length }}
                            </Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Gerencie suas conexões WhatsApp.
                        </p>
                    </div>
                </div>

                <Button size="sm" @click="showAddDialog = true">
                    + Nova instância
                </Button>
            </div>

            <!-- Instance Grid -->
            <div
                v-if="instances.length"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <InstanceCard
                    v-for="instance in instances"
                    :key="instance.id"
                    :instance="instance"
                    :csrf="csrf()"
                    @delete="confirmDeleteId = instance.id"
                />
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-xl border border-dashed border-sidebar-border/70 bg-card p-12 text-center dark:border-sidebar-border"
            >
                <div
                    class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="text-muted-foreground"
                    >
                        <path
                            d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                        />
                    </svg>
                </div>
                <p class="text-sm font-medium text-foreground">
                    Nenhuma instância configurada
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Clique em "+ Nova instância" para adicionar sua primeira
                    conexão WhatsApp.
                </p>
                <Button class="mt-4" size="sm" @click="showAddDialog = true">
                    + Nova instância
                </Button>
            </div>
        </div>
    </AppLayout>

    <!-- ─── Add Instance Dialog ──────────────────────────────────────────────── -->
    <Dialog
        :open="showAddDialog"
        @update:open="
            (v: boolean) => {
                if (!v) closeDialog();
            }
        "
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Nova instância</DialogTitle>
                <DialogDescription
                    >Configure uma nova conexão WhatsApp.</DialogDescription
                >
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submitAdd">
                <!-- Provedor: Meta Cloud API (fixo) -->
                <div
                    class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2"
                >
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        class="shrink-0"
                    >
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.768.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.464 3.488"
                            fill="#25D366"
                        />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-foreground">
                            Conexão via Meta Cloud API
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            WhatsApp Business Platform oficial da Meta.
                        </p>
                    </div>
                </div>

                <!-- Campos comuns: nome -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label
                            >Apelido
                            <span class="text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <Input
                            v-model="addForm.display_name"
                            placeholder="ex: Vendas SP"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            >Nome da instância
                            <span class="text-red-500">*</span></Label
                        >
                        <Input
                            v-model="addForm.name"
                            class="font-mono"
                            placeholder="ex: vendas-sp"
                        />
                        <p
                            v-if="addForm.errors.name"
                            class="text-xs text-red-500"
                        >
                            {{ addForm.errors.name }}
                        </p>
                    </div>
                </div>

                <!-- ── Embedded Signup ───────────────────────────────────────── -->

                <!-- Stage: select mode + launch FB.login() -->
                <template v-if="metaStage === 'select'">
                    <div class="space-y-2">
                        <Label>Como conectar este número?</Label>
                        <div class="space-y-2">
                            <button
                                v-for="opt in [
                                    {
                                        value: 'new',
                                        label: 'Número novo',
                                        desc: 'Ative um número que ainda não usa o WhatsApp Business API. Você receberá um código OTP por chamada ou SMS.',
                                    },
                                    {
                                        value: 'migrate',
                                        label: 'Migrar número existente',
                                        desc: 'Transfira um número que usa o app WhatsApp Business para a Cloud API. O app original será desativado.',
                                    },
                                    {
                                        value: 'coexistence',
                                        label: 'Coexistência (manter app)',
                                        desc: 'Use a Cloud API e o app WhatsApp Business ao mesmo tempo via QR code. Disponível para BR/MX/IN. Limite: 20 msg/seg.',
                                    },
                                ] as const"
                                :key="opt.value"
                                type="button"
                                :class="[
                                    'w-full rounded-lg border px-3 py-2.5 text-left text-xs transition-colors',
                                    metaMode === opt.value
                                        ? 'border-primary bg-primary/5 dark:bg-primary/10'
                                        : 'border-border bg-card hover:border-muted-foreground/40',
                                ]"
                                @click="metaMode = opt.value"
                            >
                                <span class="font-semibold text-foreground">{{
                                    opt.label
                                }}</span>
                                <p class="mt-0.5 text-muted-foreground">
                                    {{ opt.desc }}
                                </p>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="metaError"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400"
                    >
                        {{ metaError }}
                    </div>
                </template>

                <!-- Stage: processing FB popup / token exchange -->
                <template v-if="metaStage === 'processing'">
                    <div class="flex flex-col items-center gap-3 py-6">
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent"
                        />
                        <p class="text-sm text-muted-foreground">
                            Aguardando autorização do Facebook...
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Conclua o fluxo na janela do Facebook que abriu.
                        </p>
                    </div>
                </template>

                <!-- Stage: confirm + optional PIN -->
                <template v-if="metaStage === 'confirm'">
                    <div
                        class="space-y-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-2.5 text-xs text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <div class="flex items-center gap-2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                            <span class="font-semibold"
                                >Conta vinculada com sucesso!</span
                            >
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 pl-1">
                            <span class="text-green-600 dark:text-green-500"
                                >Modo:</span
                            >
                            <span class="font-medium">{{
                                metaModeLabel[metaMode]
                            }}</span>
                            <span class="text-green-600 dark:text-green-500"
                                >WABA ID:</span
                            >
                            <span class="font-mono font-medium">{{
                                metaWabaId || '—'
                            }}</span>
                            <span class="text-green-600 dark:text-green-500"
                                >Phone Number ID:</span
                            >
                            <span class="font-mono font-medium">{{
                                metaPhoneNumberId || '—'
                            }}</span>
                        </div>
                    </div>

                    <!-- PIN only for new / migrate modes -->
                    <div v-if="needsPin()" class="space-y-1.5">
                        <Label
                            >PIN de registro
                            <span class="text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <Input
                            v-model="addForm.meta_pin"
                            class="w-32 font-mono"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="000000"
                        />
                        <p class="text-xs text-muted-foreground">
                            PIN de 6 dígitos para registro do número na Cloud
                            API. Deixe em branco para usar
                            <code>000000</code> (padrão).
                        </p>
                        <p
                            v-if="addForm.errors.meta_pin"
                            class="text-xs text-red-500"
                        >
                            {{ addForm.errors.meta_pin }}
                        </p>
                    </div>

                    <p
                        v-if="addForm.errors.meta_signup_token"
                        class="text-xs text-red-500"
                    >
                        {{ addForm.errors.meta_signup_token }}
                    </p>
                </template>

                <!-- ── Footer ─────────────────────────────────────────────────── -->
                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog"
                        >Cancelar</Button
                    >

                    <!-- Stage 'select': single FB CTA. Must call FB.login synchronously to avoid popup blockers. -->
                    <Button
                        v-if="metaStage === 'select'"
                        type="button"
                        class="bg-[#1877F2] text-white hover:bg-[#1877F2]/90"
                        :disabled="!addForm.name"
                        @click="launchEmbeddedSignup()"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="mr-1.5"
                        >
                            <path
                                d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073"
                            />
                        </svg>
                        Continuar com Facebook
                    </Button>

                    <!-- Stage 'confirm': submit -->
                    <Button
                        v-else-if="metaStage === 'confirm'"
                        type="submit"
                        :disabled="
                            addForm.processing || !addForm.meta_signup_token
                        "
                    >
                        {{
                            addForm.processing
                                ? 'Criando...'
                                : 'Criar instância'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ─── Delete Confirm Modal ─────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="confirmDeleteId !== null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="confirmDeleteId = null"
        >
            <div class="w-full max-w-sm rounded-xl bg-card p-6 shadow-xl">
                <h3 class="text-sm font-semibold text-foreground">
                    Remover instância
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Tem certeza? A instância será removida do sistema. O
                    WhatsApp não será desconectado automaticamente.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="confirmDeleteId = null"
                        >Cancelar</Button
                    >
                    <Button
                        variant="destructive"
                        size="sm"
                        @click="deleteInstance(confirmDeleteId!)"
                        >Remover</Button
                    >
                </div>
            </div>
        </div>
    </Teleport>
</template>
