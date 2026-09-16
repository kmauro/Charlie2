<script setup lang="ts">
import type {
    LegalEntityOption,
    Location,
    LocationFilters,
    LocationsPaginator,
    OrganizationOption,
    OrganizationPermissions,
    OrganizationSummaries,
} from '@/domains/organizations/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    locations: LocationsPaginator;
    filters: LocationFilters;
    summaries: OrganizationSummaries;
    legalEntities: LegalEntityOption[];
    statusOptions: OrganizationOption[];
    permissions: OrganizationPermissions;
}>();

const search = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const selectedLegalEntity = ref(
    props.filters.legal_entity_id
        ? String(props.filters.legal_entity_id)
        : '',
);
const activeAction = ref<number | null>(null);
const hasFilters = computed(
    () =>
        search.value.trim() !== '' ||
        selectedStatus.value !== '' ||
        selectedLegalEntity.value !== '',
);
const summaryCards = computed(() => [
    {
        label: 'Razones sociales',
        value: props.summaries.legal_entities_total,
        detail: `${props.summaries.legal_entities_active} activas`,
        icon: 'building',
    },
    {
        label: 'Sedes',
        value: props.summaries.locations_total,
        detail: 'registradas',
        icon: 'map-pin',
    },
    {
        label: 'Operativas',
        value: props.summaries.locations_active,
        detail: 'activas',
        icon: 'check-circle',
    },
    {
        label: 'En preparación',
        value: props.summaries.locations_preparation,
        detail: 'próximas aperturas',
        icon: 'clock',
    },
]);

const statusClasses = (status: string): string => {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20';
    }

    if (status === 'preparation') {
        return 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/20';
    }

    if (status === 'inactive') {
        return 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-500/30';
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return 'Sin fecha definida';
    }

    const parts = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const date = parts
        ? new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]))
        : new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium' }).format(
              date,
          );
};

const applyFilters = (): void => {
    router.get(
        '/organizacion/sedes',
        {
            search: search.value.trim() || undefined,
            status: selectedStatus.value || undefined,
            legal_entity_id: selectedLegalEntity.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearFilters = (): void => {
    search.value = '';
    selectedStatus.value = '';
    selectedLegalEntity.value = '';
    applyFilters();
};

const archiveLocation = (location: Location): void => {
    if (
        !window.confirm(
            `¿Archivar ${location.name}? La sede dejará de estar disponible para nuevas operaciones.`,
        )
    ) {
        return;
    }

    activeAction.value = location.id;
    router.delete(`/organizacion/sedes/${location.id}`, {
        preserveScroll: true,
        onFinish: () => {
            activeAction.value = null;
        },
    });
};

const restoreLocation = (location: Location): void => {
    if (!window.confirm(`¿Restaurar ${location.name} como sede activa?`)) {
        return;
    }

    activeAction.value = location.id;
    router.patch(
        `/organizacion/sedes/${location.id}/restaurar`,
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
    <Head title="Sedes" />

    <AppShell
        title="Sedes"
        eyebrow="Organización"
        description="Administrá la estructura legal y la configuración operativa de cada sede."
        :action-href="permissions.create ? '/organizacion/sedes/nueva' : ''"
        :action-label="permissions.create ? 'Nueva sede' : ''"
        action-icon="plus"
    >
        <section aria-labelledby="organization-summary-title">
            <h2 id="organization-summary-title" class="sr-only">Resumen de organización</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="summary in summaryCards"
                    :key="summary.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ summary.label }}</p>
                            <p class="pt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ summary.value }}</p>
                            <p class="pt-1 text-xs text-slate-500 dark:text-slate-400">{{ summary.detail }}</p>
                        </div>
                        <span class="grid size-10 place-items-center rounded-xl bg-clinic-50 text-clinic-700 dark:bg-clinic-400/10 dark:text-clinic-300">
                            <BaseIcon :name="summary.icon" class="size-5" />
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <div class="mt-6 flex justify-end">
            <Link
                href="/organizacion/razones-sociales"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-clinic-300 hover:bg-clinic-50 hover:text-clinic-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-clinic-700 dark:hover:bg-clinic-950/30 dark:hover:text-clinic-300"
            >
                <BaseIcon name="building" class="size-4" />
                Gestionar razones sociales
            </Link>
        </div>

        <section
            aria-labelledby="locations-title"
            class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <header class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2 id="locations-title" class="text-lg font-semibold text-slate-950 dark:text-white">Padrón de sedes</h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ locations.total }} {{ locations.total === 1 ? 'sede encontrada' : 'sedes encontradas' }}
                        </p>
                    </div>

                    <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_11rem_14rem_auto]" role="search" @submit.prevent="applyFilters">
                        <div class="relative">
                            <label for="location-search" class="sr-only">Buscar sedes</label>
                            <BaseIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="location-search"
                                v-model="search"
                                type="search"
                                placeholder="Nombre, código o dirección"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pr-3 pl-10 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </div>
                        <select
                            v-model="selectedStatus"
                            aria-label="Filtrar por estado"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        >
                            <option value="">Todos los estados</option>
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <select
                            v-model="selectedLegalEntity"
                            aria-label="Filtrar por razón social"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        >
                            <option value="">Todas las razones sociales</option>
                            <option v-for="entity in legalEntities" :key="entity.id" :value="String(entity.id)">{{ entity.label }}</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                                <BaseIcon name="filter" class="size-4" /> Filtrar
                            </button>
                            <button v-if="hasFilters" type="button" aria-label="Limpiar filtros" class="grid size-11 place-items-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" @click="clearFilters">
                                <BaseIcon name="rotate-ccw" class="size-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </header>

            <ul v-if="locations.data.length" class="grid gap-4 p-4 lg:grid-cols-2 2xl:grid-cols-3 sm:p-6">
                <li v-for="location in locations.data" :key="location.id">
                    <article class="flex h-full flex-col rounded-2xl border border-slate-200 p-5 transition hover:border-clinic-300 hover:shadow-sm dark:border-slate-700 dark:hover:border-clinic-700">
                        <header class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-slate-950 dark:text-white">{{ location.name }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClasses(location.status.value)">
                                        {{ location.status.label }}
                                    </span>
                                </div>
                                <p class="pt-1 text-xs font-semibold tracking-wide text-clinic-700 uppercase dark:text-clinic-300">Código {{ location.code }}</p>
                            </div>
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                                <BaseIcon name="map-pin" class="size-5" />
                            </span>
                        </header>

                        <dl class="grid gap-3 pt-5 text-sm">
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Razón social</dt>
                                <dd class="pt-1 font-medium text-slate-800 dark:text-slate-200">{{ location.legal_entity.business_name }}</dd>
                                <dd class="text-xs text-slate-500">CUIT {{ location.legal_entity.cuit }}</dd>
                            </div>
                            <div class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                                <BaseIcon name="map-pin" class="mt-0.5 size-4 shrink-0 text-slate-400" />
                                <dd>{{ location.address }}<span v-if="location.neighborhood">, {{ location.neighborhood }}</span></dd>
                            </div>
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                <BaseIcon name="clock" class="size-4 shrink-0 text-slate-400" />
                                <dd>{{ location.opening_time }} a {{ location.closing_time }} · {{ formatDate(location.opening_date) }}</dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2 pt-5">
                            <span v-for="service in location.service_labels" :key="service" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ service }}</span>
                        </div>

                        <footer class="mt-auto flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <Link
                                v-if="location.permissions.update"
                                :href="`/organizacion/sedes/${location.id}/editar`"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <BaseIcon name="edit" class="size-4" /> Editar
                            </Link>
                            <button
                                v-if="location.permissions.archive"
                                type="button"
                                :disabled="activeAction === location.id"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                @click="archiveLocation(location)"
                            >
                                <BaseIcon name="archive" class="size-4" /> Archivar
                            </button>
                            <button
                                v-if="location.permissions.restore"
                                type="button"
                                :disabled="activeAction === location.id"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-emerald-200 px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                @click="restoreLocation(location)"
                            >
                                <BaseIcon name="rotate-ccw" class="size-4" /> Restaurar
                            </button>
                        </footer>
                    </article>
                </li>
            </ul>

            <div v-else class="grid min-h-64 place-items-center p-8 text-center">
                <div>
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><BaseIcon name="building" class="size-6" /></span>
                    <h3 class="pt-4 font-semibold text-slate-950 dark:text-white">No hay sedes para mostrar</h3>
                    <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">Creá una sede o ajustá los filtros aplicados.</p>
                </div>
            </div>

            <PaginationNav
                v-if="locations.last_page > 1"
                :links="locations.links"
                :from="locations.from"
                :to="locations.to"
                :total="locations.total"
            />
        </section>
    </AppShell>
</template>
