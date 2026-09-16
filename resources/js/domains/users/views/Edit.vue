<script setup lang="ts">
import UserForm from '@/domains/users/components/UserForm.vue';
import type {
    EditableUser,
    PermissionOption,
    RoleOption,
    ScopeOption,
} from '@/domains/users/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    user: EditableUser;
    roles: RoleOption[];
    permissionOptions: PermissionOption[];
    legalEntityOptions: ScopeOption[];
    locationOptions: ScopeOption[];
}>();
</script>

<template>
    <Head :title="`Editar ${user.name}`" />

    <AppShell
        :title="`Editar ${user.name}`"
        eyebrow="Usuarios"
        description="Modificá el rol y los permisos individuales de la cuenta."
    >
        <UserForm
            mode="edit"
            :endpoint="`/usuarios/${props.user.id}`"
            :user="user"
            :roles="roles"
            :permission-options="permissionOptions"
            :legal-entity-options="legalEntityOptions"
            :location-options="locationOptions"
        />
    </AppShell>
</template>
