<script setup lang="ts">
import type {
    EditableUser,
    PermissionOption,
    RoleOption,
} from '@/domains/users/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    endpoint: string;
    roles: RoleOption[];
    permissionOptions: PermissionOption[];
    user?: EditableUser;
}>();

interface UserFormValues {
    name: string;
    email: string;
    role_id: number | '';
    expires_in_hours: number;
    permissions: string[];
}

const initialRoleId =
    props.user?.role_id ??
    props.roles.find((role) => role.code === props.user?.role?.code)?.id ??
    '';

const form = useForm<UserFormValues>({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role_id: initialRoleId,
    expires_in_hours: 48,
    permissions: props.user?.permissions ?? [],
});

const isEditing = computed(() => props.mode === 'edit');
const selectedRoleCode = computed(
    () => props.roles.find((role) => role.id === form.role_id)?.code ?? '',
);
const availablePermissionOptions = computed(() =>
    props.permissionOptions.filter((permission) =>
        permission.allowed_roles.includes(selectedRoleCode.value),
    ),
);
const submitLabel = computed(() =>
    isEditing.value ? 'Guardar cambios' : 'Enviar invitación',
);

const submit = (): void => {
    if (isEditing.value) {
        form.put(props.endpoint, {
            preserveScroll: true,
        });

        return;
    }

    form.post(props.endpoint);
};

watch(selectedRoleCode, () => {
    const availableValues = new Set(
        availablePermissionOptions.value.map((permission) => permission.value),
    );
    form.permissions = form.permissions.filter((permission) =>
        availableValues.has(permission),
    );
});
</script>

<template>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            novalidate
            @submit.prevent="submit"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <Link
                        href="/usuarios"
                        class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-clinic-300 hover:bg-clinic-50 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-clinic-700 dark:hover:bg-clinic-400/10 dark:hover:text-clinic-300"
                        aria-label="Volver a usuarios"
                    >
                        <BaseIcon
                            name="arrow-left"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </Link>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">
                            {{ isEditing ? 'Datos de la cuenta' : 'Datos de la persona' }}
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{
                                isEditing
                                    ? 'Actualizá la identidad, el rol y las excepciones de permisos.'
                                    : 'La cuenta quedará pendiente hasta que la persona acepte la invitación.'
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-8 p-5 sm:p-6">
                <fieldset>
                    <legend class="text-sm font-semibold text-slate-950 dark:text-white">
                        Identidad y rol
                    </legend>

                    <div class="grid gap-5 pt-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label
                                for="user-name"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Nombre completo
                            </label>
                            <input
                                id="user-name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                required
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="
                                    form.errors.name ? 'user-name-error' : undefined
                                "
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                                placeholder="Nombre y apellido"
                            />
                            <FieldError
                                id="user-name-error"
                                :message="form.errors.name"
                                class="pt-2"
                            />
                        </div>

                        <div>
                            <label
                                for="user-email"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Correo electrónico
                            </label>
                            <input
                                id="user-email"
                                v-model="form.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                required
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="
                                    form.errors.email
                                        ? 'user-email-error'
                                        : 'user-email-help'
                                "
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                                placeholder="nombre@clinica.com.ar"
                            />
                            <p
                                v-if="!form.errors.email && !isEditing"
                                id="user-email-help"
                                class="pt-2 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Se generará un enlace firmado, personal y de uso único.
                            </p>
                            <FieldError
                                id="user-email-error"
                                :message="form.errors.email"
                                class="pt-2"
                            />
                        </div>

                        <div>
                            <label
                                for="user-role"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Rol
                            </label>
                            <select
                                id="user-role"
                                v-model.number="form.role_id"
                                required
                                :aria-invalid="Boolean(form.errors.role_id)"
                                :aria-describedby="
                                    form.errors.role_id ? 'user-role-error' : undefined
                                "
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 aria-invalid:border-rose-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                            >
                                <option value="" disabled>Seleccionar un rol</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                            <FieldError
                                id="user-role-error"
                                :message="form.errors.role_id"
                                class="pt-2"
                            />
                        </div>

                        <div v-if="!isEditing" class="sm:col-span-2">
                            <label
                                for="invitation-expiration"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                Vigencia del enlace
                            </label>
                            <select
                                id="invitation-expiration"
                                v-model.number="form.expires_in_hours"
                                :aria-invalid="
                                    Boolean(form.errors.expires_in_hours)
                                "
                                :aria-describedby="
                                    form.errors.expires_in_hours
                                        ? 'invitation-expiration-error'
                                        : undefined
                                "
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 aria-invalid:border-rose-500 sm:max-w-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                            >
                                <option :value="24">24 horas</option>
                                <option :value="48">48 horas</option>
                                <option :value="72">72 horas</option>
                                <option :value="168">7 días</option>
                            </select>
                            <FieldError
                                id="invitation-expiration-error"
                                :message="form.errors.expires_in_hours"
                                class="pt-2"
                            />
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-950 dark:text-white">
                        Permisos individuales
                    </legend>
                    <p class="pt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        El rol define el máximo permitido. Marcá las capacidades
                        que esta cuenta podrá utilizar.
                    </p>

                    <div class="grid gap-3 pt-4 sm:grid-cols-2">
                        <label
                            v-for="permission in availablePermissionOptions"
                            :key="permission.value"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-clinic-300 hover:bg-clinic-50/50 dark:border-slate-700 dark:hover:border-clinic-700 dark:hover:bg-clinic-400/5"
                        >
                            <input
                                v-model="form.permissions"
                                type="checkbox"
                                :value="permission.value"
                                class="mt-0.5 size-4 rounded border-slate-300 text-clinic-600 accent-clinic-600 focus:ring-2 focus:ring-clinic-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-950 dark:focus:ring-offset-slate-900"
                            />
                            <span>
                                <span
                                    class="block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ permission.label }}
                                </span>
                                <span
                                    v-if="permission.description"
                                    class="block pt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ permission.description }}
                                </span>
                            </span>
                        </label>
                    </div>
                    <p
                        v-if="availablePermissionOptions.length === 0"
                        class="pt-4 text-sm text-slate-500 dark:text-slate-400"
                    >
                        El rol seleccionado no admite permisos individuales.
                    </p>
                    <FieldError :message="form.errors.permissions" class="pt-3" />
                </fieldset>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 p-5 sm:flex-row sm:items-center sm:justify-end sm:p-6 dark:border-slate-800 dark:bg-slate-950/50"
            >
                <Link
                    href="/usuarios"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-clinic-700 disabled:cursor-wait disabled:opacity-60 dark:bg-clinic-600 dark:hover:bg-clinic-500"
                >
                    <BaseIcon
                        :name="isEditing ? 'save' : 'mail'"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{
                        form.processing
                            ? isEditing
                                ? 'Guardando…'
                                : 'Enviando…'
                            : submitLabel
                    }}
                </button>
            </div>
        </form>

        <aside class="grid content-start gap-4" aria-label="Información de seguridad">
            <section
                class="rounded-2xl border border-clinic-200 bg-clinic-50 p-5 dark:border-clinic-900 dark:bg-clinic-950/40"
            >
                <span
                    class="grid size-10 place-items-center rounded-xl bg-clinic-600 text-white"
                    aria-hidden="true"
                >
                    <BaseIcon name="shield-check" class="size-5" />
                </span>
                <h2 class="pt-4 font-semibold text-clinic-950 dark:text-clinic-100">
                    {{ isEditing ? 'Cambio auditable' : 'Alta controlada' }}
                </h2>
                <ul
                    class="grid gap-2 pt-3 text-sm leading-6 text-clinic-900 dark:text-clinic-200"
                >
                    <li class="flex gap-2">
                        <span aria-hidden="true">•</span>
                        {{
                            isEditing
                                ? 'Los cambios de rol y permisos quedan registrados.'
                                : 'La invitación no puede reutilizarse.'
                        }}
                    </li>
                    <li class="flex gap-2">
                        <span aria-hidden="true">•</span>
                        Los permisos individuales nunca amplían el máximo del rol.
                    </li>
                    <li class="flex gap-2">
                        <span aria-hidden="true">•</span>
                        El backend valida cada operación aunque el control no se
                        muestre en la interfaz.
                    </li>
                </ul>
            </section>
        </aside>
    </div>
</template>
