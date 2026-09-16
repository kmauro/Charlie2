<script setup lang="ts">
import PatientForm from '@/domains/patients/components/PatientForm.vue';
import type {
    PatientOption,
    PatientPermissions,
} from '@/domains/patients/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    statusOptions: PatientOption[];
    registeredSexOptions: PatientOption[];
    permissions: PatientPermissions;
}>();
</script>

<template>
    <Head title="Nuevo paciente" />

    <AppShell
        title="Nuevo paciente"
        eyebrow="Gestión de pacientes"
        description="Creá una ficha única con identidad, cobertura y contactos relacionados."
    >
        <PatientForm
            :status-options="statusOptions"
            :registered-sex-options="registeredSexOptions"
        />
    </AppShell>
</template>
