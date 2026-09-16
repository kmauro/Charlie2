<script setup lang="ts">
import type {
    AppointmentFormData,
    AppointmentFormRecord,
    AppointmentLocationOption,
    AppointmentOption,
    AppointmentPatientOption,
    AppointmentProfessionalOption,
} from '@/domains/appointments/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import SearchableCombobox from '@/shared/components/SearchableCombobox.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        patientOptions: AppointmentPatientOption[];
        professionalOptions: AppointmentProfessionalOption[];
        locationOptions: AppointmentLocationOption[];
        typeOptions: AppointmentOption[];
        statusOptions?: AppointmentOption[];
        appointment?: AppointmentFormRecord;
        indexUrl?: string;
        submitUrl: string;
    }>(),
    {
        statusOptions: () => [],
        appointment: undefined,
        indexUrl: '/turnos',
    },
);

const isEditing = computed(() => props.appointment !== undefined);

const localDateValue = (date: Date = new Date()): string => {
    const localDate = new Date(date);
    localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());

    return localDate.toISOString().slice(0, 10);
};

const initialStart = props.appointment?.starts_at
    .replace(' ', 'T')
    .slice(0, 16) ?? '';
const minimumDate = localDateValue();
const appointmentDate = ref(initialStart.slice(0, 10) || minimumDate);
const appointmentTime = ref(initialStart.slice(11, 16));

const form = useForm<AppointmentFormData>({
    patient_id: props.appointment?.patient.id ?? '',
    professional_id: props.appointment?.professional.id ?? '',
    location_id: props.appointment?.location.id ?? '',
    starts_at: initialStart,
    type: props.appointment?.type.value ?? props.typeOptions[0]?.value ?? 'in_person',
    office: props.appointment?.office ?? '',
    reason: props.appointment?.reason ?? '',
    ...(props.appointment
        ? { lock_version: props.appointment.lock_version }
        : {}),
});

const selectedProfessional = computed(() =>
    props.professionalOptions.find(
        (professional) => professional.value === Number(form.professional_id),
    ),
);

const selectedLocation = computed(() =>
    props.locationOptions.find(
        (location) => location.value === Number(form.location_id),
    ),
);

const patientComboboxOptions = computed(() =>
    props.patientOptions.map((patient) => ({
        value: patient.value,
        label: patient.label,
        description: patient.document_number
            ? `DNI ${patient.document_number}`
            : undefined,
        searchText: `${patient.label} ${patient.document_number ?? ''}`,
    })),
);

const professionalComboboxOptions = computed(() =>
    props.professionalOptions.map((professional) => ({
        value: professional.value,
        label: professional.label,
        description: professional.specialty,
        searchText: `${professional.label} ${professional.specialty}`,
    })),
);

const normalized = (value: string): string =>
    value.trim().toLocaleLowerCase('es-AR');

const relevantSchedules = computed(() => {
    const professional = selectedProfessional.value;
    const location = selectedLocation.value;

    if (!professional) {
        return [];
    }

    if (!location) {
        return professional.schedules;
    }

    return professional.schedules.filter((schedule) =>
        schedule.location_id !== null && schedule.location_id !== undefined
            ? schedule.location_id === location.value
            : normalized(schedule.location_name) === normalized(location.label),
    );
});

const weekdayLabel = (weekday: number): string =>
    [
        'Día inválido',
        'Lunes',
        'Martes',
        'Miércoles',
        'Jueves',
        'Viernes',
        'Sábado',
        'Domingo',
    ][weekday] ?? `Día ${weekday}`;

const formatTime = (value: string): string => value.slice(0, 5);

const minutesFromTime = (value: string): number => {
    const [hours = 0, minutes = 0] = value
        .slice(0, 5)
        .split(':')
        .map(Number);

    return hours * 60 + minutes;
};

const timeFromMinutes = (value: number): string => {
    const hours = Math.floor(value / 60)
        .toString()
        .padStart(2, '0');
    const minutes = (value % 60).toString().padStart(2, '0');

    return `${hours}:${minutes}`;
};

const selectedWeekday = computed(() => {
    if (!appointmentDate.value) {
        return null;
    }

    const weekday = new Date(`${appointmentDate.value}T12:00:00`).getDay();

    return weekday === 0 ? 7 : weekday;
});

const timeSlots = computed(() => {
    const professional = selectedProfessional.value;

    if (
        !professional
        || !selectedLocation.value
        || selectedWeekday.value === null
    ) {
        return [];
    }

    const duration = professional.duration_minutes;

    if (duration < 1) {
        return [];
    }

    const slots = new Map<string, string>();

    relevantSchedules.value
        .filter((schedule) => schedule.weekday === selectedWeekday.value)
        .forEach((schedule) => {
            const scheduleEnd = minutesFromTime(schedule.end_time);

            for (
                let slotStart = minutesFromTime(schedule.start_time);
                slotStart + duration <= scheduleEnd;
                slotStart += duration
            ) {
                const value = timeFromMinutes(slotStart);
                const end = timeFromMinutes(slotStart + duration);
                const startsAt = new Date(`${appointmentDate.value}T${value}:00`);

                if (!isEditing.value && startsAt <= new Date()) {
                    continue;
                }

                slots.set(value, `${value}–${end}`);
            }
        });

    if (
        isEditing.value
        && appointmentDate.value === initialStart.slice(0, 10)
        && appointmentTime.value
        && !slots.has(appointmentTime.value)
    ) {
        const end = props.appointment?.ends_at.slice(11, 16)
            ?? timeFromMinutes(
                minutesFromTime(appointmentTime.value) + duration,
            );
        slots.set(
            appointmentTime.value,
            `${appointmentTime.value}–${end}`,
        );
    }

    return [...slots.entries()]
        .sort(([first], [second]) => first.localeCompare(second))
        .map(([value, label]) => ({ value, label }));
});

const timePlaceholder = computed(() => {
    if (!selectedProfessional.value) {
        return 'Seleccioná un profesional';
    }

    if (!selectedLocation.value) {
        return 'Seleccioná una sede';
    }

    if (timeSlots.value.length === 0) {
        return 'No hay horarios disponibles';
    }

    return 'Seleccionar horario';
});

watch(appointmentDate, () => {
    appointmentTime.value = '';
});

watch(
    [() => form.professional_id, () => form.location_id],
    () => {
        appointmentTime.value = '';
    },
);

watch(appointmentTime, (time) => {
    form.starts_at = appointmentDate.value && time
        ? `${appointmentDate.value}T${time}`
        : '';
    form.clearErrors('starts_at');
});

const submit = (): void => {
    if (isEditing.value) {
        form.put(props.submitUrl, {
            preserveScroll: true,
        });

        return;
    }

    form.post(props.submitUrl, {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="submit">
        <div class="space-y-6">
            <section
                aria-labelledby="appointment-people"
                class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-clinic-50 p-2.5 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300">
                            <BaseIcon name="users" class="size-5" />
                        </span>
                        <div>
                            <h2 id="appointment-people" class="font-semibold text-slate-950 dark:text-white">
                                Paciente y profesional
                            </h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Seleccioná quién será atendido y el profesional responsable.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                    <div>
                        <label for="appointment-patient" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Paciente <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <SearchableCombobox
                            v-model="form.patient_id"
                            input-id="appointment-patient"
                            :options="patientComboboxOptions"
                            placeholder="Seleccionar paciente"
                            search-placeholder="Buscar por apellido o DNI..."
                            empty-message="No se encontraron pacientes."
                            aria-describedby="appointment-patient-error"
                            :invalid="Boolean(form.errors.patient_id)"
                            required
                        />
                        <FieldError id="appointment-patient-error" :message="form.errors.patient_id" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="appointment-professional" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Profesional <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <SearchableCombobox
                            v-model="form.professional_id"
                            input-id="appointment-professional"
                            :options="professionalComboboxOptions"
                            placeholder="Seleccionar profesional"
                            search-placeholder="Buscar por apellido o especialidad..."
                            empty-message="No se encontraron profesionales."
                            aria-describedby="appointment-professional-error"
                            :invalid="Boolean(form.errors.professional_id)"
                            required
                        />
                        <FieldError id="appointment-professional-error" :message="form.errors.professional_id" class="mt-1.5" />
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="appointment-schedule"
                class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <BaseIcon name="calendar-days" class="size-5" />
                        </span>
                        <div>
                            <h2 id="appointment-schedule" class="font-semibold text-slate-950 dark:text-white">
                                Sede y horario
                            </h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                La duración se toma de la configuración del profesional.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                    <div>
                        <label for="appointment-location" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Sede <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="appointment-location"
                            v-model="form.location_id"
                            required
                            :aria-invalid="Boolean(form.errors.location_id)"
                            aria-describedby="appointment-location-error"
                            class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        >
                            <option value="" disabled>Seleccionar sede</option>
                            <option
                                v-for="location in locationOptions"
                                :key="location.value"
                                :value="location.value"
                            >
                                {{ location.label }} · {{ location.code }}
                            </option>
                        </select>
                        <FieldError id="appointment-location-error" :message="form.errors.location_id" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="appointment-date" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Fecha <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="appointment-date"
                            v-model="appointmentDate"
                            type="date"
                            :min="minimumDate"
                            required
                            :aria-invalid="Boolean(form.errors.starts_at)"
                            aria-describedby="appointment-start-error"
                            class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                    </div>

                    <div>
                        <label for="appointment-time" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Horario <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="appointment-time"
                            v-model="appointmentTime"
                            required
                            :disabled="timeSlots.length === 0"
                            :aria-invalid="Boolean(form.errors.starts_at)"
                            aria-describedby="appointment-time-help appointment-start-error"
                            class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:disabled:bg-slate-900 dark:disabled:text-slate-500"
                        >
                            <option value="" disabled>{{ timePlaceholder }}</option>
                            <option
                                v-for="slot in timeSlots"
                                :key="slot.value"
                                :value="slot.value"
                            >
                                {{ slot.label }}
                            </option>
                        </select>
                        <p id="appointment-time-help" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            Los intervalos se calculan según la agenda y duración del profesional.
                        </p>
                        <FieldError id="appointment-start-error" :message="form.errors.starts_at" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="appointment-type" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Tipo de atención <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="appointment-type"
                            v-model="form.type"
                            required
                            :aria-invalid="Boolean(form.errors.type)"
                            aria-describedby="appointment-type-error"
                            class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        >
                            <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <FieldError id="appointment-type-error" :message="form.errors.type" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="appointment-office" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Consultorio
                        </label>
                        <input
                            id="appointment-office"
                            v-model="form.office"
                            type="text"
                            maxlength="100"
                            :aria-invalid="Boolean(form.errors.office)"
                            aria-describedby="appointment-office-error"
                            placeholder="Ej.: Consultorio 204"
                            class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <FieldError id="appointment-office-error" :message="form.errors.office" class="mt-1.5" />
                    </div>

                    <div class="lg:col-span-2">
                        <label for="appointment-reason" class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            Motivo administrativo
                        </label>
                        <textarea
                            id="appointment-reason"
                            v-model="form.reason"
                            rows="3"
                            maxlength="1000"
                            :aria-invalid="Boolean(form.errors.reason)"
                            aria-describedby="appointment-reason-error appointment-reason-help"
                            placeholder="Ej.: Control anual"
                            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <p id="appointment-reason-help" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            No ingreses información clínica sensible en este campo.
                        </p>
                        <FieldError id="appointment-reason-error" :message="form.errors.reason" class="mt-1.5" />
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
            <section class="rounded-2xl border border-clinic-100 bg-clinic-50/70 p-5 dark:border-clinic-900/60 dark:bg-clinic-950/25">
                <div class="flex items-center gap-2 text-clinic-800 dark:text-clinic-200">
                    <BaseIcon name="clock" class="size-5" />
                    <h2 class="text-sm font-semibold">Disponibilidad configurada</h2>
                </div>

                <template v-if="selectedProfessional">
                    <p class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ selectedProfessional.label }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        {{ selectedProfessional.specialty }} · {{ selectedProfessional.duration_minutes }} minutos por turno
                    </p>

                    <ul v-if="relevantSchedules.length" class="mt-4 space-y-2">
                        <li
                            v-for="(schedule, index) in relevantSchedules"
                            :key="`${schedule.weekday}-${schedule.start_time}-${index}`"
                            class="rounded-xl border border-white/80 bg-white/80 px-3 py-2.5 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-200"
                        >
                            <span class="font-semibold">{{ weekdayLabel(schedule.weekday) }}</span>
                            · {{ formatTime(schedule.start_time) }}–{{ formatTime(schedule.end_time) }}
                            <span v-if="!selectedLocation" class="mt-0.5 block text-slate-500 dark:text-slate-400">
                                {{ schedule.location_name }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        El profesional no tiene una franja configurada para la sede elegida.
                    </p>
                </template>
                <p v-else class="mt-4 text-xs leading-5 text-slate-600 dark:text-slate-300">
                    Seleccioná un profesional para consultar su duración y sus horarios.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start gap-3">
                    <BaseIcon name="shield-check" class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Controles automáticos</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Al guardar se valida agenda, sede y superposición del profesional, paciente y consultorio.
                        </p>
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row xl:flex-col-reverse">
                <Link
                    :href="indexUrl"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <BaseIcon name="arrow-left" class="size-4" />
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <BaseIcon :name="form.processing ? 'loader' : 'save'" class="size-4" :class="{ 'animate-spin': form.processing }" />
                    {{ form.processing ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear turno' }}
                </button>
            </div>
        </aside>
    </form>
</template>
