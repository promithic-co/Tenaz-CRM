<script setup lang="ts">
import { computed } from 'vue';
import { statusLabel, statusClasses } from '@/lib/lead-status';

const props = defineProps<{
    status: string;
}>();

const label = computed(() => statusLabel(props.status));
const classes = computed(() => statusClasses(props.status));
</script>

<template>
    <span
        :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap', classes]"
    >
        {{ label }}
    </span>
</template>
