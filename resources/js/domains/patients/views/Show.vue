<script setup lang="ts">
import type { Patient, PatientPermissions } from '@/domains/patients/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        patient: Patient;
        permissions: PatientPermissions;
        externalClinicalRecordUrl?: string | null;
    }>(),
    {
        externalClinicalRecordUrl: null,
    },
);

const activeAction = ref(false);
const isArchived = computed(() => props.patient.status.value === 'archived');

const formatDate = (value: string | null, withTime = false): string => {
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
        dateStyle: 'long',
        ...(withTime ? { timeStyle: 'short' as const } : {}),
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

const archivePatient = (): void => {
    if (
        !window.confirm(
            `¿Archivar la ficha de ${props.patient.display_name}? La información y su trazabilidad se conservarán.`,
        )
    ) {
        return;
    }

    activeAction.value = true;
    router.delete(`/pacientes/${props.patient.id}`, {
        onFinish: () => {
            activeAction.value = false;
        },
    });
};

const restorePatient = (): void => {
    if (
        !window.confirm(
            `¿Restaurar la ficha de ${props.patient.display_name} a su estado previo?`,
        )
    ) {
        return;
    }

    activeAction.value = true;
    router.patch(
        `/pacientes/${props.patient.id}/restaurar`,
        {},
        {
            onFinish: () => {
                activeAction.value = false;
            },
        },
    );
};

</script>

<template>
    <Head :title="patient.display_name" />

    <AppShell
        :title="patient.display_name"
        eyebrow="Ficha administrativa"
        description="Datos demográficos, cobertura y contactos relacionados."
    >
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <Link
                href="/pacientes"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-clinic-300 hover:text-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-clinic-700 dark:hover:text-clinic-300"
            >
                <BaseIcon name="arrow-left" class="size-4" />
                Volver
            </Link>
            <Link
                v-if="permissions.update && !isArchived"
                :href="`/pacientes/${patient.id}/editar`"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white transition hover:bg-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
            >
                <BaseIcon name="edit" class="size-4" />
                Editar
            </Link>
            <button
                v-if="permissions.archive && !isArchived"
                type="button"
                :disabled="activeAction"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-wait disabled:opacity-60 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300 dark:hover:bg-rose-950/60"
                @click="archivePatient"
            >
                <BaseIcon name="archive" class="size-4" />
                Archivar
            </button>
            <button
                v-if="permissions.restore && isArchived"
                type="button"
                :disabled="activeAction"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                @click="restorePatient"
            >
                <BaseIcon name="rotate-ccw" class="size-4" />
                Restaurar
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.75fr)]">
            <div class="flex flex-col gap-6">
                <section
                    aria-labelledby="patient-profile-title"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-4">
                            <span
                                class="grid size-12 shrink-0 place-items-center rounded-full bg-clinic-100 text-sm font-bold text-clinic-800 dark:bg-clinic-400/15 dark:text-clinic-200"
                                aria-hidden="true"
                            >
                                {{
                                    `${patient.first_name.charAt(0)}${patient.last_name.charAt(0)}`.toLocaleUpperCase(
                                        'es-AR',
                                    )
                                }}
                            </span>
                            <div>
                                <h2
                                    id="patient-profile-title"
                                    class="font-semibold text-slate-950 dark:text-white"
                                >
                                    Identificación
                                </h2>
                                <p
                                    class="pt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    DNI {{ patient.document_number }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset"
                            :class="statusClasses(patient.status.value)"
                        >
                            {{ patient.status.label }}
                        </span>
                    </div>

                    <dl class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Fecha de nacimiento
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ formatDate(patient.birth_date) }} ·
                                {{ patient.age }} años
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Sexo registral
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ patient.registered_sex.label }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Género
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ patient.gender || 'No informado' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Domicilio
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ patient.address || 'No informado' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Teléfono
                            </dt>
                            <dd class="pt-1 text-sm font-medium">
                                <a
                                    v-if="patient.phone"
                                    :href="`tel:${patient.phone}`"
                                    class="rounded-sm text-clinic-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:text-clinic-300"
                                >
                                    {{ patient.phone }}
                                </a>
                                <span
                                    v-else
                                    class="text-slate-900 dark:text-slate-100"
                                >
                                    No informado
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Correo electrónico
                            </dt>
                            <dd class="min-w-0 pt-1 text-sm font-medium">
                                <a
                                    v-if="patient.email"
                                    :href="`mailto:${patient.email}`"
                                    class="break-all rounded-sm text-clinic-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:text-clinic-300"
                                >
                                    {{ patient.email }}
                                </a>
                                <span
                                    v-else
                                    class="text-slate-900 dark:text-slate-100"
                                >
                                    No informado
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    aria-labelledby="patient-contacts-title"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-200 p-5 sm:p-6 dark:border-slate-800">
                        <h2
                            id="patient-contacts-title"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Contactos relacionados
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ patient.contacts.length }}
                            {{
                                patient.contacts.length === 1
                                    ? 'contacto registrado'
                                    : 'contactos registrados'
                            }}
                        </p>
                    </div>

                    <ul
                        v-if="patient.contacts.length"
                        class="divide-y divide-slate-200 dark:divide-slate-800"
                    >
                        <li
                            v-for="contact in patient.contacts"
                            :key="contact.id ?? `${contact.full_name}-${contact.phone}`"
                            class="p-5 sm:p-6"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <h3
                                        class="font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ contact.full_name }}
                                    </h3>
                                    <p
                                        class="pt-1 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ contact.relationship }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-if="contact.is_emergency_contact"
                                        class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-400/10 dark:text-rose-300"
                                    >
                                        Emergencias
                                    </span>
                                    <span
                                        v-if="contact.is_legal_representative"
                                        class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-400/10 dark:text-violet-300"
                                    >
                                        Representante legal
                                    </span>
                                    <span
                                        v-if="contact.is_financially_responsible"
                                        class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-400/10 dark:text-amber-300"
                                    >
                                        Responsable de pago
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                <a
                                    v-if="contact.phone"
                                    :href="`tel:${contact.phone}`"
                                    class="flex min-w-0 items-center gap-2 rounded-sm text-clinic-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:text-clinic-300"
                                >
                                    <BaseIcon name="contact" class="size-4 shrink-0" />
                                    <span class="truncate">{{ contact.phone }}</span>
                                </a>
                                <span
                                    v-else
                                    class="flex items-center gap-2 text-slate-500 dark:text-slate-400"
                                >
                                    <BaseIcon name="contact" class="size-4" />
                                    Sin teléfono
                                </span>
                                <a
                                    v-if="contact.email"
                                    :href="`mailto:${contact.email}`"
                                    class="flex min-w-0 items-center gap-2 rounded-sm text-clinic-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:text-clinic-300"
                                >
                                    <BaseIcon name="mail" class="size-4 shrink-0" />
                                    <span class="truncate">{{ contact.email }}</span>
                                </a>
                                <span
                                    v-else
                                    class="flex items-center gap-2 text-slate-500 dark:text-slate-400"
                                >
                                    <BaseIcon name="mail" class="size-4" />
                                    Sin correo
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No hay contactos relacionados cargados.
                    </p>
                </section>

                <section
                    v-if="externalClinicalRecordUrl"
                    aria-labelledby="external-clinical-record-title"
                    class="overflow-hidden rounded-2xl border border-clinic-200 bg-white shadow-sm dark:border-clinic-900/70 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-clinic-100 text-clinic-700 dark:bg-clinic-400/15 dark:text-clinic-300"
                            >
                                <BaseIcon name="stethoscope" class="size-5" />
                            </span>
                            <div>
                                <h2
                                    id="external-clinical-record-title"
                                    class="font-semibold text-slate-950 dark:text-white"
                                >
                                    Historia clínica externa
                                </h2>
                                <p
                                    class="max-w-2xl pt-1 text-sm leading-6 text-slate-600 dark:text-slate-400"
                                >
                                    La información clínica se administra en una
                                    plataforma externa. Este sistema no almacena
                                    evoluciones, estudios, recetas ni alertas clínicas.
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="externalClinicalRecordUrl"
                            class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600"
                        >
                            Abrir historia clínica
                            <BaseIcon name="arrow-right" class="size-4" />
                        </Link>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-6">
                <section
                    aria-labelledby="patient-coverage-title"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"
                            >
                                <BaseIcon name="shield-check" class="size-4.5" />
                            </span>
                            <h2
                                id="patient-coverage-title"
                                class="font-semibold text-slate-950 dark:text-white"
                            >
                                Cobertura
                            </h2>
                        </div>
                    </div>
                    <dl class="grid gap-4 p-5">
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Prestador
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ patient.coverage_name || 'Particular' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Número de afiliado
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ patient.member_number || 'No corresponde' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    aria-labelledby="patient-trace-title"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <h2
                        id="patient-trace-title"
                        class="font-semibold text-slate-950 dark:text-white"
                    >
                        Trazabilidad
                    </h2>
                    <dl class="mt-4 grid gap-4">
                        <div>
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Última actualización
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ formatDate(patient.updated_at, true) }}
                            </dd>
                        </div>
                        <div v-if="patient.archived_at">
                            <dt
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Archivado
                            </dt>
                            <dd
                                class="pt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ formatDate(patient.archived_at, true) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </AppShell>
</template>
