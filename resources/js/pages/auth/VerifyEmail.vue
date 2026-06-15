<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { dashboard, logout } from '@/routes';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Verificar e-mail"
        description="A verificação de e-mail está desativada. Você já pode acessar sua conta."
    >
        <Head title="Verificação de e-mail" />

        <div class="space-y-6 text-center">
            <TextLink :href="dashboard()" class="inline-block">
                Ir para o painel
            </TextLink>
            <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
                Sair
            </TextLink>
        </div>
    </AuthLayout>
</template>
