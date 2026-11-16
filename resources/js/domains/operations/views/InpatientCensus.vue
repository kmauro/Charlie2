<script setup lang="ts">
import AppShell from '@/shared/layouts/AppShell.vue';
import { router } from '@inertiajs/vue3';

defineProps<{ admissions: Array<{ id: number; patient: string; document_number: string; location: string; admitted_at: string; lock_version: number; bed_assignment_id: number | null; bed: string | null; received_at: string | null }>; permissions: { admit: boolean } }>();
const discharge = (id: number, version: number) => router.patch(`/internaciones/${id}/egresar`, { lock_version: version }, { preserveScroll: true });
const receive = (id: number) => router.patch(`/internaciones/asignaciones/${id}/recepcion`, {}, { preserveScroll: true });
</script>

<template>
    <AppShell title="Censo de internación" eyebrow="Circuitos operativos" description="Internaciones activas, ubicación actual y recepción de movimientos.">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/60 dark:text-slate-400"><tr><th class="px-5 py-3">Paciente</th><th class="px-5 py-3">Sede</th><th class="px-5 py-3">Ubicación</th><th class="px-5 py-3">Ingreso</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><tr v-for="admission in admissions" :key="admission.id"><td class="px-5 py-4 font-medium text-slate-900 dark:text-white">{{ admission.patient }}<span class="ml-2 text-xs font-normal text-slate-500">{{ admission.document_number }}</span></td><td class="px-5 py-4">{{ admission.location }}</td><td class="px-5 py-4">{{ admission.bed ?? 'Sin ubicación' }}</td><td class="px-5 py-4">{{ new Date(admission.admitted_at).toLocaleString('es-AR') }}</td><td class="flex gap-2 px-5 py-4"><button v-if="admission.bed_assignment_id && !admission.received_at" class="rounded-lg border border-clinic-600 px-3 py-2 text-xs font-semibold text-clinic-700 hover:bg-clinic-50" @click="receive(admission.bed_assignment_id)">Confirmar recepción</button><button class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700" @click="discharge(admission.id, admission.lock_version)">Egreso</button></td></tr><tr v-if="admissions.length === 0"><td colspan="5" class="px-5 py-12 text-center text-slate-500">No hay internaciones activas en las sedes habilitadas.</td></tr></tbody></table></div></section>
    </AppShell>
</template>
