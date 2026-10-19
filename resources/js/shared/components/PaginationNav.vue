<script setup lang="ts">
import type { PaginationLink } from '@/shared/types/inertia';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        links: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
    }>(),
    {
        from: null,
        to: null,
        total: 0,
    },
);

const readableLabel = (label: string): string => {
    if (label.includes('previous')) {
        return 'Anterior';
    }

    if (label.includes('next')) {
        return 'Siguiente';
    }

    return label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»');
};
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-800 dark:bg-slate-950/50"
        aria-label="Paginación"
    >
        <p class="text-xs text-slate-500 dark:text-slate-400">
            <template v-if="from !== null && to !== null">
                Mostrando {{ from }}–{{ to }} de {{ total }}
            </template>
            <template v-else>{{ total }} registros</template>
        </p>

        <div class="flex flex-wrap items-center gap-1">
            <template v-for="link in links" :key="`${link.label}-${link.url}`">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-xs font-semibold transition"
                    :class="
                        link.active
                            ? 'bg-clinic-600 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-clinic-300 hover:text-clinic-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-clinic-700 dark:hover:text-clinic-300'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ readableLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="inline-flex min-h-10 min-w-10 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-3 text-xs font-semibold text-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600"
                    aria-disabled="true"
                >
                    {{ readableLabel(link.label) }}
                </span>
            </template>
        </div>
    </nav>
</template>
