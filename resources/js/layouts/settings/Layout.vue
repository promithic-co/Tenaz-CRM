<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editAutoTag } from '@/routes/auto-tag';
import { edit as editProfile } from '@/routes/profile';
import { index as camposIndex } from '@/routes/settings/campos';
import { index as pipelineIndex } from '@/routes/settings/pipeline';
import { index as teamIndex } from '@/routes/team';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItem } from '@/types';

/**
 * The pipeline editor and the extra-fields CRUD are wide tables, not the narrow
 * forms the rest of this area holds, so they opt out of the reading-width column.
 */
withDefaults(defineProps<{ wide?: boolean }>(), { wide: false });

const page = usePage();
const canManageTeam = computed(() => {
    const role = page.props.auth?.currentRole;
    return role === 'owner' || role === 'administrator';
});

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Profile', href: editProfile() },
        { title: 'Password', href: editPassword() },
        { title: 'Two-factor auth', href: show() },
        { title: 'Appearance', href: editAppearance() },
    ];

    // Tenant-wide configuration, not account preferences — hence the same guard
    // the team page uses. They used to live behind their own sidebar group,
    // which meant two doors into the same "settings" idea.
    if (canManageTeam.value) {
        items.push({ title: 'Team', href: teamIndex() });
        items.push({ title: 'Auto-tag IA', href: editAutoTag() });
        items.push({ title: 'Pipeline de status', href: pipelineIndex() });
        items.push({ title: 'Campos adicionais', href: camposIndex() });
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-3 py-4 sm:px-4 sm:py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'min-h-10 w-auto shrink-0 justify-start lg:w-full',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div :class="wide ? 'min-w-0 flex-1' : 'flex-1 md:max-w-2xl'">
                <section :class="wide ? 'space-y-12' : 'max-w-xl space-y-12'">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
