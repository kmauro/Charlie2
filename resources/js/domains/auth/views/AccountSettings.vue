<script setup lang="ts">
import AppShell from '@/shared/layouts/AppShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ account: { name: string; email: string }; mfaEnabled: boolean; showMfaPrompt: boolean }>();
const form = useForm({ name: props.account.name, email: props.account.email });
const skip = useForm({});
</script>

<template>
    <Head title="Ajustes de cuenta" />
    <AppShell title="Ajustes de cuenta" eyebrow="Mi cuenta" description="Administrá tus datos personales y la seguridad de acceso.">
        <section v-if="showMfaPrompt && !mfaEnabled" class="mb-6 rounded-2xl border border-clinic-200 bg-clinic-50 p-5 dark:border-clinic-500/30 dark:bg-clinic-500/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-3"><BaseIcon name="shield-check" class="mt-0.5 size-5 text-clinic-700 dark:text-clinic-300" /><div><h2 class="font-semibold text-slate-900 dark:text-white">Protegé tu cuenta con 2FA</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Es opcional y podés configurarlo ahora o más tarde desde esta pantalla.</p></div></div>
                <div class="flex gap-2"><Link href="/mi-cuenta/2fa/configurar" class="rounded-xl bg-clinic-700 px-4 py-2 text-sm font-semibold text-white hover:bg-clinic-800">Configurar ahora</Link><button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800" @click="skip.post('/configurar-seguridad/omitir')">Ahora no</button></div>
            </div>
        </section>
        <div class="grid max-w-3xl gap-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Datos básicos</h2>
                <form class="mt-5 grid gap-4" @submit.prevent="form.put('/mi-cuenta')">
                    <label class="grid gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">Nombre<input v-model="form.name" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-950" /></label><FieldError :message="form.errors.name" />
                    <label class="grid gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">Correo electrónico<input v-model="form.email" type="email" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-950" /></label><FieldError :message="form.errors.email" />
                    <button type="submit" :disabled="form.processing" class="min-h-11 justify-self-start rounded-xl bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60">Guardar cambios</button>
                </form>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Verificación en dos pasos</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ mfaEnabled ? 'Tu cuenta está protegida con una aplicación autenticadora.' : 'Agregá una capa adicional de seguridad a tu acceso.' }}</p></div><BaseIcon :name="mfaEnabled ? 'shield-check' : 'shield'" class="size-6 text-clinic-600" /></div><Link v-if="!mfaEnabled" href="/mi-cuenta/2fa/configurar" class="mt-5 inline-flex rounded-xl border border-clinic-300 px-4 py-2 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-500/40 dark:text-clinic-300">Configurar 2FA</Link></section>
        </div>
    </AppShell>
</template>
