<script setup lang="ts">
import UserForm from '@/domains/users/components/UserForm.vue';
import type {
    PermissionOption,
    RoleOption,
} from '@/domains/users/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    roles: RoleOption[];
    permissionOptions: PermissionOption[];
}>();
</script>

<template>
    <Head title="Invitar usuario" />

    <AppShell
        title="Invitar usuario"
        eyebrow="Usuarios"
        description="Generá una invitación de uso único y definí el acceso inicial de la cuenta."
    >
        <UserForm
            mode="create"
            endpoint="/usuarios"
            :roles="roles"
            :permission-options="permissionOptions"
        />
    </AppShell>
</template>
