<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ codes: string[]; finishUrl: string }>();
const form = useForm({});
</script>

<template>
    <Head title="Guardá tus códigos de recuperación" />
    <AuthShell eyebrow="Códigos de recuperación">
        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><BaseIcon name="key" class="size-6" /></div>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Guardá estos códigos</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">Cada código se puede usar una sola vez si no tenés acceso a tu aplicación autenticadora. No volverán a mostrarse.</p>
        <div class="mt-6 grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm font-semibold text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"><code v-for="code in codes" :key="code">{{ code }}</code></div>
        <form class="mt-6" @submit.prevent="form.post(finishUrl)"><button type="submit" :disabled="form.processing" class="min-h-12 w-full rounded-xl bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60 dark:bg-clinic-600">Ya los guardé, continuar</button></form>
    </AuthShell>
</template>
