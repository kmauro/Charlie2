<script setup lang="ts">
import type {
    LegalEntitiesPaginator,
    LegalEntity,
    LegalEntityFilters,
    OrganizationOption,
} from '@/domains/organizations/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    legalEntities: LegalEntitiesPaginator;
    filters: LegalEntityFilters;
    statusOptions: OrganizationOption[];
    permissions: { create?: boolean };
}>();

const search = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const activeAction = ref<number | null>(null);
const hasFilters = computed(
    () => search.value.trim() !== '' || selectedStatus.value !== '',
);

const statusClasses = (status: string): string => {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20';
    }

    if (status === 'inactive') {
        return 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-500/30';
};

const applyFilters = (): void => {
    router.get(
        '/organizacion/razones-sociales',
        {
            search: search.value.trim() || undefined,
            status: selectedStatus.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearFilters = (): void => {
    search.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const archiveLegalEntity = (legalEntity: LegalEntity): void => {
    if (legalEntity.unarchived_locations_count > 0) {
        return;
    }

    if (
        !window.confirm(
            `¿Archivar ${legalEntity.business_name}? Sus datos fiscales se conservarán.`,
        )
    ) {
        return;
    }

    activeAction.value = legalEntity.id;
    router.delete(`/organizacion/razones-sociales/${legalEntity.id}`, {
        preserveScroll: true,
        onFinish: () => {
            activeAction.value = null;
        },
    });
};

const restoreLegalEntity = (legalEntity: LegalEntity): void => {
    if (
        !window.confirm(`¿Restaurar ${legalEntity.business_name} como entidad activa?`)
    ) {
        return;
    }

    activeAction.value = legalEntity.id;
    router.patch(
        `/organizacion/razones-sociales/${legalEntity.id}/restaurar`,
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
    <Head title="Razones sociales" />
    <AppShell
        title="Razones sociales"
        eyebrow="Organización"
        description="Gestioná las entidades fiscales del grupo y controlá qué sedes dependen de cada una."
        :action-href="permissions.create ? '/organizacion/razones-sociales/nueva' : ''"
        :action-label="permissions.create ? 'Nueva razón social' : ''"
        action-icon="plus"
    >
        <div class="mb-4 flex justify-start">
            <Link
                href="/organizacion/sedes"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <BaseIcon name="arrow-left" class="size-4" /> Volver a sedes
            </Link>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Entidades fiscales</h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ legalEntities.total }} {{ legalEntities.total === 1 ? 'entidad encontrada' : 'entidades encontradas' }}
                        </p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-[minmax(15rem,1fr)_11rem_auto]" role="search" @submit.prevent="applyFilters">
                        <div class="relative">
                            <label for="legal-entity-search" class="sr-only">Buscar razón social</label>
                            <BaseIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="legal-entity-search"
                                v-model="search"
                                type="search"
                                placeholder="Nombre, CUIT o domicilio"
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

            <ul v-if="legalEntities.data.length" class="grid gap-4 p-4 lg:grid-cols-2 sm:p-6">
                <li v-for="legalEntity in legalEntities.data" :key="legalEntity.id">
                    <article class="flex h-full flex-col rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                        <header class="flex items-start gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-clinic-600 text-white">
                                <BaseIcon name="building" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h3 class="font-semibold text-slate-950 dark:text-white">{{ legalEntity.business_name }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClasses(legalEntity.status.value)">{{ legalEntity.status.label }}</span>
                                </div>
                                <p class="pt-1 font-mono text-sm text-slate-500 dark:text-slate-400">CUIT {{ legalEntity.formatted_cuit }}</p>
                            </div>
                        </header>

                        <dl class="grid gap-3 pt-5 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/60 sm:col-span-2">
                                <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Domicilio fiscal</dt>
                                <dd class="pt-1 text-sm text-slate-800 dark:text-slate-200">{{ legalEntity.fiscal_address }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/60">
                                <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Sedes totales</dt>
                                <dd class="pt-1 text-lg font-semibold text-slate-950 dark:text-white">{{ legalEntity.locations_count }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/60">
                                <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Sedes activas</dt>
                                <dd class="pt-1 text-lg font-semibold text-slate-950 dark:text-white">{{ legalEntity.active_locations_count }}</dd>
                            </div>
                        </dl>

                        <p v-if="legalEntity.permissions.archive && legalEntity.unarchived_locations_count > 0" class="pt-4 text-xs text-amber-700 dark:text-amber-300">
                            Para archivarla, primero archivá sus {{ legalEntity.unarchived_locations_count }} sedes no archivadas.
                        </p>

                        <footer class="mt-auto flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <Link
                                v-if="legalEntity.permissions.update"
                                :href="`/organizacion/razones-sociales/${legalEntity.id}/editar`"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <BaseIcon name="edit" class="size-4" /> Editar
                            </Link>
                            <button
                                v-if="legalEntity.permissions.archive"
                                type="button"
                                :disabled="activeAction === legalEntity.id || legalEntity.unarchived_locations_count > 0"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                @click="archiveLegalEntity(legalEntity)"
                            >
                                <BaseIcon name="archive" class="size-4" /> Archivar
                            </button>
                            <button
                                v-if="legalEntity.permissions.restore"
                                type="button"
                                :disabled="activeAction === legalEntity.id"
                                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-emerald-200 px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                @click="restoreLegalEntity(legalEntity)"
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
                    <h3 class="pt-4 font-semibold text-slate-950 dark:text-white">No hay razones sociales para mostrar</h3>
                    <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">Creá una entidad o ajustá los filtros.</p>
                </div>
            </div>

            <PaginationNav
                v-if="legalEntities.last_page > 1"
                :links="legalEntities.links"
                :from="legalEntities.from"
                :to="legalEntities.to"
                :total="legalEntities.total"
            />
        </section>
    </AppShell>
</template>
