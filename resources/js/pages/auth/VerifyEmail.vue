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
        title="Verify email"
        description="Email verification is disabled. You can access your account."
    >
        <Head title="Email verification" />

        <div class="space-y-6 text-center">
            <TextLink :href="dashboard()" class="inline-block">
                Go to dashboard
            </TextLink>
            <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
                Log out
            </TextLink>
        </div>
    </AuthLayout>
</template>
