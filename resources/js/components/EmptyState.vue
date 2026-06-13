<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <component :is="icon" class="h-12 w-12 text-muted-foreground/50 mb-4" />
        <h3 class="font-semibold text-foreground mb-1">{{ title }}</h3>
        <p v-if="description" class="text-sm text-muted-foreground max-w-sm">{{ description }}</p>
        <div class="mt-4">
            <slot />
        </div>
    </div>
</template>
