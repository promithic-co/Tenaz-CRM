<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppWordmark from '@/components/AppWordmark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-paper p-6 md:p-10"
    >
        <!-- Faint grid texture, mirrors the marketing landing -->
        <div
            class="pointer-events-none absolute inset-0 -z-10"
            style="
                background-image:
                    linear-gradient(rgba(17, 17, 17, 0.035) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(17, 17, 17, 0.035) 1px, transparent 1px);
                background-size: 44px 44px;
                mask-image: linear-gradient(to bottom, black 0%, transparent 80%);
            "
            aria-hidden="true"
        ></div>

        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-6">
                <Link :href="home()" aria-label="Tenaz CRM">
                    <AppWordmark class="h-7 w-auto text-ink" />
                </Link>

                <div
                    class="w-full rounded-[8px] border border-ink bg-surface p-8 shadow-[8px_8px_0_var(--color-gold)]"
                >
                    <div class="mb-6 space-y-1.5 text-center">
                        <h1 class="font-display text-3xl leading-tight text-ink">
                            {{ title }}
                        </h1>
                        <p class="text-sm text-ink-soft">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
