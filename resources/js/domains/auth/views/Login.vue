<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const showPassword = ref(false);
const page = usePage();
const status = computed(() => page.props.status as string | undefined);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/login', {
        preserveScroll: true,
        onError: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Ingresar" />

    <AuthShell eyebrow="Acceso al sistema">
        <div>
            <div
                class="mb-7 inline-flex items-center gap-2 rounded-full bg-clinic-50 px-3 py-1.5 text-xs font-semibold text-clinic-700 ring-1 ring-clinic-100 dark:bg-clinic-500/10 dark:text-clinic-300 dark:ring-clinic-400/15"
            >
                <BaseIcon name="lock-keyhole" class="size-4" aria-hidden="true" />
                Acceso seguro
            </div>

            <h1
                class="text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-4xl dark:text-white"
            >
                Bienvenido de nuevo
            </h1>
            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
                Ingresá con la cuenta asignada para acceder a tu espacio de trabajo.
            </p>
        </div>

        <div
            v-if="status"
            class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ status }}
        </div>

        <form class="mt-8 grid gap-5" novalidate @submit.prevent="submit">
            <div class="grid gap-2">
                <label
                    for="email"
                    class="text-sm font-medium text-slate-800 dark:text-slate-200"
                >
                    Correo electrónico
                </label>
                <div class="relative">
                    <BaseIcon
                        name="mail"
                        class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        id="email"
                        v-model="form.email"
                        name="email"
                        type="email"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                        placeholder="nombre@clinica.com.ar"
                        :aria-invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'login-email-error' : undefined"
                        class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-11 py-3 text-sm text-slate-950 shadow-sm shadow-slate-950/[0.02] outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:shadow-none dark:placeholder:text-slate-600 dark:hover:border-slate-600 dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                    />
                </div>
                <FieldError
                    id="login-email-error"
                    :message="form.errors.email"
                />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <label for="password" class="text-sm font-medium text-slate-800 dark:text-slate-200">Contraseña</label>
                    <Link href="/recuperar-contrasena" class="text-xs font-semibold text-clinic-700 hover:underline dark:text-clinic-300">¿La olvidaste?</Link>
                </div>
                <div class="relative">
                    <BaseIcon
                        name="lock-keyhole"
                        class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        placeholder="Ingresá tu contraseña"
                        :aria-invalid="Boolean(form.errors.password)"
                        :aria-describedby="
                            form.errors.password ? 'login-password-error' : undefined
                        "
                        class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-11 py-3 pr-12 text-sm text-slate-950 shadow-sm shadow-slate-950/[0.02] outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:shadow-none dark:placeholder:text-slate-600 dark:hover:border-slate-600 dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                    />
                    <button
                        type="button"
                        class="absolute top-1/2 right-2.5 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-clinic-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        :aria-label="
                            showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                        "
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <BaseIcon
                            :name="showPassword ? 'eye-off' : 'eye'"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <FieldError
                    id="login-password-error"
                    :message="form.errors.password"
                />
            </div>

            <label
                class="flex cursor-pointer items-start gap-3 text-sm text-slate-600 dark:text-slate-400"
            >
                <input
                    v-model="form.remember"
                    name="remember"
                    type="checkbox"
                    class="mt-0.5 size-4 rounded border-slate-300 text-clinic-700 accent-clinic-700 focus:ring-2 focus:ring-clinic-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:focus:ring-offset-slate-950"
                />
                <span>
                    Mantener la sesión iniciada
                    <span class="mt-0.5 block text-xs text-slate-500">
                        No recomendado en equipos compartidos.
                    </span>
                </span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="group flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-clinic-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-clinic-700/20 transition hover:bg-clinic-800 disabled:cursor-wait disabled:opacity-70 dark:bg-clinic-600 dark:shadow-clinic-950/30 dark:hover:bg-clinic-500"
            >
                <BaseIcon
                    v-if="form.processing"
                    name="loader"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                {{ form.processing ? 'Ingresando…' : 'Ingresar al sistema' }}
                <BaseIcon
                    v-if="!form.processing"
                    name="arrow-right"
                    class="size-4 transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </button>
        </form>

        <div
            class="mt-7 rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
        >
            <div class="flex items-start gap-3">
                <span
                    class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                >
                    <BaseIcon name="info" class="size-4" aria-hidden="true" />
                </span>
                <p class="leading-5">
                    ¿Recibiste una invitación?
                    <Link
                        href="/register"
                        class="font-semibold text-clinic-700 underline-offset-4 hover:underline dark:text-clinic-300"
                    >
                        Abrí el enlace enviado
                    </Link>
                    por el superadministrador para activar la cuenta.
                </p>
            </div>
        </div>
    </AuthShell>
</template>
