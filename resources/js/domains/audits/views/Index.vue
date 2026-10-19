<script setup lang="ts">
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import type { PaginationLink } from '@/shared/types/inertia';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface AuditLogItem {
    id: number;
    created_at: string;
    actor: string;
    event: string;
    resource: string;
    resource_id: string | null;
    outcome: string;
    correlation_id: string;
    ip_address: string | null;
}

interface AuditPaginator {
    data: AuditLogItem[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface SelectOption {
    value: number;
    label: string;
}

const props = defineProps<{
    logs: AuditPaginator;
    filters: Record<string, string | number | null>;
    actors: SelectOption[];
    canExport: boolean;
}>();

const form = reactive({
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
    actor_id: String(props.filters.actor_id ?? ''),
    event: String(props.filters.event ?? ''),
    outcome: String(props.filters.outcome ?? ''),
    resource: String(props.filters.resource ?? ''),
});

const activeFilters = computed(() =>
    Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')),
);
const exportUrl = computed(() => {
    const query = new URLSearchParams(activeFilters.value).toString();

    return query === '' ? '/auditoria/exportar' : `/auditoria/exportar?${query}`;
});

const submit = (): void => {
    router.get('/auditoria', activeFilters.value, {
        preserveState: true,
        replace: true,
    });
};

const clear = (): void => {
    Object.assign(form, {
        from: '',
        to: '',
        actor_id: '',
        event: '',
        outcome: '',
        resource: '',
    });
    submit();
};

const outcomeLabel = (outcome: string): string =>
    ({ success: 'Correcto', denied: 'Denegado', failure: 'Fallido' })[outcome] ??
    outcome;
</script>

<template>
    <Head title="Auditoría" />

    <AppShell
        title="Registro de auditoría"
        eyebrow="Seguridad"
        description="Consultá y exportá los eventos administrativos registrados por el sistema."
    >
        <form
            class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-card md:grid-cols-2 xl:grid-cols-6 dark:border-slate-800 dark:bg-slate-900"
            @submit.prevent="submit"
        >
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Desde
                <input v-model="form.from" type="date" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950" />
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Hasta
                <input v-model="form.to" type="date" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950" />
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Actor
                <select v-model="form.actor_id" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Todos</option>
                    <option v-for="actor in actors" :key="actor.value" :value="actor.value">{{ actor.label }}</option>
                </select>
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Resultado
                <select v-model="form.outcome" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Todos</option>
                    <option value="success">Correcto</option>
                    <option value="denied">Denegado</option>
                    <option value="failure">Fallido</option>
                </select>
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Evento
                <input v-model="form.event" type="search" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="patient.updated" />
            </label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                Recurso
                <input v-model="form.resource" type="search" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Patient" />
            </label>

            <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-6">
                <button type="submit" class="rounded-xl bg-clinic-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-clinic-700">Filtrar</button>
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" @click="clear">Limpiar</button>
                <a v-if="canExport" :href="exportUrl" class="ml-auto rounded-xl border border-clinic-200 px-4 py-2.5 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-800 dark:text-clinic-300 dark:hover:bg-clinic-950/40">Exportar CSV</a>
            </div>
        </form>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Fecha</th>
                            <th class="px-5 py-3 font-semibold">Actor</th>
                            <th class="px-5 py-3 font-semibold">Evento</th>
                            <th class="px-5 py-3 font-semibold">Recurso</th>
                            <th class="px-5 py-3 font-semibold">Resultado</th>
                            <th class="px-5 py-3 font-semibold">Correlación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="log in logs.data" :key="log.id" class="text-slate-700 dark:text-slate-300">
                            <td class="whitespace-nowrap px-5 py-4">{{ log.created_at }}</td>
                            <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">{{ log.actor }}</td>
                            <td class="px-5 py-4 font-mono text-xs">{{ log.event }}</td>
                            <td class="px-5 py-4">{{ log.resource }}<span v-if="log.resource_id" class="text-slate-400"> #{{ log.resource_id }}</span></td>
                            <td class="px-5 py-4">{{ outcomeLabel(log.outcome) }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ log.correlation_id }}</td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No hay eventos para los filtros seleccionados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationNav :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" />
        </section>
    </AppShell>
</template>
