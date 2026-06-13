<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="Registration closed"
        description="New accounts are created by administrators. Contact your administrator for access."
    >
        <Head title="Register" />

        <div class="space-y-6 text-center">
            <p class="text-sm text-muted-foreground">
                Already have an account?
            </p>
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
            >
                Log in
            </TextLink>
        </div>
    </AuthBase>
</template>
