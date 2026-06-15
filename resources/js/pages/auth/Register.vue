<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="Cadastro restrito"
        description="Novas contas são criadas pelos administradores. Fale com o administrador da sua conta para obter acesso."
    >
        <Head title="Criar conta" />

        <div class="space-y-6 text-center">
            <p class="text-sm text-muted-foreground">
                Já tem uma conta?
            </p>
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
            >
                Entrar
            </TextLink>
        </div>
    </AuthBase>
</template>
