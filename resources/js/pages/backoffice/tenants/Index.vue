<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Tenant = {
    id: number;
    name: string;
    users_count: number;
    agents_count: number;
    created_at: string;
};

defineProps<{
    tenants: Tenant[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Backoffice', href: '/backoffice' },
    { title: 'Tenants', href: '/backoffice/tenants' },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Tenants" />

        <div class="px-3 py-4 sm:px-4 sm:py-6">
            <div class="flex max-w-5xl flex-col space-y-12">
                <Heading
                    title="Tenants"
                    description="Lista de organizações cadastradas na plataforma."
                />

                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead>
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Nome
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Usuários
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Agentes
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    scope="col"
                                >
                                    Criado em
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="tenants.length > 0">
                            <tr
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                class="border-t"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ tenant.name }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ tenant.users_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ tenant.agents_count }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDate(tenant.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td
                                    colspan="4"
                                    class="py-12 text-center text-muted-foreground"
                                >
                                    <p class="text-sm font-medium">
                                        Nenhum tenant encontrado
                                    </p>
                                    <p class="mt-1 text-sm">
                                        Não há organizações cadastradas na
                                        plataforma.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
