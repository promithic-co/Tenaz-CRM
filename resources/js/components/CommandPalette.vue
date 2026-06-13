<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, LayoutGrid, MessageSquare, Headphones, Bot, Smartphone, FlaskConical, Users } from 'lucide-vue-next';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import StatusBadge from '@/components/StatusBadge.vue';
import type { Lead, Agent } from '@/types/models';

const open = ref(false);
const query = ref('');
const loading = ref(false);
const results = ref<{ leads: Lead[]; agents: Agent[] }>({ leads: [], agents: [] });
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const staticPages = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Conversas', href: '/conversas', icon: MessageSquare },
    { title: 'Contatos', href: '/contatos', icon: Users },
    { title: 'Atendimentos', href: '/atendimentos', icon: Headphones },
    { title: 'Agentes', href: '/agentes', icon: Bot },
    { title: 'WhatsApp', href: '/whatsapp', icon: Smartphone },
    { title: 'Playground', href: '/playground', icon: FlaskConical },
];

const filteredPages = computed(() => {
    if (!query.value) return staticPages;
    return staticPages.filter(p => p.title.toLowerCase().includes(query.value.toLowerCase()));
});

watch(query, (value) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (value.length < 2) {
        results.value = { leads: [], agents: [] };
        loading.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/search?q=${encodeURIComponent(value)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            results.value = await res.json();
        } finally {
            loading.value = false;
        }
    }, 300);
});

function handleKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        open.value = !open.value;
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

function navigateTo(href: string) {
    open.value = false;
    query.value = '';
    router.visit(href);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="p-0 gap-0 max-w-lg overflow-hidden">
            <div class="flex items-center border-b px-3">
                <Search class="h-4 w-4 text-muted-foreground shrink-0 mr-2" />
                <input
                    v-model="query"
                    autofocus
                    type="text"
                    placeholder="Buscar leads, agentes, páginas..."
                    class="flex-1 bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
                />
                <kbd class="text-xs text-muted-foreground border rounded px-1 py-0.5">Esc</kbd>
            </div>
            <div class="max-h-[400px] overflow-y-auto py-2">
                <div v-if="loading" class="flex justify-center py-6">
                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
                <template v-else>
                    <div v-if="results.leads.length > 0" class="px-2 pb-2">
                        <p class="px-2 py-1 text-xs font-medium text-muted-foreground uppercase tracking-wide">Leads</p>
                        <button
                            v-for="lead in results.leads"
                            :key="lead.id"
                            @click="navigateTo(`/conversas/${lead.id}`)"
                            class="w-full flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-accent text-left"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate">{{ lead.nome ?? lead.whatsapp }}</div>
                                <div class="text-xs text-muted-foreground truncate">{{ lead.whatsapp }}</div>
                            </div>
                            <StatusBadge :status="lead.status" />
                        </button>
                    </div>
                    <div v-if="results.agents.length > 0" class="px-2 pb-2">
                        <p class="px-2 py-1 text-xs font-medium text-muted-foreground uppercase tracking-wide">Agentes</p>
                        <button
                            v-for="agent in results.agents"
                            :key="agent.id"
                            @click="navigateTo(`/agentes/${agent.id}/config`)"
                            class="w-full flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-accent text-left"
                        >
                            <Bot class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span class="font-medium">{{ agent.name }}</span>
                        </button>
                    </div>
                    <div class="px-2">
                        <p class="px-2 py-1 text-xs font-medium text-muted-foreground uppercase tracking-wide">Páginas</p>
                        <button
                            v-for="page in filteredPages"
                            :key="page.href"
                            @click="navigateTo(page.href)"
                            class="w-full flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-accent text-left"
                        >
                            <component :is="page.icon" class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span>{{ page.title }}</span>
                        </button>
                    </div>
                    <div v-if="query.length >= 2 && !loading && results.leads.length === 0 && results.agents.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                        Nenhum resultado para "{{ query }}"
                    </div>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
