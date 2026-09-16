<script setup lang="ts">
import type {
    DashboardProps,
    DashboardTone,
} from '@/domains/dashboard/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<DashboardProps>();

const page = usePage();
const accountRoleCode = computed(() => {
    const role = page.props.auth.user?.role;

    return typeof role === 'object' && role !== null ? role.code : '';
});
const quickActionItems = [
    {
        label: 'Invitar usuario',
        detail: 'Asignar rol y sedes',
        icon: 'user-plus',
        href: '/usuarios/nuevo',
        allowedRoles: ['superadmin'],
    },
    {
        label: 'Nuevo paciente',
        detail: 'Crear ficha administrativa',
        icon: 'patients',
        href: '/pacientes/nuevo',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
            'reception',
        ],
    },
    {
        label: 'Nuevo turno',
        detail: 'Reservar en la agenda',
        icon: 'calendar-days',
        href: '/turnos/crear',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
            'reception',
        ],
    },
    {
        label: 'Alta profesional',
        detail: 'Definir matrícula y agenda',
        icon: 'stethoscope',
        href: '/profesionales/nuevo',
        allowedRoles: ['superadmin'],
    },
    {
        label: 'Gestionar sedes',
        detail: 'Razones sociales y espacios',
        icon: 'building',
        href: '/organizacion/sedes',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
        ],
    },
];
const quickActions = computed(() =>
    quickActionItems.filter(
        (action) => action.allowedRoles?.includes(accountRoleCode.value) ?? true,
    ),
);

const toneClasses: Record<DashboardTone, string> = {
    blue: 'bg-clinic-50 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300',
    emerald:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    violet:
        'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppShell
        title="Buen día, equipo"
        eyebrow="Resumen operativo"
        description="Una vista rápida de la actividad de la clínica y las tareas que requieren atención."
    >
        <section
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Indicadores"
        >
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ metric.label }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ metric.value }}
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl"
                        :class="toneClasses[metric.tone]"
                    >
                        <BaseIcon :name="metric.icon" class="size-5" />
                    </span>
                </div>
                <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">
                    {{ metric.detail }}
                </p>
            </article>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.75fr)]">
            <section
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Próximos turnos
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ appointmentContext.location }} ·
                            {{ appointmentContext.period }}
                        </p>
                    </div>
                    <Link
                        href="/turnos"
                        class="rounded-lg px-3 py-2 text-xs font-semibold text-clinic-700 hover:bg-clinic-50 dark:text-clinic-300 dark:hover:bg-clinic-950/40"
                    >
                        Ver agenda
                    </Link>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article
                        v-for="appointment in upcomingAppointments"
                        :key="`${appointment.time}-${appointment.patient}`"
                        class="flex gap-4 px-5 py-4"
                    >
                        <div class="w-12 shrink-0">
                            <p
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ appointment.time }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-semibold text-slate-400 uppercase"
                            >
                                hs
                            </p>
                        </div>
                        <div
                            class="min-w-0 flex-1 border-l border-slate-200 pl-4 dark:border-slate-700"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ appointment.patient }}
                                    </p>
                                    <p
                                        class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ appointment.professional }} ·
                                        {{ appointment.specialty }}
                                    </p>
                                </div>
                                <span
                                    class="w-fit rounded-full px-2.5 py-1 text-[10px] font-bold"
                                    :class="toneClasses[appointment.tone]"
                                >
                                    {{ appointment.status }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Accesos rápidos
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Operaciones frecuentes
                        </p>
                    </div>
                    <BaseIcon
                        name="activity"
                        class="size-5 text-clinic-600 dark:text-clinic-300"
                    />
                </div>
                <div class="mt-4 grid gap-2">
                    <Link
                        v-for="action in quickActions"
                        :key="action.href"
                        :href="action.href"
                        class="group flex items-center gap-3 rounded-xl border border-transparent p-3 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-800"
                    >
                        <span
                            class="grid size-9 place-items-center rounded-xl bg-slate-100 text-slate-500 group-hover:bg-clinic-100 group-hover:text-clinic-700 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-clinic-950 dark:group-hover:text-clinic-300"
                        >
                            <BaseIcon :name="action.icon" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-xs font-semibold text-slate-800 dark:text-slate-100"
                            >
                                {{ action.label }}
                            </span>
                            <span
                                class="mt-0.5 block text-[11px] text-slate-400 dark:text-slate-500"
                            >
                                {{ action.detail }}
                            </span>
                        </span>
                        <BaseIcon
                            name="chevron-right"
                            class="size-4 text-slate-300 group-hover:text-clinic-600 dark:text-slate-600"
                        />
                    </Link>
                </div>
            </section>
        </div>

        <section
            class="mt-6 grid gap-4 md:grid-cols-3"
            aria-label="Estado operativo"
        >
            <article
                v-for="status in operationalStatuses"
                :key="status.title"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-items-center rounded-xl"
                        :class="toneClasses[status.tone]"
                    >
                        <BaseIcon :name="status.icon" class="size-4.5" />
                    </span>
                    <div>
                        <p
                            class="text-xs font-semibold text-slate-900 dark:text-white"
                        >
                            {{ status.title }}
                        </p>
                        <p
                            class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500"
                        >
                            {{ status.detail }}
                        </p>
                    </div>
                </div>
            </article>
        </section>
    </AppShell>
</template>
