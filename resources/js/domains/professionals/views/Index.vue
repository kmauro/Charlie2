<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type {
    PaginatedProfessionals,
    Professional,
    ProfessionalFilters,
    ProfessionalSummaries,
} from '@/domains/professionals/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';

const props = defineProps<{
    professionals: PaginatedProfessionals;
    filters: ProfessionalFilters;
    summaries: ProfessionalSummaries;
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const changingProfessionalId = ref<number | null>(null);

const summary = computed(() => ({
    total: props.summaries.total,
    active: props.summaries.active,
    inactive: props.summaries.inactive,
    onLeave: props.summaries.on_leave,
    invitationPending:
        props.summaries.invitation_pending ??
        props.professionals.data.filter(
            (professional) => professional.invitation_pending,
        ).length,
}));

const hasFilters = computed(
    () => filterForm.search.trim() !== '' || filterForm.status !== '',
);

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toLocaleUpperCase('es');

const statusClasses = (value: string): string =>
    value === 'active'
        ? 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300'
        : value === 'on_leave'
          ? 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300'
        : 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300';

const officeAssignmentLabel = (professional: Professional): string => {
    if (typeof professional.office_assignment_type !== 'string') {
        return professional.office_assignment_type.label;
    }

    return professional.office_assignment_type === 'fixed'
        ? 'Consultorio fijo'
        : 'Según disponibilidad';
};

const formatTime = (time: string): string => time.slice(0, 5);

const applyFilters = (): void => {
    router.get(
        '/profesionales',
        {
            search: filterForm.search.trim() || undefined,
            status: filterForm.status || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = (): void => {
    filterForm.search = '';
    filterForm.status = '';

    router.get(
        '/profesionales',
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deactivateProfessional = (professional: Professional): void => {
    if (
        !window.confirm(
            `¿Querés inactivar a ${professional.name}? No se eliminará su información.`,
        )
    ) {
        return;
    }

    changingProfessionalId.value = professional.id;
    router.delete(`/profesionales/${professional.id}`, {
        preserveScroll: true,
        onFinish: () => {
            changingProfessionalId.value = null;
        },
    });
};

const restoreProfessional = (professional: Professional): void => {
    changingProfessionalId.value = professional.id;
    router.patch(
        `/profesionales/${professional.id}/restaurar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                changingProfessionalId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Profesionales" />

    <AppShell
        title="Profesionales"
        eyebrow="Equipo asistencial"
        description="Administrá profesionales, matrículas, consultorios y horarios de atención."
        action-href="/profesionales/nuevo"
        action-label="Nuevo profesional"
        action-icon="user-plus"
    >
        <section
            aria-label="Resumen de profesionales"
            class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
        >
            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Profesionales
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ summary.total }}
                        </p>
                    </div>
                    <span
                        class="rounded-xl bg-clinic-50 p-2.5 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300"
                    >
                        <BaseIcon name="users" class="size-5" />
                    </span>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            En licencia
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ summary.onLeave }}
                        </p>
                    </div>
                    <span
                        class="rounded-xl bg-amber-50 p-2.5 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                    >
                        <BaseIcon name="clock" class="size-5" />
                    </span>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Activos
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ summary.active }}
                        </p>
                    </div>
                    <span
                        class="rounded-xl bg-emerald-50 p-2.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <BaseIcon name="user-check" class="size-5" />
                    </span>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Inactivos
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ summary.inactive }}
                        </p>
                    </div>
                    <span
                        class="rounded-xl bg-slate-100 p-2.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <BaseIcon name="archive" class="size-5" />
                    </span>
                </div>
            </article>

            <article
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Invitaciones pendientes
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ summary.invitationPending }}
                        </p>
                    </div>
                    <span
                        class="rounded-xl bg-amber-50 p-2.5 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                    >
                        <BaseIcon name="mail" class="size-5" />
                    </span>
                </div>
            </article>
        </section>

        <section
            aria-labelledby="professionals-list-title"
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <h2
                            id="professionals-list-title"
                            class="text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Directorio profesional
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Buscá por nombre, correo, DNI, especialidad o matrícula.
                        </p>
                    </div>

                    <form
                        class="grid gap-3 sm:grid-cols-[minmax(15rem,1fr)_11rem_auto] lg:min-w-3xl"
                        role="search"
                        @submit.prevent="applyFilters"
                    >
                        <div class="relative">
                            <label for="professional-search" class="sr-only">
                                Buscar profesional
                            </label>
                            <BaseIcon
                                name="search"
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                id="professional-search"
                                v-model="filterForm.search"
                                type="search"
                                autocomplete="off"
                                placeholder="Nombre, DNI o especialidad"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white py-2.5 pr-3 pl-10 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
                            />
                        </div>

                        <div>
                            <label for="professional-status" class="sr-only">
                                Filtrar por estado
                            </label>
                            <select
                                id="professional-status"
                                v-model="filterForm.status"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            >
                                <option value="">Todos los estados</option>
                                <option value="active">Activos</option>
                                <option value="on_leave">En licencia</option>
                                <option value="inactive">Inactivos</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white transition hover:bg-clinic-700"
                        >
                            <BaseIcon name="filter" class="size-4" />
                            Filtrar
                        </button>
                    </form>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
                >
                    <p class="text-slate-500 dark:text-slate-400" aria-live="polite">
                        {{ professionals.total }}
                        {{
                            professionals.total === 1
                                ? 'profesional encontrado'
                                : 'profesionales encontrados'
                        }}
                    </p>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 font-semibold text-clinic-700 hover:bg-clinic-50 dark:text-clinic-300 dark:hover:bg-clinic-950/40"
                        @click="clearFilters"
                    >
                        <BaseIcon name="rotate-ccw" class="size-4" />
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <div
                v-if="professionals.data.length"
                class="grid gap-4 p-4 xl:grid-cols-2 sm:p-6"
            >
                <article
                    v-for="professional in professionals.data"
                    :key="professional.id"
                    class="flex flex-col rounded-2xl border border-slate-200 p-5 transition-colors hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700"
                >
                    <header class="flex items-start gap-4">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-clinic-100 text-sm font-bold text-clinic-800 dark:bg-clinic-950 dark:text-clinic-200"
                            aria-hidden="true"
                        >
                            {{ initials(professional.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3 class="font-semibold text-slate-950 dark:text-white">
                                    {{ professional.name }}
                                </h3>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="
                                        statusClasses(professional.status.value)
                                    "
                                >
                                    {{ professional.status.label }}
                                </span>
                            </div>
                            <p
                                class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ professional.email }}
                            </p>
                            <span
                                v-if="professional.invitation_pending"
                                class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                            >
                                <BaseIcon name="mail" class="size-3.5" />
                                Invitación pendiente
                            </span>
                        </div>
                    </header>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70">
                            <dt
                                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                <BaseIcon name="stethoscope" class="size-4" />
                                Especialidad
                            </dt>
                            <dd
                                class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ professional.specialty }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70">
                            <dt
                                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                <BaseIcon name="badge-check" class="size-4" />
                                Matrícula
                            </dt>
                            <dd
                                class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ professional.registration }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70">
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                DNI
                            </dt>
                            <dd
                                class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ professional.document_number }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70">
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Teléfono
                            </dt>
                            <dd
                                class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ professional.phone || 'No informado' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                            >
                                <BaseIcon
                                    name="calendar-days"
                                    class="size-4 text-slate-500"
                                />
                                Horarios de atención
                            </p>
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                Turnos de
                                {{ professional.appointment_duration_minutes }} min
                            </span>
                        </div>

                        <ul
                            v-if="professional.schedules.length"
                            class="mt-2 grid gap-2 sm:grid-cols-2"
                        >
                            <li
                                v-for="schedule in professional.schedules"
                                :key="
                                    schedule.id ??
                                    `${schedule.location_name}-${schedule.weekday}-${schedule.start_time}`
                                "
                                class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-800"
                            >
                                <span
                                    class="block font-medium text-slate-800 dark:text-slate-200"
                                >
                                    {{ schedule.location_name }}
                                </span>
                                <span
                                    class="mt-0.5 block text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        schedule.weekday_label ??
                                        `Día ${schedule.weekday}`
                                    }}
                                    · {{ formatTime(schedule.start_time) }}–{{
                                        formatTime(schedule.end_time)
                                    }}
                                </span>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="mt-2 rounded-xl border border-dashed border-slate-300 px-3 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                        >
                            Sin horarios configurados.
                        </p>
                    </div>

                    <footer
                        class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                    >
                        <span
                            class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                        >
                            <BaseIcon name="building-2" class="size-4" />
                            {{ officeAssignmentLabel(professional) }}
                            <span v-if="professional.fixed_office">
                                · {{ professional.fixed_office }}
                            </span>
                        </span>

                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="`/profesionales/${professional.id}/editar`"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <BaseIcon name="edit" class="size-4" />
                                Editar
                            </Link>
                            <button
                                v-if="professional.status.value === 'active'"
                                type="button"
                                :disabled="
                                    changingProfessionalId === professional.id
                                "
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                @click="deactivateProfessional(professional)"
                            >
                                <BaseIcon name="archive" class="size-4" />
                                Inactivar
                            </button>
                            <button
                                v-else
                                type="button"
                                :disabled="
                                    changingProfessionalId === professional.id
                                "
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                @click="restoreProfessional(professional)"
                            >
                                <BaseIcon name="rotate-ccw" class="size-4" />
                                Reactivar
                            </button>
                        </div>
                    </footer>
                </article>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <span
                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                >
                    <BaseIcon
                        :name="hasFilters ? 'search' : 'stethoscope'"
                        class="size-5"
                    />
                </span>
                <h3 class="mt-4 font-semibold text-slate-950 dark:text-white">
                    {{
                        hasFilters
                            ? 'No hay coincidencias'
                            : 'Todavía no hay profesionales'
                    }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        hasFilters
                            ? 'Probá con otros criterios de búsqueda.'
                            : 'Registrá al primer profesional para comenzar.'
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="clearFilters"
                >
                    Limpiar filtros
                </button>
                <Link
                    v-else
                    href="/profesionales/nuevo"
                    class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700"
                >
                    <BaseIcon name="user-plus" class="size-4" />
                    Nuevo profesional
                </Link>
            </div>

            <PaginationNav
                v-if="professionals.links.length"
                :links="professionals.links"
                :from="professionals.from"
                :to="professionals.to"
                :total="professionals.total"
            />
        </section>
    </AppShell>
</template>
