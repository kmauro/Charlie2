<script setup lang="ts">
import PatientForm from '@/domains/patients/components/PatientForm.vue';
import type {
    Patient,
    PatientOption,
    PatientPermissions,
} from '@/domains/patients/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    patient: Patient;
    statusOptions: PatientOption[];
    registeredSexOptions: PatientOption[];
    permissions: PatientPermissions;
}>();
</script>

<template>
    <Head :title="`Editar ${patient.display_name}`" />

    <AppShell
        :title="`Editar ${patient.display_name}`"
        eyebrow="Gestión de pacientes"
        description="Actualizá sus datos administrativos y contactos relacionados."
    >
        <PatientForm
            :patient="patient"
            :status-options="statusOptions"
            :registered-sex-options="registeredSexOptions"
        />
    </AppShell>
</template>
