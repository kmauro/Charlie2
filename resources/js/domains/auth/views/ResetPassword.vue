<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Restablecer contraseña" />
    <AuthShell eyebrow="Nueva contraseña">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Restablecer contraseña</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">Elegí una contraseña nueva para recuperar el acceso a tu cuenta.</p>
        <form class="mt-8 grid gap-5" @submit.prevent="form.post('/restablecer-contrasena')">
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                Correo electrónico
                <input v-model="form.email" type="email" autocomplete="email" required class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            </label>
            <FieldError :message="form.errors.email" />
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                Nueva contraseña
                <input v-model="form.password" type="password" autocomplete="new-password" required class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            </label>
            <FieldError :message="form.errors.password" />
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                Confirmar contraseña
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            </label>
            <button type="submit" :disabled="form.processing" class="min-h-12 rounded-xl bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60 dark:bg-clinic-600">{{ form.processing ? 'Actualizando…' : 'Actualizar contraseña' }}</button>
        </form>
    </AuthShell>
</template>
