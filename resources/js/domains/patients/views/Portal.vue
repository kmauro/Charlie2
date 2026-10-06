<script setup lang="ts">
import BrandMark from '@/shared/components/BrandMark.vue';
import FieldError from '@/shared/components/FieldError.vue';
import FlashMessage from '@/shared/components/FlashMessage.vue';
import type { AppFlashData } from '@/shared/types/inertia';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    patient: {
        display_name: string;
        document_number: string;
        email: string;
        phone: string | null;
        address: string | null;
        coverage_name: string;
        member_number: string | null;
        notification_preferences: { appointment_reminders_by_email: boolean; appointment_reminders_by_whatsapp: boolean; appointment_reminder_hours: number };
    };
    upcomingAppointments: Array<{
        id: number;
        starts_at: string;
        professional: string;
        location: string;
        status: string;
    }>;
}>();

const page = usePage();
const flash = computed<AppFlashData>(() => page.props.flash ?? {});
const form = useForm({
    phone: props.patient.phone ?? '',
    email: props.patient.email,
    address: props.patient.address ?? '',
    appointment_reminders_by_email: props.patient.notification_preferences.appointment_reminders_by_email,
    appointment_reminders_by_whatsapp: props.patient.notification_preferences.appointment_reminders_by_whatsapp,
    appointment_reminder_hours: props.patient.notification_preferences.appointment_reminder_hours,
});
const formatDate = (value: string): string => new Intl.DateTimeFormat('es-AR', {
    dateStyle: 'full',
    timeStyle: 'short',
}).format(new Date(value));
const submit = (): void => form.put('/portal/perfil', { preserveScroll: true });
</script>

<template>
    <Head title="Mi portal" />
    <div class="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-white">
        <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <BrandMark />
                <div class="flex items-center gap-2">
                    <Link href="/turnos" class="inline-flex min-h-10 items-center rounded-xl px-3 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:text-clinic-300 dark:hover:bg-clinic-950/40">Mis turnos</Link>
                    <Link href="/logout" method="post" as="button" class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Salir</Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8">
            <FlashMessage :message="flash.success" :error="flash.error" />
            <section class="rounded-3xl bg-clinic-700 p-6 text-white shadow-lg shadow-clinic-700/20 sm:p-8">
                <p class="text-sm font-semibold text-clinic-100">Portal del paciente</p>
                <h1 class="mt-2 text-3xl font-bold">Hola, {{ patient.display_name }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-clinic-100">Consultá tus próximos turnos y mantené actualizados tus datos de contacto.</p>
            </section>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold">Próximos turnos</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Podés cancelar un turno con la anticipación permitida desde Mis turnos.</p></div>
                    <div v-if="upcomingAppointments.length" class="divide-y divide-slate-100 dark:divide-slate-800"><article v-for="appointment in upcomingAppointments" :key="appointment.id" class="p-5"><p class="font-semibold text-slate-950 dark:text-white">{{ formatDate(appointment.starts_at) }}</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ appointment.professional }} · {{ appointment.location }}</p><span class="mt-3 inline-flex rounded-full bg-clinic-50 px-2.5 py-1 text-xs font-semibold text-clinic-700 dark:bg-clinic-950/40 dark:text-clinic-300">{{ appointment.status }}</span></article></div>
                    <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">No tenés próximos turnos.</p>
                </section>

                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit"><h2 class="font-semibold">Mis datos y recordatorios</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">La identidad, cobertura y responsables se gestionan con la institución.</p><dl class="mt-5 space-y-3 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-950/60"><div><dt class="text-xs font-semibold text-slate-500 uppercase">DNI</dt><dd class="mt-1 font-medium">{{ patient.document_number }}</dd></div><div><dt class="text-xs font-semibold text-slate-500 uppercase">Cobertura</dt><dd class="mt-1 font-medium">{{ patient.coverage_name }}{{ patient.member_number ? ` · ${patient.member_number}` : '' }}</dd></div></dl><label class="mt-5 block text-sm font-medium text-slate-700 dark:text-slate-200">Teléfono<input v-model="form.phone" type="tel" autocomplete="tel" maxlength="32" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 dark:border-slate-700 dark:bg-slate-950" /></label><FieldError :message="form.errors.phone" /><label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Correo electrónico<input v-model="form.email" required type="email" autocomplete="email" maxlength="255" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 dark:border-slate-700 dark:bg-slate-950" /></label><FieldError :message="form.errors.email" /><label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Dirección<input v-model="form.address" type="text" autocomplete="street-address" maxlength="1000" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 dark:border-slate-700 dark:bg-slate-950" /></label><FieldError :message="form.errors.address" /><div class="mt-5 space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><p class="text-sm font-semibold">Recordatorios de turno</p><label class="flex items-center gap-3 text-sm"><input v-model="form.appointment_reminders_by_email" type="checkbox" class="size-4 rounded" /> Correo electrónico</label><label class="block text-sm">Anticipación<select v-model="form.appointment_reminder_hours" class="mt-1.5 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 dark:border-slate-700 dark:bg-slate-950"><option :value="12">12 horas</option><option :value="24">24 horas</option><option :value="48">48 horas</option></select></label><p class="text-xs text-slate-500 dark:text-slate-400">WhatsApp se habilitará cuando la institución configure un proveedor.</p></div><button type="submit" :disabled="form.processing" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700 disabled:opacity-60">{{ form.processing ? 'Guardando…' : 'Guardar datos' }}</button></form>
            </div>
        </main>
    </div>
</template>
