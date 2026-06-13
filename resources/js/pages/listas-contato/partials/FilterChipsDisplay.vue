<script setup lang="ts">
interface ChipGroup {
    label: string;
    values: string[];
    modifier?: string;
}

defineProps<{ groups: ChipGroup[] }>();
</script>

<template>
    <div v-if="groups.length === 0" class="text-sm text-muted-foreground">
        Nenhum filtro aplicado.
    </div>
    <p v-else class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-foreground">
        <template v-for="(group, i) in groups" :key="i">
            <span class="inline-flex items-center gap-1">
                <span class="font-semibold">{{ group.label }}:</span>
                <span>{{ group.values.join(', ') }}</span>
                <span v-if="group.modifier" class="text-muted-foreground">{{ group.modifier }}</span>
            </span>
            <span v-if="i < groups.length - 1" aria-hidden="true" class="text-muted-foreground"> | </span>
        </template>
    </p>
</template>
