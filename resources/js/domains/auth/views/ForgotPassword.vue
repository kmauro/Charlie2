<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({ email: '' });
const page = usePage();
const status = computed(() => page.props.status as string | undefined);
</script>

<template>
    <Head title="Recuperar contraseña" />
    <AuthShell eyebrow="Recuperación de acceso">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Recuperar contraseña</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">Ingresá el correo de tu cuenta. Si está habilitada, recibirás un enlace válido por 60 minutos.</p>
        <div v-if="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ status }}</div>
        <form class="mt-8 grid gap-5" @submit.prevent="form.post('/recuperar-contrasena')">
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                Correo electrónico
                <input v-model="form.email" type="email" autocomplete="email" required class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            </label>
            <FieldError :message="form.errors.email" />
            <button type="submit" :disabled="form.processing" class="min-h-12 rounded-xl bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60 dark:bg-clinic-600">{{ form.processing ? 'Enviando…' : 'Enviar enlace' }}</button>
            <Link href="/login" class="text-center text-sm font-semibold text-clinic-700 hover:underline dark:text-clinic-300">Volver al ingreso</Link>
        </form>
    </AuthShell>
</template>
