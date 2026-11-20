<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ email: string }>();
const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '', trusted_device: false });

const submit = (): void => {
    form.post(recovery.value ? '/verificacion-mfa/recuperacion' : '/verificacion-mfa', {
        preserveScroll: true,
        onError: () => form.reset(recovery.value ? 'recovery_code' : 'code'),
    });
};
</script>

<template>
    <Head title="Verificación en dos pasos" />
    <AuthShell eyebrow="Verificación de seguridad">
        <div class="inline-flex size-11 items-center justify-center rounded-xl bg-clinic-50 text-clinic-700 dark:bg-clinic-500/10 dark:text-clinic-300"><BaseIcon name="shield-check" class="size-6" /></div>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Verificá tu identidad</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">Ingresá el código de tu aplicación autenticadora para continuar con {{ email }}.</p>
        <form class="mt-8 grid gap-5" @submit.prevent="submit">
            <label class="grid gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                {{ recovery ? 'Código de recuperación' : 'Código de seis dígitos' }}
                <input v-if="recovery" v-model="form.recovery_code" name="recovery_code" autocomplete="one-time-code" required autofocus class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-center font-mono text-lg tracking-[0.3em] text-slate-950 outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                <input v-else v-model="form.code" name="code" autocomplete="one-time-code" required autofocus class="min-h-12 rounded-xl border border-slate-300 bg-white px-4 text-center font-mono text-lg tracking-[0.3em] text-slate-950 outline-none focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            </label>
            <FieldError :message="recovery ? form.errors.recovery_code : form.errors.code" />
            <label v-if="!recovery" class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-400"><input v-model="form.trusted_device" type="checkbox" class="mt-0.5 size-4 rounded border-slate-300 accent-clinic-700" /><span><span class="font-medium text-slate-800 dark:text-slate-200">Confiar en este dispositivo</span><span class="mt-0.5 block text-xs">No volveremos a solicitar el código durante 30 días. No lo uses en equipos compartidos.</span></span></label>
            <button type="submit" :disabled="form.processing" class="min-h-12 rounded-xl bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-800 disabled:opacity-60 dark:bg-clinic-600">{{ form.processing ? 'Verificando…' : 'Continuar' }}</button>
        </form>
        <button type="button" class="mt-5 text-sm font-semibold text-clinic-700 hover:underline dark:text-clinic-300" @click="recovery = !recovery; form.clearErrors(); form.reset()">{{ recovery ? 'Usar aplicación autenticadora' : 'Usar un código de recuperación' }}</button>
    </AuthShell>
</template>
