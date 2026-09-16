<script setup lang="ts">
import type {
    PatientFilters,
    PatientListItem,
    PatientOption,
    PatientPermissions,
    PatientSummaries,
    PatientsPaginator,
} from '@/domains/patients/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    patients: PatientsPaginator;
    filters: PatientFilters;
    summaries: PatientSummaries;
    statusOptions: PatientOption[];
    registeredSexOptions: PatientOption[];
    permissions: PatientPermissions;
}>();

const search = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const activeAction = ref<number | null>(null);

const summaryCards = computed(() => [
    {
        label: 'Pacientes',
        value: props.summaries.total,
        detail: 'fichas registradas',
        icon: 'patients',
        iconClass:
            'bg-clinic-50 text-clinic-700 dark:bg-clinic-400/10 dark:text-clinic-300',
    },
    {
        label: 'Activos',
        value: props.summaries.active,
        detail: 'en seguimiento',
        icon: 'user-check',
        iconClass:
            'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
    },
    {
        label: 'Internados',
        value: props.summaries.inpatient,
        detail: 'en atención',
        icon: 'bed',
        iconClass:
            'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
    },
    {
        label: 'Archivados',
        value: props.summaries.archived,
        detail: 'conservados',
        icon: 'archive',
        iconClass:
            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    },
    {
        label: 'Fallecidos',
        value: props.summaries.deceased,
        detail: 'identificados',
        icon: 'heart-pulse',
        iconClass:
            'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300',
    },
    {
        label: 'Bloqueados',
        value: props.summaries.blocked,
        detail: 'con restricción',
        icon: 'lock-keyhole',
        iconClass:
            'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
    },
]);

const hasFilters = computed(
    () => search.value.trim() !== '' || selectedStatus.value !== '',
);

const isArchived = (patient: PatientListItem): boolean =>
    patient.status.value === 'archived';

const initials = (patient: PatientListItem): string =>
    `${patient.first_name.charAt(0)}${patient.last_name.charAt(0)}`.toLocaleUpperCase(
        'es-AR',
    );

const formatDate = (value: string | null): string => {
    if (!value) {
        return 'No informado';
    }

    const dateOnlyMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const date = dateOnlyMatch
        ? new Date(
              Number(dateOnlyMatch[1]),
              Number(dateOnlyMatch[2]) - 1,
              Number(dateOnlyMatch[3]),
          )
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium',
    }).format(date);
};

const statusClasses = (status: string): string => {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20';
    }

    if (status === 'inpatient') {
        return 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20';
    }

    if (status === 'deceased') {
        return 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-400/20';
    }

    if (status === 'blocked') {
        return 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-500/30';
};

const applyFilters = (): void => {
    router.get(
        '/pacientes',
        {
            search: search.value.trim() || undefined,
            status: selectedStatus.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = (): void => {
    search.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const archivePatient = (patient: PatientListItem): void => {
    if (
        !window.confirm(
            `¿Archivar la ficha de ${patient.display_name}? La información y su trazabilidad se conservarán.`,
        )
    ) {
        return;
    }

    activeAction.value = patient.id;
    router.delete(`/pacientes/${patient.id}`, {
        preserveScroll: true,
        onFinish: () => {
            activeAction.value = null;
        },
    });
};

const restorePatient = (patient: PatientListItem): void => {
    if (
        !window.confirm(
            `¿Restaurar la ficha de ${patient.display_name} a su estado previo?`,
        )
    ) {
        return;
    }

    activeAction.value = patient.id;
    router.patch(
        `/pacientes/${patient.id}/restaurar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Pacientes" />

    <AppShell
        title="Pacientes"
        eyebrow="Gestión de pacientes"
        description="Administrá la ficha demográfica, cobertura, contactos y estado de cada paciente."
        :action-href="permissions.create ? '/pacientes/nuevo' : ''"
        :action-label="permissions.create ? 'Nuevo paciente' : ''"
        action-icon="user-plus"
    >
        <section aria-labelledby="patients-summary-title">
            <h2 id="patients-summary-title" class="sr-only">
                Resumen de pacientes
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <article
                    v-for="summary in summaryCards"
                    :key="summary.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ summary.label }}
                            </p>
                            <p
                                class="pt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ summary.value }}
                            </p>
                            <p
                                class="pt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ summary.detail }}
                            </p>
                        </div>
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            :class="summary.iconClass"
                        >
                            <BaseIcon :name="summary.icon" class="size-5" />
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section
            aria-labelledby="patients-list-title"
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div>
                        <h2
                            id="patients-list-title"
                            class="text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Padrón de pacientes
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ patients.total }}
                            {{
                                patients.total === 1
                                    ? 'ficha encontrada'
                                    : 'fichas encontradas'
                            }}
                        </p>
                    </div>

                    <form
                        class="grid gap-3 sm:grid-cols-[minmax(16rem,1fr)_12rem_auto]"
                        role="search"
                        @submit.prevent="applyFilters"
                    >
                        <div class="relative">
                            <label for="patient-search" class="sr-only">
                                Buscar pacientes
                            </label>
                            <BaseIcon
                                name="search"
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                id="patient-search"
                                v-model="search"
                                type="search"
                                autocomplete="off"
                                placeholder="Nombre, DNI o cobertura"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pr-3 pl-10 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                            />
                        </div>

                        <div>
                            <label for="patient-status-filter" class="sr-only">
                                Filtrar por estado
                            </label>
                            <select
                                id="patient-status-filter"
                                v-model="selectedStatus"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                            >
                                <option value="">Todos los estados</option>
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                <BaseIcon name="filter" class="size-4" />
                                Filtrar
                            </button>
                            <button
                                v-if="hasFilters"
                                type="button"
                                class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                                aria-label="Limpiar filtros"
                                @click="clearFilters"
                            >
                                <BaseIcon name="rotate-ccw" class="size-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <ul
                v-if="patients.data.length"
                class="grid gap-4 p-4 lg:grid-cols-2 2xl:grid-cols-3 sm:p-6"
            >
                <li v-for="patient in patients.data" :key="patient.id">
                    <article
                        class="flex h-full flex-col rounded-2xl border p-5 transition"
                        :class="
                            isArchived(patient)
                                ? 'border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/40'
                                : 'border-slate-200 hover:border-clinic-300 hover:shadow-sm dark:border-slate-800 dark:hover:border-clinic-800'
                        "
                    >
                        <header class="flex items-start gap-4">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-full bg-clinic-100 text-sm font-semibold text-clinic-800 dark:bg-clinic-400/15 dark:text-clinic-200"
                                aria-hidden="true"
                            >
                                {{ initials(patient) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex flex-wrap items-start justify-between gap-2"
                                >
                                    <h3
                                        class="min-w-0 font-semibold text-slate-950 dark:text-white"
                                    >
                                        <Link
                                            :href="`/pacientes/${patient.id}`"
                                            class="rounded-sm hover:text-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:hover:text-clinic-300"
                                        >
                                            {{ patient.display_name }}
                                        </Link>
                                    </h3>
                                    <span
                                        class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="
                                            statusClasses(patient.status.value)
                                        "
                                    >
                                        {{ patient.status.label }}
                                    </span>
                                </div>
                                <p
                                    class="pt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    DNI {{ patient.document_number }} ·
                                    {{ patient.age }} años
                                </p>
                            </div>
                        </header>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70"
                            >
                                <dt
                                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <BaseIcon name="shield-check" class="size-4" />
                                    Cobertura
                                </dt>
                                <dd
                                    class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ patient.coverage_name || 'Particular' }}
                                </dd>
                                <dd
                                    v-if="patient.member_number"
                                    class="pt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Nº {{ patient.member_number }}
                                </dd>
                            </div>
                            <div
                                class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/70"
                            >
                                <dt
                                    class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <BaseIcon name="contact" class="size-4" />
                                    Contactos
                                </dt>
                                <dd
                                    class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ patient.contacts_count }}
                                    {{
                                        patient.contacts_count === 1
                                            ? 'relacionado'
                                            : 'relacionados'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            class="mt-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2"
                        >
                            <p
                                class="flex min-w-0 items-center gap-2 text-slate-600 dark:text-slate-300"
                            >
                                <BaseIcon
                                    name="contact"
                                    class="size-4 shrink-0 text-slate-400"
                                />
                                <span class="truncate">{{
                                    patient.phone || 'Sin teléfono'
                                }}</span>
                            </p>
                            <p
                                class="flex min-w-0 items-center gap-2 text-slate-600 dark:text-slate-300"
                            >
                                <BaseIcon
                                    name="calendar-days"
                                    class="size-4 shrink-0 text-slate-400"
                                />
                                <span class="truncate">{{
                                    formatDate(patient.birth_date)
                                }}</span>
                            </p>
                        </div>

                        <footer
                            class="mt-auto flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                        >
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Actualizado {{ formatDate(patient.updated_at) }}
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="`/pacientes/${patient.id}`"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:border-clinic-300 hover:text-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-clinic-700 dark:hover:text-clinic-300"
                                >
                                    <BaseIcon name="eye" class="size-4" />
                                    Ver
                                </Link>
                                <Link
                                    v-if="
                                        patient.permissions.update &&
                                        !isArchived(patient)
                                    "
                                    :href="`/pacientes/${patient.id}/editar`"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:border-clinic-300 hover:text-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-clinic-700 dark:hover:text-clinic-300"
                                >
                                    <BaseIcon name="edit" class="size-4" />
                                    Editar
                                </Link>
                                <button
                                    v-if="patient.permissions.archive"
                                    type="button"
                                    :disabled="activeAction === patient.id"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-wait disabled:opacity-60 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                    @click="archivePatient(patient)"
                                >
                                    <BaseIcon name="archive" class="size-4" />
                                    Archivar
                                </button>
                                <button
                                    v-if="patient.permissions.restore"
                                    type="button"
                                    :disabled="activeAction === patient.id"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                    @click="restorePatient(patient)"
                                >
                                    <BaseIcon name="rotate-ccw" class="size-4" />
                                    Restaurar
                                </button>
                            </div>
                        </footer>
                    </article>
                </li>
            </ul>

            <div v-else class="grid min-h-72 place-items-center p-8 text-center">
                <div>
                    <span
                        class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <BaseIcon
                            :name="hasFilters ? 'search' : 'patients'"
                            class="size-5"
                        />
                    </span>
                    <h3 class="pt-4 font-semibold text-slate-950 dark:text-white">
                        {{
                            hasFilters
                                ? 'No encontramos pacientes'
                                : 'Todavía no hay pacientes'
                        }}
                    </h3>
                    <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            hasFilters
                                ? 'Probá modificando o limpiando los filtros.'
                                : permissions.create
                                  ? 'Registrá la primera ficha para comenzar.'
                                  : 'No hay fichas disponibles para consultar.'
                        }}
                    </p>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl px-3 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:text-clinic-300 dark:hover:bg-clinic-400/10"
                        @click="clearFilters"
                    >
                        Limpiar filtros
                    </button>
                    <Link
                        v-else-if="permissions.create"
                        href="/pacientes/nuevo"
                        class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white transition hover:bg-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                    >
                        <BaseIcon name="user-plus" class="size-4" />
                        Nuevo paciente
                    </Link>
                </div>
            </div>

            <PaginationNav
                :links="patients.links"
                :from="patients.from"
                :to="patients.to"
                :total="patients.total"
            />
        </section>
    </AppShell>
</template>
