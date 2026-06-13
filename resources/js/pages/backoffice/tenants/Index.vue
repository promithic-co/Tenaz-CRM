<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
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

        <div class="px-4 py-6">
            <div class="flex flex-col space-y-12 max-w-5xl">
                <Heading
                    title="Tenants"
                    description="Lista de organizações cadastradas na plataforma."
                />

                <div class="rounded-md border overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th
                                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide px-4 py-3 text-left"
                                    scope="col"
                                >
                                    Nome
                                </th>
                                <th
                                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide px-4 py-3 text-right"
                                    scope="col"
                                >
                                    Usuários
                                </th>
                                <th
                                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide px-4 py-3 text-right"
                                    scope="col"
                                >
                                    Agentes
                                </th>
                                <th
                                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide px-4 py-3 text-left"
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
                                <td class="px-4 py-3 font-medium">{{ tenant.name }}</td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ tenant.users_count }}</td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ tenant.agents_count }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatDate(tenant.created_at) }}</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="4" class="py-12 text-center text-muted-foreground">
                                    <p class="font-medium text-sm">Nenhum tenant encontrado</p>
                                    <p class="text-sm mt-1">Não há organizações cadastradas na plataforma.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
