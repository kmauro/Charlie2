<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';

withDefaults(
    defineProps<{
        compact?: boolean;
        inverse?: boolean;
    }>(),
    {
        compact: false,
        inverse: false,
    },
);
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="grid size-10 shrink-0 place-items-center rounded-xl bg-clinic-600 text-white shadow-lg shadow-clinic-600/20"
            :class="{ 'bg-white/15 ring-1 ring-white/20': inverse }"
        >
            <BaseIcon name="heart-pulse" class="size-6" :stroke-width="2" />
        </span>
        <span v-if="!compact" class="min-w-0">
            <span
                class="block text-base font-bold tracking-tight"
                :class="inverse ? 'text-white' : 'text-slate-950 dark:text-white'"
            >
                Clínica
            </span>
            <span
                class="block text-[11px] font-semibold tracking-[0.18em] uppercase"
                :class="inverse ? 'text-blue-100' : 'text-slate-400 dark:text-slate-500'"
            >
                Gestión integral
            </span>
        </span>
    </div>
</template>
