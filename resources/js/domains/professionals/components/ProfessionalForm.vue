<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import type {
    Professional,
    ProfessionalFormData,
    ProfessionalLocationOption,
    ProfessionalSchedule,
    SelectOption,
} from '@/domains/professionals/types';

const props = withDefaults(
    defineProps<{
        professional?: Professional;
        statuses?: SelectOption[];
        officeAssignmentTypes?: SelectOption[];
        locationOptions?: ProfessionalLocationOption[];
    }>(),
    {
        professional: undefined,
        statuses: () => [
            { value: 'active', label: 'Activo' },
            { value: 'inactive', label: 'Inactivo' },
        ],
        officeAssignmentTypes: () => [
            { value: 'availability', label: 'Según disponibilidad' },
            { value: 'fixed', label: 'Consultorio fijo' },
        ],
        locationOptions: () => [],
    },
);

const weekdays: SelectOption[] = [
    { value: '1', label: 'Lunes' },
    { value: '2', label: 'Martes' },
    { value: '3', label: 'Miércoles' },
    { value: '4', label: 'Jueves' },
    { value: '5', label: 'Viernes' },
    { value: '6', label: 'Sábado' },
    { value: '7', label: 'Domingo' },
];

const registrationTypes = ['M.N.', 'M.P.'];

const optionValue = (option: string | SelectOption | undefined): string =>
    typeof option === 'string' ? option : (option?.value ?? '');

const normalizeTime = (time: string | undefined, fallback: string): string =>
    time ? time.slice(0, 5) : fallback;

const normalizedLocationName = (name: string): string =>
    name.trim().toLocaleLowerCase('es-AR');

const locationIdFromLegacyName = (name: string): number | '' =>
    props.locationOptions.find(
        (location) =>
            normalizedLocationName(location.label) ===
            normalizedLocationName(name),
    )?.value ?? '';

const splitRegistration = (
    professional: Professional | undefined,
): { type: string; number: string } => {
    if (professional?.registration_type || professional?.registration_number) {
        return {
            type: professional.registration_type ?? 'M.N.',
            number: professional.registration_number ?? '',
        };
    }

    const match = professional?.registration?.match(/^(.+?)\s+([A-Za-z0-9./-]+)$/);

    return {
        type: match?.[1] ?? 'M.N.',
        number: match?.[2] ?? '',
    };
};

const registration = splitRegistration(props.professional);

const newSchedule = (): ProfessionalSchedule => ({
    location_id: props.locationOptions[0]?.value ?? '',
    location_name: '',
    weekday: 1,
    start_time: '08:00',
    end_time: '12:00',
});

const initialSchedules = (): ProfessionalSchedule[] => {
    if (!props.professional?.schedules.length) {
        return [newSchedule()];
    }

    return props.professional.schedules.map((schedule) => ({
        id: schedule.id,
        location_id:
            schedule.location_id ??
            locationIdFromLegacyName(schedule.location_name),
        location_name: schedule.location_name,
        weekday: Number(schedule.weekday),
        start_time: normalizeTime(schedule.start_time, '08:00'),
        end_time: normalizeTime(schedule.end_time, '12:00'),
    }));
};

const form = useForm<ProfessionalFormData>({
    name: props.professional?.name ?? '',
    email: props.professional?.email ?? '',
    document_number: props.professional?.document_number ?? '',
    phone: props.professional?.phone ?? '',
    specialty: props.professional?.specialty ?? '',
    registration_type: registration.type,
    registration_number: registration.number,
    appointment_duration_minutes:
        props.professional?.appointment_duration_minutes ?? 30,
    status: props.professional?.status.value,
    office_assignment_type: optionValue(
        props.professional?.office_assignment_type,
    ) || 'availability',
    fixed_office: props.professional?.fixed_office ?? '',
    schedules: initialSchedules(),
});

const isEditing = computed(() => props.professional !== undefined);
const submitLabel = computed(() =>
    isEditing.value ? 'Guardar cambios' : 'Crear profesional',
);

const fieldError = (field: string): string | undefined =>
    (form.errors as Record<string, string>)[field];

const addSchedule = (): void => {
    const lastSchedule = form.schedules.at(-1);

    form.schedules.push({
        location_id:
            lastSchedule?.location_id ?? props.locationOptions[0]?.value ?? '',
        location_name: '',
        weekday: lastSchedule ? Math.min(lastSchedule.weekday + 1, 7) : 1,
        start_time: lastSchedule?.start_time ?? '08:00',
        end_time: lastSchedule?.end_time ?? '12:00',
    });
};

const removeSchedule = (index: number): void => {
    if (form.schedules.length === 1) {
        return;
    }

    form.schedules.splice(index, 1);
};

const submit = (): void => {
    form.transform((data) => ({
        ...data,
        fixed_office:
            data.office_assignment_type === 'fixed' ? data.fixed_office : '',
        schedules: data.schedules.map(
            ({ location_id, weekday, start_time, end_time }) => ({
                location_id,
                weekday,
                start_time,
                end_time,
            }),
        ),
    }));

    if (isEditing.value && props.professional) {
        form.put(`/profesionales/${props.professional.id}`, {
            preserveScroll: true,
        });

        return;
    }

    form.post('/profesionales', {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="mx-auto flex max-w-6xl flex-col gap-6" @submit.prevent="submit">
        <div
            v-if="form.hasErrors"
            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
            role="alert"
        >
            Revisá los campos marcados antes de continuar.
        </div>

        <section
            aria-labelledby="professional-personal-data"
            class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="rounded-xl bg-clinic-50 p-2.5 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300"
                    >
                        <BaseIcon name="user-round" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="professional-personal-data"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Datos personales
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Identificación y medios de contacto del profesional.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div class="sm:col-span-2">
                    <label
                        for="professional-name"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Nombre completo
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        maxlength="255"
                        required
                        :aria-invalid="Boolean(form.errors.name)"
                        aria-describedby="professional-name-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="Ej.: Dra. Valentina Ríos"
                    />
                    <FieldError
                        id="professional-name-error"
                        :message="form.errors.name"
                    />
                </div>

                <div>
                    <label
                        for="professional-document"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        DNI
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-document"
                        v-model="form.document_number"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        minlength="7"
                        maxlength="9"
                        pattern="[0-9]{7,9}"
                        required
                        :aria-invalid="Boolean(form.errors.document_number)"
                        aria-describedby="professional-document-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="Sin puntos"
                    />
                    <FieldError
                        id="professional-document-error"
                        :message="form.errors.document_number"
                    />
                </div>

                <div>
                    <label
                        for="professional-phone"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Teléfono
                    </label>
                    <input
                        id="professional-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        maxlength="32"
                        :aria-invalid="Boolean(form.errors.phone)"
                        aria-describedby="professional-phone-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="+54 11 5555 5555"
                    />
                    <FieldError
                        id="professional-phone-error"
                        :message="form.errors.phone"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label
                        for="professional-email"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Correo electrónico
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        required
                        :aria-invalid="Boolean(form.errors.email)"
                        aria-describedby="professional-email-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="profesional@clinica.com"
                    />
                    <FieldError
                        id="professional-email-error"
                        :message="form.errors.email"
                    />
                    <p
                        v-if="!isEditing"
                        class="mt-2 flex items-start gap-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        <BaseIcon name="mail" class="mt-0.5 size-3.5 shrink-0" />
                        Al crear el profesional se enviará una invitación a esta
                        dirección para activar su acceso.
                    </p>
                </div>
            </div>
        </section>

        <section
            aria-labelledby="professional-clinical-data"
            class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="rounded-xl bg-violet-50 p-2.5 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300"
                    >
                        <BaseIcon name="stethoscope" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="professional-clinical-data"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Información profesional
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Especialidad, matrícula y duración habitual de sus
                            turnos.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                <div class="sm:col-span-2">
                    <label
                        for="professional-specialty"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Especialidad
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-specialty"
                        v-model="form.specialty"
                        type="text"
                        maxlength="120"
                        required
                        :aria-invalid="Boolean(form.errors.specialty)"
                        aria-describedby="professional-specialty-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="Ej.: Cardiología"
                    />
                    <FieldError
                        id="professional-specialty-error"
                        :message="form.errors.specialty"
                    />
                </div>

                <div>
                    <label
                        for="professional-registration-type"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Tipo de matrícula
                    </label>
                    <select
                        id="professional-registration-type"
                        v-model="form.registration_type"
                        :aria-invalid="Boolean(form.errors.registration_type)"
                        aria-describedby="professional-registration-type-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        <option
                            v-for="type in registrationTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ type }}
                        </option>
                    </select>
                    <FieldError
                        id="professional-registration-type-error"
                        :message="form.errors.registration_type"
                    />
                </div>

                <div>
                    <label
                        for="professional-registration-number"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Número de matrícula
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-registration-number"
                        v-model="form.registration_number"
                        type="text"
                        maxlength="50"
                        required
                        :aria-invalid="Boolean(form.errors.registration_number)"
                        aria-describedby="professional-registration-number-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="98214"
                    />
                    <FieldError
                        id="professional-registration-number-error"
                        :message="form.errors.registration_number"
                    />
                </div>

                <div>
                    <label
                        for="professional-duration"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Duración del turno
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <div class="relative mt-2">
                        <input
                            id="professional-duration"
                            v-model.number="form.appointment_duration_minutes"
                            type="number"
                            min="10"
                            max="180"
                            step="5"
                            required
                            :aria-invalid="
                                Boolean(
                                    form.errors.appointment_duration_minutes,
                                )
                            "
                            aria-describedby="professional-duration-error"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 pr-16 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500 dark:text-slate-400"
                        >
                            minutos
                        </span>
                    </div>
                    <FieldError
                        id="professional-duration-error"
                        :message="form.errors.appointment_duration_minutes"
                    />
                </div>

                <div v-if="isEditing">
                    <label
                        for="professional-status"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Estado
                    </label>
                    <select
                        id="professional-status"
                        v-model="form.status"
                        :aria-invalid="Boolean(form.errors.status)"
                        aria-describedby="professional-status-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <FieldError
                        id="professional-status-error"
                        :message="form.errors.status"
                    />
                </div>
            </div>
        </section>

        <section
            aria-labelledby="professional-office-assignment"
            class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="rounded-xl bg-amber-50 p-2.5 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                    >
                        <BaseIcon name="building-2" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="professional-office-assignment"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Asignación de consultorio
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Definí si se asignará según disponibilidad o si tendrá
                            un consultorio fijo.
                        </p>
                    </div>
                </div>
            </div>

            <fieldset class="grid gap-3 p-5 sm:grid-cols-2 sm:p-6">
                <legend class="sr-only">Modalidad de consultorio</legend>
                <label
                    v-for="assignmentType in officeAssignmentTypes"
                    :key="assignmentType.value"
                    class="flex cursor-pointer gap-3 rounded-2xl border p-4 transition-colors"
                    :class="
                        form.office_assignment_type === assignmentType.value
                            ? 'border-clinic-500 bg-clinic-50/70 ring-1 ring-clinic-500 dark:border-clinic-500 dark:bg-clinic-950/30'
                            : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'
                    "
                >
                    <input
                        v-model="form.office_assignment_type"
                        type="radio"
                        name="office-assignment-type"
                        :value="assignmentType.value"
                        class="mt-0.5 size-4 border-slate-300 text-clinic-600 focus:ring-clinic-600 dark:border-slate-600 dark:bg-slate-950"
                    />
                    <span>
                        <span
                            class="block text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            {{ assignmentType.label }}
                        </span>
                        <span
                            class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                assignmentType.value === 'fixed'
                                    ? 'Se reservará el mismo consultorio para su atención.'
                                    : 'La sede podrá asignar un consultorio disponible cada día.'
                            }}
                        </span>
                    </span>
                </label>
                <FieldError
                    class="sm:col-span-2"
                    :message="form.errors.office_assignment_type"
                />

                <div
                    v-if="form.office_assignment_type === 'fixed'"
                    class="sm:col-span-2"
                >
                    <label
                        for="professional-fixed-office"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Consultorio fijo
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="professional-fixed-office"
                        v-model="form.fixed_office"
                        type="text"
                        maxlength="100"
                        required
                        :aria-invalid="Boolean(form.errors.fixed_office)"
                        aria-describedby="professional-fixed-office-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="Ej.: Consultorio 204"
                    />
                    <FieldError
                        id="professional-fixed-office-error"
                        :message="form.errors.fixed_office"
                    />
                </div>
            </fieldset>
        </section>

        <section
            aria-labelledby="professional-schedules"
            class="rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 sm:p-6"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <BaseIcon name="calendar-days" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="professional-schedules"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Sedes y horarios
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Agregá una franja independiente por sede y día.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    :disabled="locationOptions.length === 0"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-clinic-200 bg-clinic-50 px-4 text-sm font-semibold text-clinic-700 transition hover:bg-clinic-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-clinic-800 dark:bg-clinic-950/40 dark:text-clinic-200 dark:hover:bg-clinic-900/50"
                    @click="addSchedule"
                >
                    <BaseIcon name="plus" class="size-4" />
                    Agregar horario
                </button>
            </div>

            <div class="flex flex-col gap-4 p-5 sm:p-6">
                <div
                    v-if="locationOptions.length === 0"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    Primero tenés que crear una sede activa para configurar la
                    agenda profesional.
                    <Link
                        href="/organizacion/sedes/nueva"
                        class="font-semibold underline underline-offset-2"
                    >
                        Crear sede
                    </Link>
                </div>
                <article
                    v-for="(schedule, index) in form.schedules"
                    :key="schedule.id ?? `new-${index}`"
                    class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-950/50"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            Horario {{ index + 1 }}
                        </h3>
                        <button
                            type="button"
                            :disabled="form.schedules.length === 1"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-300 dark:hover:bg-rose-950/30"
                            :aria-label="`Quitar horario ${index + 1}`"
                            @click="removeSchedule(index)"
                        >
                            <BaseIcon name="archive" class="size-4" />
                            Quitar
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="md:col-span-2 xl:col-span-1">
                            <label
                                :for="`schedule-location-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Sede
                                <span class="text-rose-600" aria-hidden="true"
                                    >*</span
                                >
                            </label>
                            <select
                                :id="`schedule-location-${index}`"
                                v-model="schedule.location_id"
                                required
                                :aria-invalid="
                                    Boolean(
                                        fieldError(
                                            `schedules.${index}.location_id`,
                                        ),
                                    )
                                "
                                :aria-describedby="`schedule-location-${index}-error`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="" disabled>
                                    Seleccionar sede
                                </option>
                                <option
                                    v-for="location in locationOptions"
                                    :key="location.value"
                                    :value="location.value"
                                >
                                    {{ location.label }} · {{ location.code }}
                                </option>
                            </select>
                            <FieldError
                                :id="`schedule-location-${index}-error`"
                                :message="
                                    fieldError(
                                        `schedules.${index}.location_id`,
                                    )
                                "
                            />
                        </div>

                        <div>
                            <label
                                :for="`schedule-weekday-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Día
                            </label>
                            <select
                                :id="`schedule-weekday-${index}`"
                                v-model.number="schedule.weekday"
                                :aria-invalid="
                                    Boolean(
                                        fieldError(
                                            `schedules.${index}.weekday`,
                                        ),
                                    )
                                "
                                :aria-describedby="`schedule-weekday-${index}-error`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option
                                    v-for="weekday in weekdays"
                                    :key="weekday.value"
                                    :value="Number(weekday.value)"
                                >
                                    {{ weekday.label }}
                                </option>
                            </select>
                            <FieldError
                                :id="`schedule-weekday-${index}-error`"
                                :message="
                                    fieldError(`schedules.${index}.weekday`)
                                "
                            />
                        </div>

                        <div>
                            <label
                                :for="`schedule-start-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Desde
                            </label>
                            <input
                                :id="`schedule-start-${index}`"
                                v-model="schedule.start_time"
                                type="time"
                                required
                                :aria-invalid="
                                    Boolean(
                                        fieldError(
                                            `schedules.${index}.start_time`,
                                        ),
                                    )
                                "
                                :aria-describedby="`schedule-start-${index}-error`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                            <FieldError
                                :id="`schedule-start-${index}-error`"
                                :message="
                                    fieldError(`schedules.${index}.start_time`)
                                "
                            />
                        </div>

                        <div>
                            <label
                                :for="`schedule-end-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Hasta
                            </label>
                            <input
                                :id="`schedule-end-${index}`"
                                v-model="schedule.end_time"
                                type="time"
                                required
                                :aria-invalid="
                                    Boolean(
                                        fieldError(
                                            `schedules.${index}.end_time`,
                                        ),
                                    )
                                "
                                :aria-describedby="`schedule-end-${index}-error`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                            <FieldError
                                :id="`schedule-end-${index}-error`"
                                :message="
                                    fieldError(`schedules.${index}.end_time`)
                                "
                            />
                        </div>
                    </div>
                </article>

                <FieldError :message="form.errors.schedules" />
            </div>
        </section>

        <div
            class="sticky bottom-3 z-20 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur sm:flex-row sm:items-center sm:justify-end dark:border-slate-700 dark:bg-slate-900/95"
        >
            <Link
                href="/profesionales"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <BaseIcon name="arrow-left" class="size-4" />
                Cancelar
            </Link>
            <button
                type="submit"
                :disabled="form.processing || locationOptions.length === 0"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <BaseIcon name="save" class="size-4" />
                {{ form.processing ? 'Guardando…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
