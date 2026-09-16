<script setup lang="ts">
import AuthShell from '@/domains/auth/layouts/AuthShell.vue';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import type { RoleReference } from '@/shared/types/inertia';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface InvitationDetails {
    email: string;
    name?: string | null;
    role?: RoleReference | string | null;
    expires_at?: string | null;
}

const props = defineProps<{
    invitation: InvitationDetails | null;
    acceptUrl: string | null;
}>();

const showPassword = ref(false);
const form = useForm({
    name: props.invitation?.name ?? '',
    password: '',
    password_confirmation: '',
});

const passwordsMatch = computed(
    () =>
        form.password_confirmation.length === 0 ||
        form.password === form.password_confirmation,
);

const invitationRole = computed(() => {
    const role = props.invitation?.role;

    if (!role) {
        return '';
    }

    return typeof role === 'string' ? role : role.name;
});

const submit = (): void => {
    if (!props.invitation || !props.acceptUrl || !passwordsMatch.value) {
        return;
    }

    form.post(props.acceptUrl);
};
</script>

<template>
    <Head title="Crear cuenta" />

    <AuthShell :eyebrow="invitation ? 'Invitación verificada' : 'Acceso privado'">
        <div v-if="!invitation" class="text-center">
            <div
                class="mx-auto grid size-16 place-items-center rounded-2xl bg-clinic-50 text-clinic-700 ring-1 ring-clinic-100 dark:bg-clinic-500/10 dark:text-clinic-300 dark:ring-clinic-400/15"
            >
                <BaseIcon name="mail" class="size-8" aria-hidden="true" />
            </div>

            <div
                class="mx-auto mt-6 w-fit rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20"
            >
                Invitación requerida
            </div>

            <h1
                class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-4xl dark:text-white"
            >
                El registro es privado
            </h1>
            <p
                class="mx-auto mt-4 max-w-sm text-sm leading-6 text-slate-600 dark:text-slate-400"
            >
                Las cuentas se activan únicamente desde una invitación vigente
                emitida por un administrador autorizado.
            </p>

            <div
                role="status"
                class="mt-8 rounded-2xl border border-slate-200 bg-white/80 p-5 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900/70"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <BaseIcon name="info" class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
                            ¿Cómo continúo?
                        </h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600 dark:text-slate-400">
                            Solicitá una invitación y abrí el enlace personal
                            recibido. Si ya venció, pedí que lo generen nuevamente.
                        </p>
                    </div>
                </div>
            </div>

            <Link
                href="/login"
                class="mt-7 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-clinic-700/20 transition hover:bg-clinic-800 dark:bg-clinic-600 dark:hover:bg-clinic-500"
            >
                <BaseIcon name="arrow-left" class="size-4" aria-hidden="true" />
                Volver al ingreso
            </Link>
        </div>

        <div v-else>
            <div
                class="mb-7 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/15"
            >
                <BaseIcon name="check" class="size-4" aria-hidden="true" />
                Invitación válida
            </div>

            <h1
                class="text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-4xl dark:text-white"
            >
                Activá tu cuenta
            </h1>
            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
                Confirmá tu nombre y definí una contraseña segura para ingresar.
            </p>

            <dl
                class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm dark:border-slate-800 dark:bg-slate-900/60"
            >
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Correo invitado
                    </dt>
                    <dd class="truncate pt-1 font-semibold text-slate-900 dark:text-white">
                        {{ invitation.email }}
                    </dd>
                </div>
                <div v-if="invitationRole">
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Rol asignado
                    </dt>
                    <dd class="pt-1 font-semibold text-slate-900 dark:text-white">
                        {{ invitationRole }}
                    </dd>
                </div>
            </dl>

            <form class="mt-7 grid gap-5" novalidate @submit.prevent="submit">
                <div class="grid gap-2">
                    <label
                        for="registration-name"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Nombre completo
                    </label>
                    <input
                        id="registration-name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        :aria-invalid="Boolean(form.errors.name)"
                        :aria-describedby="
                            form.errors.name ? 'registration-name-error' : undefined
                        "
                        class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-600 dark:hover:border-slate-600 dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                        placeholder="Nombre y apellido"
                    />
                    <FieldError
                        id="registration-name-error"
                        :message="form.errors.name"
                    />
                </div>

                <div class="grid gap-2">
                    <label
                        for="registration-password"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Contraseña
                    </label>
                    <div class="relative">
                        <input
                            id="registration-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            :aria-invalid="Boolean(form.errors.password)"
                            :aria-describedby="
                                form.errors.password
                                    ? 'registration-password-error'
                                    : 'registration-password-help'
                            "
                            class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-600 dark:hover:border-slate-600 dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                            placeholder="Creá una contraseña"
                        />
                        <button
                            type="button"
                            class="absolute top-1/2 right-2.5 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            :aria-label="
                                showPassword
                                    ? 'Ocultar contraseñas'
                                    : 'Mostrar contraseñas'
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
                    <p
                        v-if="!form.errors.password"
                        id="registration-password-help"
                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Usá al menos 12 caracteres, con mayúsculas, minúsculas,
                        números y símbolos.
                    </p>
                    <FieldError
                        id="registration-password-error"
                        :message="form.errors.password"
                    />
                </div>

                <div class="grid gap-2">
                    <label
                        for="registration-password-confirmation"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Confirmar contraseña
                    </label>
                    <input
                        id="registration-password-confirmation"
                        v-model="form.password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        required
                        :aria-invalid="
                            Boolean(form.errors.password_confirmation) ||
                            !passwordsMatch
                        "
                        :aria-describedby="
                            !passwordsMatch
                                ? 'password-match-error'
                                : form.errors.password_confirmation
                                  ? 'registration-password-confirmation-error'
                                  : undefined
                        "
                        class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-clinic-500 focus:ring-4 focus:ring-clinic-500/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-600 dark:hover:border-slate-600 dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                        placeholder="Repetí la contraseña"
                    />
                    <FieldError
                        v-if="!passwordsMatch"
                        id="password-match-error"
                        message="Las contraseñas no coinciden."
                    />
                    <FieldError
                        v-else
                        id="registration-password-confirmation-error"
                        :message="form.errors.password_confirmation"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !passwordsMatch || !acceptUrl"
                    class="group flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-clinic-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-clinic-700/20 transition hover:bg-clinic-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-clinic-600 dark:shadow-clinic-950/30 dark:hover:bg-clinic-500"
                >
                    <BaseIcon
                        v-if="form.processing"
                        name="loader"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ form.processing ? 'Activando cuenta…' : 'Activar mi cuenta' }}
                    <BaseIcon
                        v-if="!form.processing"
                        name="arrow-right"
                        class="size-4 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
                ¿Ya activaste tu cuenta?
                <Link
                    href="/login"
                    class="font-semibold text-clinic-700 underline-offset-4 hover:underline dark:text-clinic-300"
                >
                    Ingresá al sistema
                </Link>
            </p>
        </div>
    </AuthShell>
</template>
