<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProfessionalForm from '@/domains/professionals/components/ProfessionalForm.vue';
import type {
    ProfessionalLocationOption,
    SelectOption,
} from '@/domains/professionals/types';
import AppShell from '@/shared/layouts/AppShell.vue';

withDefaults(
    defineProps<{
        statuses?: SelectOption[];
        officeAssignmentTypes?: SelectOption[];
        locationOptions: ProfessionalLocationOption[];
    }>(),
    {
        statuses: undefined,
        officeAssignmentTypes: undefined,
    },
);
</script>

<template>
    <Head title="Nuevo profesional" />

    <AppShell
        title="Nuevo profesional"
        eyebrow="Equipo asistencial"
        description="Registrá sus datos, matrícula, modalidad de consultorio y horarios de atención."
    >
        <ProfessionalForm
            :statuses="statuses"
            :office-assignment-types="officeAssignmentTypes"
            :location-options="locationOptions"
        />
    </AppShell>
</template>
