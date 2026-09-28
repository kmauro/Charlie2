<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import SearchableCombobox from '@/shared/components/SearchableCombobox.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    entries: Array<{ id: number; patient: string; document_number: string; professional: string | null; location: string | null; type: string; desired_from: string | null; priority: number; notes: string | null; schedule_url: string }>;
    patientOptions: Array<{ value: number; label: string; document_number?: string }>;
    professionalOptions: Array<{ value: number; label: string; specialty: string }>;
    locationOptions: Array<{ value: number; label: string }>;
    typeOptions: Array<{ value: string; label: string }>;
    storeUrl: string;
}>();

const form = useForm({ patient_id: '' as number | '', professional_id: '' as number | '', location_id: '' as number | '', type: 'in_person', desired_from: '', priority: 0, notes: '' });
const patients = computed(() => props.patientOptions.map((item) => ({ value: item.value, label: item.label, description: item.document_number ? `DNI ${item.document_number}` : undefined, searchText: `${item.label} ${item.document_number ?? ''}` })));
const submit = (): void => form.post(props.storeUrl, { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Lista de espera" />
    <AppShell title="Lista de espera" eyebrow="Agenda clínica" description="Registrá solicitudes y asigná un turno manualmente cuando haya disponibilidad." action-label="Volver a turnos" action-href="/turnos">
        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-3"><span class="rounded-xl bg-clinic-50 p-2.5 text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300"><BaseIcon name="user-plus" class="size-5" /></span><div><h2 class="font-semibold text-slate-950 dark:text-white">Nueva solicitud</h2><p class="text-xs text-slate-500 dark:text-slate-400">No reserva un horario.</p></div></div>
                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div><label class="text-sm font-medium text-slate-800 dark:text-slate-200">Paciente</label><SearchableCombobox v-model="form.patient_id" input-id="waitlist-patient" :options="patients" placeholder="Seleccionar paciente" search-placeholder="Buscar por apellido o DNI..." empty-message="No se encontraron pacientes." :invalid="Boolean(form.errors.patient_id)" required /><FieldError :message="form.errors.patient_id" class="mt-1.5" /></div>
                    <div><label for="waitlist-professional" class="text-sm font-medium text-slate-800 dark:text-slate-200">Profesional preferido</label><select id="waitlist-professional" v-model="form.professional_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">Cualquiera</option><option v-for="professional in professionalOptions" :key="professional.value" :value="professional.value">{{ professional.label }} · {{ professional.specialty }}</option></select></div>
                    <div><label for="waitlist-location" class="text-sm font-medium text-slate-800 dark:text-slate-200">Sede preferida</label><select id="waitlist-location" v-model="form.location_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">Cualquiera</option><option v-for="location in locationOptions" :key="location.value" :value="location.value">{{ location.label }}</option></select></div>
                    <div class="grid grid-cols-2 gap-3"><div><label for="waitlist-type" class="text-sm font-medium text-slate-800 dark:text-slate-200">Tipo</label><select id="waitlist-type" v-model="form.type" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option v-for="type in typeOptions" :key="type.value" :value="type.value">{{ type.label }}</option></select></div><div><label for="waitlist-priority" class="text-sm font-medium text-slate-800 dark:text-slate-200">Prioridad</label><input id="waitlist-priority" v-model="form.priority" type="number" min="0" max="9" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></div></div>
                    <div><label for="waitlist-date" class="text-sm font-medium text-slate-800 dark:text-slate-200">Disponible desde</label><input id="waitlist-date" v-model="form.desired_from" type="date" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></div>
                    <div><label for="waitlist-notes" class="text-sm font-medium text-slate-800 dark:text-slate-200">Observaciones</label><textarea id="waitlist-notes" v-model="form.notes" rows="3" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></div>
                    <button :disabled="form.processing" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700 disabled:opacity-60"><BaseIcon :name="form.processing ? 'loader' : 'plus'" class="size-4" :class="{ 'animate-spin': form.processing }" />{{ form.processing ? 'Guardando…' : 'Incorporar a la lista' }}</button>
                </form>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Solicitudes pendientes</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Se ordenan por prioridad y antigüedad.</p></div><div v-if="entries.length" class="divide-y divide-slate-100 dark:divide-slate-800"><article v-for="entry in entries" :key="entry.id" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950 dark:text-white">{{ entry.patient }}</h3><span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">Prioridad {{ entry.priority }}</span></div><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">DNI {{ entry.document_number }} · {{ entry.type }}<template v-if="entry.professional"> · {{ entry.professional }}</template><template v-if="entry.location"> · {{ entry.location }}</template><template v-if="entry.desired_from"> · Desde {{ entry.desired_from }}</template></p><p v-if="entry.notes" class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ entry.notes }}</p></div><Link :href="entry.schedule_url" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-clinic-200 px-4 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-800 dark:text-clinic-300 dark:hover:bg-clinic-950/30"><BaseIcon name="calendar" class="size-4" />Asignar turno</Link></article></div><div v-else class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400"><BaseIcon name="clock" class="mx-auto size-8 text-slate-400" /> <p class="mt-3">No hay solicitudes pendientes.</p></div></section>
        </div>
    </AppShell>
</template>
