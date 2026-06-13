<script setup lang="ts">
import TeamController from '@/actions/App/Http/Controllers/Settings/TeamController';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as teamIndex } from '@/routes/team';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_current_user: boolean;
};

type Invitation = {
    id: number;
    email: string;
    role: string;
    role_label: string;
    expires_at: string | null;
    is_expired: boolean;
};

type RoleOption = { value: string; label: string };

defineProps<{
    members: Member[];
    invitations: Invitation[];
    assignable_roles: RoleOption[];
}>();

const selectedRole = ref<string>('user');

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Equipe', href: teamIndex() },
];

function cancelInvitation(id: number) {
    if (!confirm('Cancelar este convite?')) return;
    router.delete(`/settings/team/invitations/${id}`, { preserveScroll: true });
}

function removeMember(id: number) {
    if (!confirm('Remover este membro da equipe?')) return;
    router.delete(`/settings/team/members/${id}`, { preserveScroll: true });
}

function changeRole(id: number, role: string) {
    router.patch(`/settings/team/members/${id}`, { role }, { preserveScroll: true });
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Equipe" />

        <SettingsLayout>
            <div class="flex flex-col space-y-12">
                <section class="space-y-6">
                    <Heading
                        variant="small"
                        title="Convidar membro"
                        description="Envie um convite por email com o perfil de acesso."
                    />

                    <Form
                        v-bind="TeamController.inviteStore.form()"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                placeholder="email@exemplo.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="role">Perfil</Label>
                            <Select v-model="selectedRole" name="role">
                                <SelectTrigger id="role">
                                    <SelectValue placeholder="Selecionar perfil" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in assignable_roles"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="role" :value="selectedRole" />
                            <InputError :message="errors.role" />
                        </div>

                        <Button type="submit" :disabled="processing">
                            Enviar convite
                        </Button>
                    </Form>
                </section>

                <section class="space-y-4">
                    <Heading
                        variant="small"
                        title="Convites pendentes"
                        description="Convites ainda não aceitos."
                    />

                    <div v-if="invitations.length === 0" class="text-sm text-muted-foreground">
                        Nenhum convite pendente.
                    </div>

                    <ul v-else class="divide-y rounded-md border">
                        <li
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="flex items-center justify-between px-4 py-3"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium">{{ invitation.email }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ invitation.role_label }} ·
                                    {{ invitation.is_expired ? 'Expirado' : `Expira em ${formatDate(invitation.expires_at)}` }}
                                </span>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="cancelInvitation(invitation.id)"
                            >
                                Cancelar
                            </Button>
                        </li>
                    </ul>
                </section>

                <section class="space-y-4">
                    <Heading
                        variant="small"
                        title="Membros"
                        description="Usuários com acesso a esta organização."
                    />

                    <ul class="divide-y rounded-md border">
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium">
                                    {{ member.name }}
                                    <span v-if="member.is_current_user" class="text-xs text-muted-foreground">
                                        (você)
                                    </span>
                                </span>
                                <span class="text-xs text-muted-foreground">{{ member.email }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <template v-if="member.role === 'owner'">
                                    <span class="rounded bg-muted px-2 py-1 text-xs font-medium">
                                        {{ member.role_label }}
                                    </span>
                                </template>
                                <template v-else>
                                    <Select
                                        :model-value="member.role"
                                        @update:model-value="(value) => changeRole(member.id, value as string)"
                                    >
                                        <SelectTrigger class="w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in assignable_roles"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        v-if="!member.is_current_user"
                                        variant="ghost"
                                        size="sm"
                                        @click="removeMember(member.id)"
                                    >
                                        Remover
                                    </Button>
                                </template>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
