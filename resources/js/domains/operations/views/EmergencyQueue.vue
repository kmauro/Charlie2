<script setup lang="ts">
import AppShell from '@/shared/layouts/AppShell.vue';
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';

defineProps<{ visits: Array<{ id: number; patient: string; document_number: string; location: string; status: string; priority: number | null; arrived_at: string; lock_version: number }>; permissions: { create: boolean } }>();
const patch = (id: number, action: string, payload?: RequestPayload) => router.patch(`/guardia/${id}/${action}`, payload, { preserveScroll: true });
</script>

<template>
    <AppShell title="Guardia administrativa" eyebrow="Circuitos operativos" description="Ingresos abiertos, espera y atención administrativa por sede.">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/60 dark:text-slate-400"><tr><th class="px-5 py-3">Paciente</th><th class="px-5 py-3">Sede</th><th class="px-5 py-3">Llegada</th><th class="px-5 py-3">Prioridad</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><tr v-for="visit in visits" :key="visit.id"><td class="px-5 py-4 font-medium text-slate-900 dark:text-white">{{ visit.patient }}<span class="ml-2 text-xs font-normal text-slate-500">{{ visit.document_number }}</span></td><td class="px-5 py-4">{{ visit.location }}</td><td class="px-5 py-4">{{ new Date(visit.arrived_at).toLocaleString('es-AR') }}</td><td class="px-5 py-4">{{ visit.priority ?? '—' }}</td><td class="px-5 py-4"><span class="rounded-full bg-clinic-50 px-2.5 py-1 text-xs font-semibold text-clinic-700 dark:bg-clinic-950/50 dark:text-clinic-300">{{ visit.status }}</span></td><td class="px-5 py-4"><button v-if="visit.status === 'waiting'" class="rounded-lg bg-clinic-600 px-3 py-2 text-xs font-semibold text-white hover:bg-clinic-700" @click="patch(visit.id, 'iniciar', { lock_version: visit.lock_version })">Iniciar</button><button v-else class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700" @click="patch(visit.id, 'cerrar', { lock_version: visit.lock_version, outcome: 'discharge' })">Cerrar</button></td></tr><tr v-if="visits.length === 0"><td colspan="6" class="px-5 py-12 text-center text-slate-500">No hay ingresos abiertos en las sedes habilitadas.</td></tr></tbody></table></div>
        </section>
    </AppShell>
</template>
