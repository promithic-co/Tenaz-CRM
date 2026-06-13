<script setup lang="ts">
import { Loader2, RefreshCw, Users } from 'lucide-vue-next';
import TagChip from '@/components/TagChip.vue';
import Button from '@/components/ui/button/Button.vue';
import type { PreviewState } from '@/composables/useFilterPreview';

defineProps<{
    state: PreviewState;
    rulesCount: number;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-foreground">Prévia</h3>
            <Button
                variant="ghost"
                size="sm"
                type="button"
                :disabled="rulesCount === 0 || state.loading.value"
                @click="state.refresh()"
            >
                <RefreshCw :class="['size-4 mr-1.5', state.loading.value ? 'animate-spin' : '']" />
                Atualizar prévia agora
            </Button>
        </div>

        <!-- Empty filters state -->
        <div
            v-if="rulesCount === 0"
            class="flex flex-col items-center justify-center py-10 text-center"
        >
            <Users class="size-10 text-muted-foreground/40 mb-3" />
            <p class="text-sm font-semibold text-foreground">Adicione um filtro pra ver a prévia</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Conforme você monta as regras, listamos aqui os leads que entram na lista.
            </p>
        </div>

        <!-- Error state -->
        <div
            v-else-if="state.error.value"
            class="flex flex-col items-center gap-3 py-8 text-center"
        >
            <p class="text-sm text-muted-foreground">{{ state.error.value }}</p>
            <Button variant="outline" size="sm" type="button" @click="state.refresh()">
                Tentar de novo
            </Button>
        </div>

        <!-- Results -->
        <template v-else>
            <!-- Count area with loading overlay -->
            <div class="relative">
                <!-- Loading skeleton overlay -->
                <div
                    v-if="state.loading.value && state.count.value === null"
                    class="flex items-center gap-2 py-1"
                >
                    <Loader2 class="size-4 animate-spin text-muted-foreground" />
                    <div class="h-6 w-40 animate-pulse rounded bg-muted" />
                </div>

                <!-- Count display -->
                <div v-else class="flex items-center gap-2">
                    <Loader2
                        v-if="state.loading.value"
                        class="size-4 animate-spin text-muted-foreground"
                    />
                    <!-- 0 leads match -->
                    <div
                        v-if="state.count.value === 0 && !state.capped.value"
                        class="flex flex-col items-start gap-0.5"
                    >
                        <p class="text-sm font-semibold text-foreground">Nenhum lead corresponde</p>
                        <p class="text-xs text-muted-foreground">
                            Ajuste os filtros pra ampliar o resultado.
                        </p>
                    </div>

                    <!-- Count display: capped, singular, plural -->
                    <p
                        v-else-if="state.count.value !== null || state.capped.value"
                        aria-live="polite"
                        class="text-lg tabular-nums font-semibold text-foreground"
                    >
                        <template v-if="state.capped.value">5000+ leads correspondem</template>
                        <template v-else-if="state.count.value === 1">1 lead corresponde</template>
                        <template v-else>{{ state.count.value }} leads correspondem</template>
                    </p>
                </div>
            </div>

            <!-- Sample list -->
            <div
                v-if="state.sample.value.length > 0"
                class="divide-y divide-border rounded-lg border border-border overflow-hidden"
            >
                <!-- Loading skeleton rows when refreshing -->
                <template v-if="state.loading.value && state.sample.value.length === 0">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="px-4 py-3 animate-pulse"
                    >
                        <div class="h-4 w-32 bg-muted rounded mb-1.5" />
                        <div class="h-3 w-24 bg-muted/60 rounded" />
                    </div>
                </template>

                <div
                    v-for="lead in state.sample.value"
                    :key="lead.id"
                    class="flex flex-col gap-1 px-4 py-3"
                >
                    <!-- Line 1: name + status -->
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-foreground flex-1 min-w-0 truncate">
                            {{ lead.nome }}
                        </span>
                        <span
                            class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ lead.status }}
                        </span>
                    </div>

                    <!-- Line 2: tags (max 4 + overflow) -->
                    <div v-if="lead.tags.length > 0" class="flex flex-wrap items-center gap-1">
                        <TagChip
                            v-for="(tag, idx) in lead.tags.slice(0, 4)"
                            :key="idx"
                            :tag="{ id: idx, ...tag }"
                        />
                        <span
                            v-if="lead.tags.length > 4"
                            class="text-xs text-muted-foreground"
                        >
                            +{{ lead.tags.length - 4 }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Loading skeleton for sample (initial load) -->
            <div
                v-else-if="state.loading.value"
                class="divide-y divide-border rounded-lg border border-border overflow-hidden"
            >
                <div
                    v-for="n in 3"
                    :key="n"
                    class="px-4 py-3 animate-pulse"
                >
                    <div class="h-4 w-32 bg-muted rounded mb-1.5" />
                    <div class="h-3 w-24 bg-muted/60 rounded" />
                </div>
            </div>
        </template>

        <!-- LGPD footer (always shown when rules exist) -->
        <p
            v-if="rulesCount > 0"
            class="text-xs text-muted-foreground border-t border-border pt-3 mt-auto"
        >
            Prévia não exibe telefone por privacidade (LGPD).
        </p>
    </div>
</template>
