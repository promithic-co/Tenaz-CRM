<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    token: string;
    invitation: {
        email: string;
        role: string;
        role_label: string;
        tenant_name: string | null;
        invited_by: string | null;
        expires_at: string | null;
    };
    existing_user: boolean;
}>();

function submit(event: Event) {
    event.preventDefault();
    const form = event.target as HTMLFormElement;
    const data = new FormData(form);
    const payload: Record<string, string> = {};
    data.forEach((value, key) => {
        payload[key] = value as string;
    });

    router.post(`/invite/${props.token}`, payload, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AuthBase
        :title="`Entrar em ${invitation.tenant_name ?? 'sua equipe'}`"
        :description="invitation.invited_by
            ? `${invitation.invited_by} convidou você como ${invitation.role_label}`
            : `Você foi convidado como ${invitation.role_label}`"
    >
        <Head title="Aceitar convite" />

        <form
            @submit="submit"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :model-value="invitation.email"
                    disabled
                />
                <p class="text-xs text-muted-foreground">
                    Este convite está vinculado a este endereço de email.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="name">Seu nome</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    :disabled="existing_user"
                />
                <p v-if="existing_user" class="text-xs text-muted-foreground">
                    Detectamos que você já tem uma conta. Use sua senha atual para entrar.
                </p>
                <InputError :message="($page.props.errors as Record<string, string>).name" />
            </div>

            <div class="grid gap-2">
                <Label for="password">
                    {{ existing_user ? 'Sua senha atual' : 'Crie uma senha' }}
                </Label>
                <Input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="($page.props.errors as Record<string, string>).password" />
            </div>

            <div v-if="!existing_user" class="grid gap-2">
                <Label for="password_confirmation">Confirme a senha</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
            </div>

            <Button
                type="submit"
                class="w-full border border-ink bg-ink text-white shadow-[4px_4px_0_var(--color-gold)] transition hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-ink hover:shadow-[6px_6px_0_var(--color-gold)]"
            >
                Aceitar convite
            </Button>
        </form>
    </AuthBase>
</template>
