<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb class="min-w-0 overflow-hidden">
        <BreadcrumbList class="min-w-0 flex-nowrap overflow-hidden">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem
                    class="min-w-0 shrink-0 last:flex-1"
                    :class="{ 'max-sm:hidden': index < breadcrumbs.length - 1 }"
                >
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="block truncate">{{
                            item.title
                        }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="shrink-0 max-sm:hidden"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
