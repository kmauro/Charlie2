<script setup lang="ts">
import AppointmentForm from '@/domains/appointments/components/AppointmentForm.vue';
import type {
    AppointmentAgendaConstraints,
    AppointmentLocationOption,
    AppointmentInitialValues,
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
    allowOverbook: boolean;
    urls: {
        index: string;
        store: string;
        recurringStore?: string;
    };
    initialValues?: AppointmentInitialValues;
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
            :allow-overbook="allowOverbook"
            :index-url="urls.index"
            :submit-url="urls.store"
            :recurring-submit-url="urls.recurringStore"
            :initial-values="initialValues"
        />
    </AppShell>
</template>
