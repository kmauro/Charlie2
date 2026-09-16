<script setup lang="ts">
import AppointmentForm from '@/domains/appointments/components/AppointmentForm.vue';
import type {
    AppointmentLocationOption,
    AppointmentOption,
    AppointmentPatientOption,
    AppointmentProfessionalOption,
} from '@/domains/appointments/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    patientOptions: AppointmentPatientOption[];
    professionalOptions: AppointmentProfessionalOption[];
    locationOptions: AppointmentLocationOption[];
    typeOptions: AppointmentOption[];
    statusOptions: AppointmentOption[];
    urls: {
        index: string;
        store: string;
    };
}>();
</script>

<template>
    <Head title="Nuevo turno" />

    <AppShell
        title="Nuevo turno"
        eyebrow="Agenda clínica"
        description="Reservá un espacio validando automáticamente la disponibilidad del paciente, profesional, sede y consultorio."
    >
        <AppointmentForm
            :patient-options="patientOptions"
            :professional-options="professionalOptions"
            :location-options="locationOptions"
            :type-options="typeOptions"
            :status-options="statusOptions"
            :index-url="urls.index"
            :submit-url="urls.store"
        />
    </AppShell>
</template>
