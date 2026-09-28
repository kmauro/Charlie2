<script setup lang="ts">
import AppointmentForm from '@/domains/appointments/components/AppointmentForm.vue';
import type {
    AppointmentAgendaConstraints,
    AppointmentLocationOption,
    AppointmentOption,
    AppointmentPatientOption,
    AppointmentProfessionalOption,
    AppointmentRoomOption,
} from '@/domains/appointments/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    patientOptions: AppointmentPatientOption[];
    professionalOptions: AppointmentProfessionalOption[];
    locationOptions: AppointmentLocationOption[];
    roomOptions: AppointmentRoomOption[];
    agendaConstraints: AppointmentAgendaConstraints;
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
            :room-options="roomOptions"
            :agenda-constraints="agendaConstraints"
            :type-options="typeOptions"
            :status-options="statusOptions"
            :index-url="urls.index"
            :submit-url="urls.store"
        />
    </AppShell>
</template>
