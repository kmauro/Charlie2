<script setup lang="ts">
import type {
    AppointmentFilters,
    AppointmentListItem,
    AppointmentLocationOption,
    AppointmentOption,
    AppointmentProfessionalOption,
    AppointmentsPaginator,
    AppointmentSummaries,
} from '@/domains/appointments/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    appointments: AppointmentsPaginator;
    filters: AppointmentFilters;
    summaries: AppointmentSummaries;
    statusOptions: AppointmentOption[];
    typeOptions: AppointmentOption[];
    locationOptions: AppointmentLocationOption[];
    professionalOptions: AppointmentProfessionalOption[];
    permissions: {
        create: boolean;
    };
}>();

const localDateValue = (): string => {
    const today = new Date();
    today.setMinutes(today.getMinutes() - today.getTimezoneOffset());

    return today.toISOString().slice(0, 10);
};

const filterForm = reactive({
    date: props.filters.date ?? localDateValue(),
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    location_id: props.filters.location_id ?? '',
    professional_id: props.filters.professional_id ?? '',
    search: props.filters.search ?? '',
});

const changingAppointmentId = ref<number | null>(null);
const cancellationAppointment = ref<AppointmentListItem | null>(null);
const cancelForm = useForm({
    cancellation_reason: '',
    lock_version: 0,
});

const hasFilters = computed(
    () =>
        filterForm.status !== '' ||
        filterForm.type !== '' ||
        filterForm.location_id !== '' ||
        filterForm.professional_id !== '' ||
        filterForm.search.trim() !== '',
);

const dateLabel = computed(() => {
    if (!filterForm.date) {
        return 'Todos los días';
    }

    return new Intl.DateTimeFormat('es-AR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${filterForm.date}T00:00:00Z`));
});

const summaryCards = computed(() => [
    {
        label: 'En el día',
        value: props.summaries.today,
        icon: 'calendar-days',
        tone: 'clinic',
    },
    {
        label: 'Programados',
        value: props.summaries.scheduled,
        icon: 'clock',
        tone: 'sky',
    },
    {
        label: 'En espera',
        value: props.summaries.waiting,
        icon: 'users',
        tone: 'amber',
    },
    {
        label: 'Atendidos',
        value: props.summaries.completed,
        icon: 'check-circle',
        tone: 'emerald',
    },
    {
        label: 'Cancelados',
        value: props.summaries.cancelled,
        icon: 'archive',
        tone: 'rose',
    },
]);

const toneClasses: Record<string, string> = {
    clinic: 'bg-clinic-50 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300',
    sky: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    emerald:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    rose: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
};

const statusClasses = (status: string): string => {
    const classes: Record<string, string> = {
        reserved:
            'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-300',
        confirmed:
            'bg-clinic-50 text-clinic-800 ring-clinic-600/20 dark:bg-clinic-950/40 dark:text-clinic-300',
        waiting:
            'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300',
        present:
            'bg-violet-50 text-violet-800 ring-violet-600/20 dark:bg-violet-950/40 dark:text-violet-300',
        attended:
            'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300',
        cancelled:
            'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300',
        absent:
            'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300',
    };

    return classes[status] ?? classes.absent;
};

const formatTime = (value: string): string =>
    new Intl.DateTimeFormat('es-AR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('es-AR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));

const filterPayload = (): Record<string, string | number | undefined> => ({
    date: filterForm.date || undefined,
    status: filterForm.status || undefined,
    type: filterForm.type || undefined,
    location_id: filterForm.location_id || undefined,
    professional_id: filterForm.professional_id || undefined,
    search: filterForm.search.trim() || undefined,
});

const applyFilters = (): void => {
    router.get('/turnos', filterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = (): void => {
    filterForm.status = '';
    filterForm.type = '';
    filterForm.location_id = '';
    filterForm.professional_id = '';
    filterForm.search = '';
    applyFilters();
};

const changeDate = (days: number): void => {
    const baseDate = filterForm.date
        ? new Date(`${filterForm.date}T00:00:00`)
        : new Date();
    baseDate.setDate(baseDate.getDate() + days);
    const localDate = new Date(
        baseDate.getTime() - baseDate.getTimezoneOffset() * 60_000,
    );
    filterForm.date = localDate.toISOString().slice(0, 10);
    applyFilters();
};

const goToToday = (): void => {
    filterForm.date = localDateValue();
    applyFilters();
};

const changeStatus = (
    appointment: AppointmentListItem,
    status: string,
): void => {
    if (status === appointment.status.value || !appointment.urls.status) {
        return;
    }

    changingAppointmentId.value = appointment.id;
    router.patch(
        appointment.urls.status,
        {
            status,
            lock_version: appointment.lock_version,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                changingAppointmentId.value = null;
            },
        },
    );
};

const openCancellation = (appointment: AppointmentListItem): void => {
    if (!appointment.urls.cancel) {
        return;
    }

    cancellationAppointment.value = appointment;
    cancelForm.cancellation_reason = '';
    cancelForm.lock_version = appointment.lock_version;
    cancelForm.clearErrors();
};

const closeCancellation = (): void => {
    if (cancelForm.processing) {
        return;
    }

    cancellationAppointment.value = null;
    cancelForm.reset();
};

const submitCancellation = (): void => {
    const appointment = cancellationAppointment.value;

    if (!appointment?.urls.cancel) {
        return;
    }

    cancelForm.patch(appointment.urls.cancel, {
        preserveScroll: true,
        onSuccess: closeCancellation,
    });
};
</script>

<template>
    <Head title="Turnos" />

    <AppShell
        title="Turnos"
        eyebrow="Agenda clínica"
        :description="`Organizá la atención de ${dateLabel}. Los horarios y conflictos se validan al guardar.`"
        :action-href="permissions.create ? '/turnos/crear' : ''"
        :action-label="permissions.create ? 'Nuevo turno' : ''"
        action-icon="calendar"
    >
        <div v-if="permissions.create" class="mb-5 flex justify-end">
            <Link
                href="/lista-espera"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-clinic-200 px-3.5 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-800 dark:text-clinic-300 dark:hover:bg-clinic-950/30"
            >
                <BaseIcon name="clock" class="size-4" />
                Lista de espera
            </Link>
        </div>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumen de turnos">
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="grid size-10 place-items-center rounded-xl" :class="toneClasses[card.tone]">
                        <BaseIcon :name="card.icon" class="size-5" />
                    </span>
                    <strong class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ card.value }}
                    </strong>
                </div>
                <p class="mt-3 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ card.label }}
                </p>
            </article>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800 sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-clinic-300 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-300"
                            aria-label="Día anterior"
                            @click="changeDate(-1)"
                        >
                            <BaseIcon name="arrow-left" class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="min-h-10 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:border-clinic-300 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-200"
                            @click="goToToday"
                        >
                            Hoy
                        </button>
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-clinic-300 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-300"
                            aria-label="Día siguiente"
                            @click="changeDate(1)"
                        >
                            <BaseIcon name="arrow-right" class="size-4" />
                        </button>
                        <input
                            v-model="filterForm.date"
                            type="date"
                            aria-label="Fecha de la agenda"
                            class="min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                            @change="applyFilters"
                        />
                    </div>

                    <p class="text-sm font-semibold capitalize text-slate-800 dark:text-slate-100">
                        {{ dateLabel }}
                    </p>
                </div>

                <form class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
                    <label class="relative md:col-span-2 xl:col-span-2">
                        <span class="sr-only">Buscar paciente, documento o profesional</span>
                        <BaseIcon name="search" class="pointer-events-none absolute top-3.5 left-3.5 size-4 text-slate-400" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            maxlength="255"
                            placeholder="Paciente, DNI o profesional"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pr-3.5 pl-10 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                    </label>

                    <select
                        v-model="filterForm.status"
                        aria-label="Filtrar por estado"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    >
                        <option value="">Todos los estados</option>
                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>

                    <select
                        v-model="filterForm.type"
                        aria-label="Filtrar por tipo"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    >
                        <option value="">Todos los tipos</option>
                        <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>

                    <select
                        v-model="filterForm.location_id"
                        aria-label="Filtrar por sede"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    >
                        <option value="">Todas las sedes</option>
                        <option v-for="location in locationOptions" :key="location.value" :value="location.value">
                            {{ location.label }}
                        </option>
                    </select>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-clinic-600 dark:hover:bg-clinic-700"
                        >
                            <BaseIcon name="filter" class="size-4" />
                            Aplicar
                        </button>
                        <button
                            v-if="hasFilters"
                            type="button"
                            class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-300 text-slate-500 transition hover:text-rose-600 dark:border-slate-700 dark:text-slate-300"
                            aria-label="Limpiar filtros"
                            @click="clearFilters"
                        >
                            <BaseIcon name="close" class="size-4" />
                        </button>
                    </div>

                    <select
                        v-model="filterForm.professional_id"
                        aria-label="Filtrar por profesional"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 md:col-span-2 xl:col-span-6 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                        @change="applyFilters"
                    >
                        <option value="">Todos los profesionales</option>
                        <option
                            v-for="professional in professionalOptions"
                            :key="professional.value"
                            :value="professional.value"
                        >
                            {{ professional.label }} · {{ professional.specialty }}
                        </option>
                    </select>
                </form>
            </div>

            <div v-if="appointments.data.length === 0" class="px-6 py-16 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                    <BaseIcon name="calendar" class="size-7" />
                </span>
                <h2 class="mt-4 text-base font-semibold text-slate-900 dark:text-white">No hay turnos para mostrar</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                    Ajustá los filtros o registrá un turno nuevo para esta agenda.
                </p>
                <Link
                    v-if="permissions.create"
                    href="/turnos/crear"
                    class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700"
                >
                    <BaseIcon name="plus" class="size-4" />
                    Nuevo turno
                </Link>
            </div>

            <div v-else>
                <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-800">
                    <article v-for="appointment in appointments.data" :key="appointment.id" class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-bold text-slate-950 dark:text-white">
                                    {{ formatTime(appointment.starts_at) }}–{{ formatTime(appointment.ends_at) }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ formatDate(appointment.starts_at) }} · {{ appointment.duration_minutes }} min
                                </p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="statusClasses(appointment.status.value)">
                                {{ appointment.status.label }}
                            </span>
                            <span v-if="appointment.is_overbook" class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300">Sobreturno</span>
                        </div>

                        <div class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-950/60">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ appointment.patient.name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">DNI {{ appointment.patient.document_number }}</p>
                            </div>
                            <div>
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ appointment.professional.name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ appointment.professional.specialty }}</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                <BaseIcon name="map-pin" class="size-4 text-clinic-600" />
                                {{ appointment.location.name }}
                                <span v-if="appointment.office">· {{ appointment.office }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <select
                                v-if="appointment.permissions.change_status && appointment.available_statuses.length"
                                :value="appointment.status.value"
                                :disabled="changingAppointmentId === appointment.id"
                                aria-label="Cambiar estado del turno"
                                class="min-h-10 flex-1 rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                @change="changeStatus(appointment, ($event.target as HTMLSelectElement).value)"
                            >
                                <option :value="appointment.status.value">{{ appointment.status.label }}</option>
                                <option
                                    v-for="status in appointment.available_statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                            <Link
                                v-if="appointment.permissions.update"
                                :href="appointment.urls.edit ?? '#!'"
                                class="grid size-10 place-items-center rounded-xl border border-slate-300 text-slate-600 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-300"
                                aria-label="Editar turno"
                            >
                                <BaseIcon name="edit" class="size-4" />
                            </Link>
                            <button
                                v-if="appointment.permissions.cancel"
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                aria-label="Cancelar turno"
                                @click="openCancellation(appointment)"
                            >
                                <BaseIcon name="close" class="size-4" />
                            </button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50/80 dark:bg-slate-950/50">
                            <tr>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase">Horario</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase">Paciente</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase">Profesional</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase">Sede</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase">Estado</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold tracking-wide text-slate-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="appointment in appointments.data" :key="appointment.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <p class="text-sm font-bold text-slate-950 dark:text-white">{{ formatTime(appointment.starts_at) }}–{{ formatTime(appointment.ends_at) }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(appointment.starts_at) }} · {{ appointment.duration_minutes }} min</p>
                                    <span class="mt-2 inline-flex rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ appointment.type.label }}</span>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ appointment.patient.name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">DNI {{ appointment.patient.document_number }}</p>
                                    <p v-if="appointment.reason" class="mt-2 max-w-52 truncate text-xs text-slate-500" :title="appointment.reason">{{ appointment.reason }}</p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ appointment.professional.name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ appointment.professional.specialty }}</p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <p class="text-sm text-slate-800 dark:text-slate-200">{{ appointment.location.name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ appointment.location.code }}<template v-if="appointment.office"> · {{ appointment.office }}</template></p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="statusClasses(appointment.status.value)">
                                        {{ appointment.status.label }}
                                    </span>
                                    <span v-if="appointment.is_overbook" class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300">Sobreturno</span>
                                    <select
                                        v-if="appointment.permissions.change_status && appointment.available_statuses.length"
                                        :value="appointment.status.value"
                                        :disabled="changingAppointmentId === appointment.id"
                                        aria-label="Cambiar estado del turno"
                                        class="mt-2 block min-h-9 max-w-40 rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-700 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        @change="changeStatus(appointment, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option :value="appointment.status.value">Cambiar estado</option>
                                        <option v-for="status in appointment.available_statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                    </select>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex justify-end gap-1.5">
                                        <Link
                                            v-if="appointment.permissions.update"
                                            :href="appointment.urls.edit ?? '#!'"
                                            class="grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-clinic-50 hover:text-clinic-700 dark:text-slate-400 dark:hover:bg-clinic-950/40 dark:hover:text-clinic-300"
                                            aria-label="Editar turno"
                                        >
                                            <BaseIcon name="edit" class="size-4" />
                                        </Link>
                                        <button
                                            v-if="appointment.permissions.cancel"
                                            type="button"
                                            class="grid size-9 place-items-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                            aria-label="Cancelar turno"
                                            @click="openCancellation(appointment)"
                                        >
                                            <BaseIcon name="close" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <PaginationNav
                    :links="appointments.links"
                    :from="appointments.from"
                    :to="appointments.to"
                    :total="appointments.total"
                />
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="cancellationAppointment"
                class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm"
                role="presentation"
                @click.self="closeCancellation"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="cancel-appointment-title"
                    class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:p-6"
                >
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                            <BaseIcon name="circle-alert" class="size-5" />
                        </span>
                        <div>
                            <h2 id="cancel-appointment-title" class="font-semibold text-slate-950 dark:text-white">Cancelar turno</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Indicá el motivo de cancelación del turno de {{ cancellationAppointment.patient.name }}. La operación quedará auditada.
                            </p>
                        </div>
                    </div>

                    <form class="mt-5" @submit.prevent="submitCancellation">
                        <label for="cancellation-reason" class="text-sm font-medium text-slate-800 dark:text-slate-200">Motivo <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea
                            id="cancellation-reason"
                            v-model="cancelForm.cancellation_reason"
                            rows="4"
                            maxlength="1000"
                            required
                            autofocus
                            :aria-invalid="Boolean(cancelForm.errors.cancellation_reason)"
                            aria-describedby="cancellation-reason-error"
                            class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <FieldError id="cancellation-reason-error" :message="cancelForm.errors.cancellation_reason" class="mt-1.5" />

                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                :disabled="cancelForm.processing"
                                class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="closeCancellation"
                            >
                                Volver
                            </button>
                            <button
                                type="submit"
                                :disabled="cancelForm.processing"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
                            >
                                <BaseIcon :name="cancelForm.processing ? 'loader' : 'archive'" class="size-4" :class="{ 'animate-spin': cancelForm.processing }" />
                                {{ cancelForm.processing ? 'Cancelando…' : 'Confirmar cancelación' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </Teleport>
    </AppShell>
</template>
