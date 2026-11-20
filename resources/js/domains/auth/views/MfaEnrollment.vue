<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ secret: string; otpauthUrl: string; qrCodeDataUri: string; submitUrl: string }>();
const form = useForm({ code: '' });
</script>

<template>
    <Head title="Configurar verificación en dos pasos" />
    <AuthShell eyebrow="Protección de la cuenta">
        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-clinic-50 text-clinic-700 dark:bg-clinic-500/10 dark:text-clinic-300"><BaseIcon name="shield-check" class="size-6" /></div>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Configurá la verificación en dos pasos</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">Agregá esta cuenta en Google Authenticator, Microsoft Authenticator u otra aplicación compatible con TOTP.</p>
        <div class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="justify-self-center rounded-xl bg-white p-3 shadow-sm"><img :src="qrCodeDataUri" width="260" height="260" alt="Código QR para configurar la aplicación autenticadora" /></div>
            <p class="text-center text-sm text-slate-600 dark:text-slate-400">Escaneá el código QR con tu aplicación autenticadora.</p>
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Clave de configuración</p>
            <code class="break-all text-sm font-semibold text-slate-800 dark:text-slate-200">{{ secret }}</code>
            <p class="text-xs text-slate-500">También podés usar esta URL en una aplicación que acepte enlaces otpauth.</p>
            <code class="break-all text-xs text-slate-500">{{ otpauthUrl }}</code>
        </div>
        <form class="mt-6 grid gap-4" @submit.prevent="form.post(submitUrl)">
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">Código de seis dígitos<input v-model="form.code" name="code" autocomplete="one-time-code" required autofocus class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-center font-mono text-lg tracking-[0.3em] outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" /></label>
            <FieldError :message="form.errors.code" />
            <button type="submit" :disabled="form.processing" class="min-h-12 rounded-xl bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60 dark:bg-clinic-600">{{ form.processing ? 'Verificando…' : 'Activar protección' }}</button>
        </form>
    </AuthShell>
</template>
