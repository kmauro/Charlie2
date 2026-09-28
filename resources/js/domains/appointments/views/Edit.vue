<script setup lang="ts">
import AppointmentForm from '@/domains/appointments/components/AppointmentForm.vue';
import type {
    AppointmentAgendaConstraints,
    AppointmentFormRecord,
    AppointmentLocationOption,
    AppointmentOption,
    AppointmentPatientOption,
    AppointmentProfessionalOption,
    AppointmentRoomOption,
} from '@/domains/appointments/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    appointment: AppointmentFormRecord;
    patientOptions: AppointmentPatientOption[];
    professionalOptions: AppointmentProfessionalOption[];
    locationOptions: AppointmentLocationOption[];
    roomOptions: AppointmentRoomOption[];
    agendaConstraints: AppointmentAgendaConstraints;
    typeOptions: AppointmentOption[];
    statusOptions: AppointmentOption[];
    urls: {
        index: string;
        update: string;
    };
}>();
</script>

<template>
    <Head :title="`Editar turno de ${appointment.patient.name}`" />

    <AppShell
        :title="`Editar turno de ${appointment.patient.name}`"
        eyebrow="Agenda clínica"
        description="Actualizá los datos de la reserva; los controles de disponibilidad se ejecutarán nuevamente."
    >
        <AppointmentForm
            :appointment="appointment"
            :patient-options="patientOptions"
            :professional-options="professionalOptions"
            :location-options="locationOptions"
            :room-options="roomOptions"
            :agenda-constraints="agendaConstraints"
            :type-options="typeOptions"
            :status-options="statusOptions"
            :index-url="urls.index"
            :submit-url="urls.update"
        />
    </AppShell>
</template>
