<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProfessionalForm from '@/domains/professionals/components/ProfessionalForm.vue';
import type {
    Professional,
    ProfessionalLocationOption,
    SelectOption,
} from '@/domains/professionals/types';
import AppShell from '@/shared/layouts/AppShell.vue';

withDefaults(
    defineProps<{
        professional: Professional;
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
    <Head :title="`Editar ${professional.name}`" />

    <AppShell
        :title="`Editar ${professional.name}`"
        eyebrow="Equipo asistencial"
        description="Actualizá sus datos profesionales, modalidad de consultorio y horarios."
    >
        <ProfessionalForm
            :professional="professional"
            :statuses="statuses"
            :office-assignment-types="officeAssignmentTypes"
            :location-options="locationOptions"
        />
    </AppShell>
</template>
