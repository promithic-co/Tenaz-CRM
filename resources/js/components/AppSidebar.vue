<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bot,
    ChevronRight,
    FileText,
    Headphones,
    Kanban,
    LayoutGrid,
    Megaphone,
    MessageSquare,
    Microscope,
    Phone,
    Shield,
    Smartphone,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const currentPath = computed(() => page.url);
const canManageAdmin = computed(() => {
    const role = page.props.auth?.currentRole;
    return role === 'owner' || role === 'administrator';
});

// Agentes submenu is open when on any /agente/* or /agentes* route
const agenteOpen = ref(
    currentPath.value.startsWith('/agente') ||
        currentPath.value.startsWith('/agentes'),
);

// Campanhas (plural) — D-01: renamed from "Disparos"
const campanhasOpen = ref(
    currentPath.value === '/campanhas' ||
        currentPath.value.startsWith('/campanhas/') ||
        currentPath.value.startsWith('/campanhas-voz') ||
        currentPath.value.startsWith('/voz') ||
        currentPath.value.startsWith('/ura') ||
        currentPath.value.startsWith('/listas-contato') ||
        currentPath.value.startsWith('/templates'),
);

// WhatsApp sub-submenu (3rd level) — D-02: Oficial
const whatsappSubOpen = ref(
    (currentPath.value === '/campanhas' ||
        currentPath.value.startsWith('/campanhas/')) &&
        !currentPath.value.startsWith('/campanhas-voz'),
);

// Voz (IVR) submenu
const vozOpen = ref(
    currentPath.value.startsWith('/voz') ||
        currentPath.value.startsWith('/campanhas-voz') ||
        currentPath.value.startsWith('/ura'),
);

const vozSubItems = [
    { title: 'Instância', href: '/voz' },
    { title: 'Campanhas', href: '/campanhas-voz' },
    { title: 'Integrações URA', href: '/ura' },
];

// Backoffice (super-admin only). The prefix is environment-configurable, so it
// comes from the server at runtime instead of being hardcoded here.
const backofficeBase = computed(
    () => `/${page.props.backoffice?.path ?? 'backoffice'}`,
);

const isSuperAdmin = computed(() => page.props.auth?.is_super_admin === true);

/**
 * Laboratory lives under the backoffice prefix and is gated by `super_admin`
 * server-side. It exposes infrastructure health, AI cost and an LLM sandbox
 * billed to the platform — operator tooling, never a tenant feature.
 */
const laboratorySubItems = computed(() => [
    { title: 'Dashboard', href: `${backofficeBase.value}/laboratory` },
    {
        title: 'Datasets',
        href: `${backofficeBase.value}/laboratory/datasets-page`,
    },
    {
        title: 'Stress Test',
        href: `${backofficeBase.value}/laboratory/stress-test`,
    },
    { title: 'AI Usage', href: `${backofficeBase.value}/laboratory/ai-usage` },
    { title: 'Health', href: `${backofficeBase.value}/laboratory/health` },
    { title: 'Playground', href: `${backofficeBase.value}/playground` },
]);

const laboratoryOpen = ref(
    currentPath.value.startsWith(`${backofficeBase.value}/laboratory`) ||
        currentPath.value.startsWith(`${backofficeBase.value}/playground`),
);

const agentSubItems = [
    { title: 'Ver agentes', href: '/agentes' },
    { title: 'Novo agente', href: '/agentes/create' },
    { title: 'Follow-up', href: '/agente/follow-up' },
];

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Conversas',
        href: '/conversas',
        icon: MessageSquare,
    },
    {
        title: 'Atendimentos',
        href: '/atendimentos',
        icon: Headphones,
    },
    {
        title: 'Kanban',
        href: '/pipeline',
        icon: Kanban,
    },
    {
        title: 'Contatos',
        href: '/contatos',
        icon: Users,
    },
    {
        title: 'WhatsApp',
        href: '/whatsapp',
        icon: Smartphone,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div class="px-2 py-0">
                <SidebarMenu>
                    <!-- Itens normais acima do Agente -->
                    <SidebarMenuItem
                        v-for="item in mainNavItems.slice(0, 4)"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="
                                currentPath === item.href ||
                                currentPath.startsWith(item.href + '/')
                            "
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Agentes com submenu colapsável -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            :is-active="
                                currentPath.startsWith('/agente') ||
                                currentPath.startsWith('/agentes')
                            "
                            tooltip="Agentes"
                            @click="agenteOpen = !agenteOpen"
                            class="cursor-pointer select-none"
                        >
                            <Bot />
                            <span>Agentes</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200"
                                :class="agenteOpen ? 'rotate-90' : ''"
                                :size="14"
                            />
                        </SidebarMenuButton>

                        <SidebarMenuSub v-if="agenteOpen">
                            <SidebarMenuSubItem
                                v-for="sub in agentSubItems"
                                :key="sub.href"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="
                                        currentPath === sub.href ||
                                        currentPath.startsWith(sub.href + '/')
                                    "
                                >
                                    <Link :href="sub.href">{{
                                        sub.title
                                    }}</Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>

                    <!-- Campanhas com submenu colapsável (D-01: renamed from "Disparos") -->
                    <SidebarMenuItem v-if="canManageAdmin">
                        <SidebarMenuButton
                            :is-active="
                                currentPath === '/campanhas' ||
                                currentPath.startsWith('/campanhas/') ||
                                currentPath.startsWith('/campanhas-voz') ||
                                currentPath.startsWith('/voz') ||
                                currentPath.startsWith('/ura') ||
                                currentPath.startsWith('/listas-contato') ||
                                currentPath.startsWith('/templates')
                            "
                            tooltip="Campanhas"
                            @click="campanhasOpen = !campanhasOpen"
                            class="cursor-pointer select-none"
                        >
                            <Megaphone />
                            <span>Campanhas</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200"
                                :class="campanhasOpen ? 'rotate-90' : ''"
                                :size="14"
                            />
                        </SidebarMenuButton>

                        <SidebarMenuSub v-if="campanhasOpen">
                            <!-- D-03.1: WhatsApp with 3rd-level submenu (D-02: Oficial) -->
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    :is-active="
                                        (currentPath === '/campanhas' ||
                                            currentPath.startsWith(
                                                '/campanhas/',
                                            )) &&
                                        !currentPath.startsWith(
                                            '/campanhas-voz',
                                        )
                                    "
                                    @click="whatsappSubOpen = !whatsappSubOpen"
                                    class="cursor-pointer select-none"
                                >
                                    <Smartphone :size="14" />
                                    <span>WhatsApp</span>
                                    <ChevronRight
                                        class="ml-auto transition-transform duration-200"
                                        :size="12"
                                        :class="
                                            whatsappSubOpen ? 'rotate-90' : ''
                                        "
                                    />
                                </SidebarMenuSubButton>
                                <!-- 3rd level: plain div with Tailwind indent (no SidebarMenuSubSub exists) -->
                                <div
                                    v-if="whatsappSubOpen"
                                    class="ml-3 flex flex-col gap-0.5 py-0.5 text-xs"
                                >
                                    <Link
                                        href="/campanhas"
                                        :class="[
                                            'block rounded px-3 py-1 transition-colors',
                                            (currentPath === '/campanhas' ||
                                                currentPath.startsWith(
                                                    '/campanhas/',
                                                )) &&
                                            !currentPath.startsWith(
                                                '/campanhas-voz',
                                            )
                                                ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                                : 'text-muted-foreground hover:bg-sidebar-accent/50 hover:text-foreground',
                                        ]"
                                    >
                                        Oficial
                                    </Link>
                                </div>
                            </SidebarMenuSubItem>

                            <!-- D-03.2: URA -->
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    :is-active="
                                        currentPath.startsWith('/voz') ||
                                        currentPath.startsWith(
                                            '/campanhas-voz',
                                        ) ||
                                        currentPath.startsWith('/ura')
                                    "
                                    @click="vozOpen = !vozOpen"
                                    class="cursor-pointer select-none"
                                >
                                    <Phone :size="14" />
                                    <span>URA</span>
                                    <ChevronRight
                                        class="ml-auto transition-transform duration-200"
                                        :size="12"
                                        :class="vozOpen ? 'rotate-90' : ''"
                                    />
                                </SidebarMenuSubButton>
                                <div
                                    v-if="vozOpen"
                                    class="ml-3 flex flex-col gap-0.5 py-0.5 text-xs"
                                >
                                    <Link
                                        v-for="sub in vozSubItems"
                                        :key="sub.href"
                                        :href="sub.href"
                                        :class="[
                                            'block rounded px-3 py-1 transition-colors',
                                            currentPath === sub.href ||
                                            currentPath.startsWith(
                                                sub.href + '/',
                                            )
                                                ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                                : 'text-muted-foreground hover:bg-sidebar-accent/50 hover:text-foreground',
                                        ]"
                                    >
                                        {{ sub.title }}
                                    </Link>
                                </div>
                            </SidebarMenuSubItem>

                            <!-- D-03.3: Listas de Contato -->
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="
                                        currentPath === '/listas-contato' ||
                                        currentPath.startsWith(
                                            '/listas-contato/',
                                        )
                                    "
                                >
                                    <Link href="/listas-contato">
                                        <Users :size="14" />
                                        <span>Listas de Contato</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>

                            <!-- D-03.4: Templates -->
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="
                                        currentPath === '/templates' ||
                                        currentPath.startsWith('/templates/')
                                    "
                                >
                                    <Link href="/templates">
                                        <FileText :size="14" />
                                        <span>Templates</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>

                    <!-- Laboratory com submenu (super-admin only) -->
                    <SidebarMenuItem v-if="isSuperAdmin">
                        <SidebarMenuButton
                            :is-active="
                                currentPath.startsWith(
                                    `${backofficeBase}/laboratory`,
                                ) ||
                                currentPath.startsWith(
                                    `${backofficeBase}/playground`,
                                )
                            "
                            tooltip="Laboratory"
                            @click="laboratoryOpen = !laboratoryOpen"
                            class="cursor-pointer select-none"
                        >
                            <Microscope />
                            <span>Laboratory</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200"
                                :class="laboratoryOpen ? 'rotate-90' : ''"
                                :size="14"
                            />
                        </SidebarMenuButton>

                        <SidebarMenuSub v-if="laboratoryOpen">
                            <SidebarMenuSubItem
                                v-for="sub in laboratorySubItems"
                                :key="sub.href"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="
                                        currentPath === sub.href ||
                                        currentPath.startsWith(sub.href + '/')
                                    "
                                >
                                    <Link :href="sub.href">{{
                                        sub.title
                                    }}</Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>

                    <!-- Pipeline de status and Campos adicionais used to hang off a
                         "Configurações" group here. They are tenant settings, so they
                         now live in the settings area with Team and Auto-tag, reachable
                         from the account menu below. -->

                    <!-- Backoffice (super-admin only) -->
                    <SidebarMenuItem v-if="isSuperAdmin">
                        <SidebarMenuButton
                            as-child
                            :is-active="currentPath.startsWith(backofficeBase)"
                            tooltip="Backoffice"
                        >
                            <Link :href="backofficeBase">
                                <Shield />
                                <span>Backoffice</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- WhatsApp -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="
                                currentPath === '/whatsapp' ||
                                currentPath.startsWith('/whatsapp/')
                            "
                            tooltip="WhatsApp"
                        >
                            <Link href="/whatsapp">
                                <Smartphone />
                                <span>WhatsApp</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <CommandPalette />
    <slot />
</template>
