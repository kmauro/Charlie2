<script setup lang="ts">
import type {
    RoleOption,
    UserFilters,
    UserListItem,
    UserSummaries,
    UsersPaginator,
} from '@/domains/users/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import PaginationNav from '@/shared/components/PaginationNav.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    users: UsersPaginator;
    filters: UserFilters;
    roles: RoleOption[];
    summaries: UserSummaries;
}>();

const page = usePage();
const search = ref(props.filters.search ?? '');
const selectedRole = ref(props.filters.role ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const activeAction = ref<number | null>(null);

const summaryCards = computed(() => [
    {
        label: 'Usuarios',
        value: props.summaries.total,
        detail: 'cuentas registradas',
        icon: 'users',
    },
    {
        label: 'Activos',
        value: props.summaries.active,
        detail: 'con acceso habilitado',
        icon: 'user-check',
    },
    {
        label: 'Pendientes',
        value: props.summaries.pending,
        detail: 'sin aceptar invitación',
        icon: 'mail',
    },
    {
        label: 'Suspendidos',
        value: props.summaries.suspended,
        detail: 'sin acceso al sistema',
        icon: 'archive',
    },
    {
        label: 'Permisos propios',
        value: props.summaries.custom_permissions,
        detail: 'con excepciones individuales',
        icon: 'shield-check',
    },
]);

const hasFilters = computed(
    () =>
        search.value.trim() !== '' ||
        selectedRole.value !== '' ||
        selectedStatus.value !== '',
);

const normalizedStatus = (user: UserListItem): string =>
    user.status.value.toLocaleLowerCase('es-AR');

const isSuspended = (user: UserListItem): boolean =>
    ['suspended', 'suspendido', 'archived', 'archivado'].includes(
        normalizedStatus(user),
    );

const isPending = (user: UserListItem): boolean =>
    [
        'pending_invitation',
        'pending',
        'pendiente',
        'invited',
        'invitation_pending',
    ].includes(
        normalizedStatus(user),
    );

const isCurrentUser = (user: UserListItem): boolean =>
    page.props.auth.user?.id === user.id;

const isProfessional = (user: UserListItem): boolean =>
    user.role?.code === 'professional';

const initials = (name: string): string =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toLocaleUpperCase('es-AR');

const formatDate = (value: string | null, fallback = 'Todavía no ingresó'): string => {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const statusClasses = (status: string): string => {
    const normalized = status.toLocaleLowerCase('es-AR');

    if (['active', 'activo'].includes(normalized)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20';
    }

    if (
        [
            'pending_invitation',
            'pending',
            'pendiente',
            'invited',
            'invitation_pending',
        ].includes(normalized)
    ) {
        return 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/20';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-500/30';
};

const applyFilters = (): void => {
    router.get(
        '/usuarios',
        {
            search: search.value.trim() || undefined,
            role: selectedRole.value || undefined,
            status: selectedStatus.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = (): void => {
    search.value = '';
    selectedRole.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const suspendUser = (user: UserListItem): void => {
    if (
        !window.confirm(
            `¿Suspender el acceso de ${user.name}? Podrás restaurarlo más adelante.`,
        )
    ) {
        return;
    }

    activeAction.value = user.id;
    router.delete(`/usuarios/${user.id}`, {
        preserveScroll: true,
        onFinish: () => {
            activeAction.value = null;
        },
    });
};

const restoreUser = (user: UserListItem): void => {
    activeAction.value = user.id;
    router.patch(
        `/usuarios/${user.id}/restaurar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
};

const resendInvitation = (user: UserListItem): void => {
    activeAction.value = user.id;
    router.post(
        `/usuarios/${user.id}/reinvitacion`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Usuarios" />

    <AppShell
        title="Usuarios"
        eyebrow="Administración y permisos"
        description="Gestioná el acceso del equipo, su rol y las excepciones individuales de permisos."
        action-href="/usuarios/nuevo"
        action-label="Invitar usuario"
        action-icon="plus"
    >
        <section aria-labelledby="users-summary-title">
            <h2 id="users-summary-title" class="sr-only">Resumen de usuarios</h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="summary in summaryCards"
                    :key="summary.label"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                {{ summary.label }}
                            </p>
                            <p
                                class="pt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ summary.value }}
                            </p>
                            <p class="pt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ summary.detail }}
                            </p>
                        </div>

                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-clinic-50 text-clinic-700 dark:bg-clinic-400/10 dark:text-clinic-300"
                        >
                            <BaseIcon
                                :name="summary.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section
            aria-labelledby="user-list-title"
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2
                            id="user-list-title"
                            class="text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Equipo con acceso
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ users.total }}
                            {{ users.total === 1 ? 'cuenta registrada' : 'cuentas registradas' }}
                        </p>
                    </div>

                    <form
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,20rem)_11rem_11rem_auto]"
                        role="search"
                        @submit.prevent="applyFilters"
                    >
                        <div class="sm:col-span-2 xl:col-span-1">
                            <label for="user-search" class="sr-only">
                                Buscar usuarios
                            </label>
                            <div class="relative">
                                <BaseIcon
                                    name="search"
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                    aria-hidden="true"
                                />
                                <input
                                    id="user-search"
                                    v-model="search"
                                    type="search"
                                    placeholder="Nombre o correo"
                                    class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pr-3 pl-10 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="role-filter" class="sr-only">Filtrar por rol</label>
                            <select
                                id="role-filter"
                                v-model="selectedRole"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                            >
                                <option value="">Todos los roles</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.code"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="status-filter" class="sr-only">
                                Filtrar por estado
                            </label>
                            <select
                                id="status-filter"
                                v-model="selectedStatus"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400 dark:focus:ring-clinic-400/10"
                            >
                                <option value="">Todos los estados</option>
                                <option value="active">Activos</option>
                                <option value="pending_invitation">Pendientes</option>
                                <option value="suspended">Suspendidos</option>
                            </select>
                        </div>

                        <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                            <button
                                type="submit"
                                class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                <BaseIcon name="filter" class="size-4" aria-hidden="true" />
                                Filtrar
                            </button>
                            <button
                                v-if="hasFilters"
                                type="button"
                                class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                                aria-label="Limpiar filtros"
                                @click="clearFilters"
                            >
                                <BaseIcon
                                    name="rotate-ccw"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <ul v-if="users.data.length > 0" class="divide-y divide-slate-200 dark:divide-slate-800">
                <li v-for="user in users.data" :key="user.id" class="p-5 sm:p-6">
                    <article
                        class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(17rem,0.8fr)_auto] xl:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-full bg-clinic-100 text-sm font-semibold text-clinic-800 dark:bg-clinic-400/15 dark:text-clinic-200"
                                aria-hidden="true"
                            >
                                {{ initials(user.name) }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <h3
                                        class="truncate font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ user.name }}
                                    </h3>
                                    <span
                                        v-if="isCurrentUser(user)"
                                        class="shrink-0 rounded-full bg-clinic-50 px-2 py-0.5 text-[10px] font-semibold text-clinic-700 dark:bg-clinic-400/10 dark:text-clinic-300"
                                    >
                                        Tu cuenta
                                    </span>
                                </div>
                                <p
                                    class="truncate pt-0.5 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt
                                    class="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Rol
                                </dt>
                                <dd class="pt-1 font-medium text-slate-800 dark:text-slate-200">
                                    {{ user.role?.name ?? 'Sin rol' }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Permisos propios
                                </dt>
                                <dd class="pt-1 font-medium text-slate-800 dark:text-slate-200">
                                    {{ user.permissions.length }}
                                </dd>
                            </div>
                            <div class="col-span-2">
                                <dt
                                    class="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ isPending(user) ? 'Vencimiento' : 'Último acceso' }}
                                </dt>
                                <dd class="pt-1 text-slate-700 dark:text-slate-300">
                                    {{
                                        isPending(user)
                                            ? formatDate(
                                                  user.invitation_expires_at,
                                                  'Sin vencimiento informado',
                                              )
                                            : formatDate(user.last_access_at)
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-col gap-3 xl:items-end">
                            <span
                                class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusClasses(user.status.value)"
                            >
                                {{ user.status.label }}
                            </span>

                            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                                <Link
                                    :href="`/usuarios/${user.id}/editar`"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:border-clinic-300 hover:text-clinic-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-clinic-700 dark:hover:text-clinic-300"
                                >
                                    <BaseIcon name="edit" class="size-4" aria-hidden="true" />
                                    Editar
                                </Link>

                                <button
                                    v-if="isPending(user)"
                                    type="button"
                                    :disabled="activeAction === user.id"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 disabled:cursor-wait disabled:opacity-60 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/70"
                                    @click="resendInvitation(user)"
                                >
                                    <BaseIcon name="mail" class="size-4" aria-hidden="true" />
                                    Reinvitar
                                </button>

                                <button
                                    v-else-if="
                                        isSuspended(user) && !isProfessional(user)
                                    "
                                    type="button"
                                    :disabled="activeAction === user.id"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 disabled:cursor-wait disabled:opacity-60 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-950/70"
                                    @click="restoreUser(user)"
                                >
                                    <BaseIcon
                                        name="rotate-ccw"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Restaurar
                                </button>

                                <button
                                    v-else-if="
                                        !isCurrentUser(user) &&
                                        !isProfessional(user)
                                    "
                                    type="button"
                                    :disabled="activeAction === user.id"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-wait disabled:opacity-60 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/70"
                                    @click="suspendUser(user)"
                                >
                                    <BaseIcon
                                        name="archive"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Suspender
                                </button>
                            </div>
                        </div>
                    </article>
                </li>
            </ul>

            <div v-else class="grid min-h-64 place-items-center p-8 text-center">
                <div>
                    <span
                        class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <BaseIcon name="search" class="size-5" aria-hidden="true" />
                    </span>
                    <h3 class="pt-4 font-semibold text-slate-950 dark:text-white">
                        No encontramos usuarios
                    </h3>
                    <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            hasFilters
                                ? 'Probá modificando o limpiando los filtros.'
                                : 'Invitá a la primera persona del equipo.'
                        }}
                    </p>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:text-clinic-300 dark:hover:bg-clinic-400/10"
                        @click="clearFilters"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <PaginationNav
                :links="users.links"
                :from="users.from"
                :to="users.to"
                :total="users.total"
            />
        </section>
    </AppShell>
</template>
