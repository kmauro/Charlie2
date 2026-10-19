<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';
import BrandMark from '@/shared/components/BrandMark.vue';
import FlashMessage from '@/shared/components/FlashMessage.vue';
import { useTheme } from '@/shared/composables/useTheme';
import type { AppFlashData } from '@/shared/types/inertia';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        eyebrow?: string;
        description?: string;
        actionHref?: string;
        actionLabel?: string;
        actionIcon?: string;
    }>(),
    {
        eyebrow: 'Panel de gestión',
        description: '',
        actionHref: '',
        actionLabel: '',
        actionIcon: 'plus',
    },
);

const page = usePage();
const mobileNavigationOpen = ref(false);
const contextMenuOpen = ref(false);
const desktopNavigationVisible = ref(false);
let desktopNavigationMediaQuery: MediaQueryList | null = null;
const { isDark, toggleTheme } = useTheme();

const authUser = computed(() => page.props.auth.user);
const appName = computed(() => page.props.appName || 'Clínica');
const accountName = computed(() => authUser.value?.name ?? 'Cuenta');
const accountEmail = computed(() => authUser.value?.email ?? '');
const accountRole = computed(() => {
    const role = authUser.value?.role;

    if (!role) {
        return 'Usuario';
    }

    return typeof role === 'string' ? role : role.name;
});
const accountRoleCode = computed(() => {
    const role = authUser.value?.role;

    return typeof role === 'object' && role !== null ? role.code : '';
});
const organizationContext = computed(() => page.props.organizationContext);
const locationOptions = computed(() => organizationContext.value.entities.flatMap((entity) =>
    entity.locations.map((location) => ({
        ...location,
        legalEntityId: entity.id,
        legalEntityName: entity.name,
    })),
));
const selectedLocationId = ref<number | null>(
    organizationContext.value.selectedLocationId,
);
const selectedLocation = computed(() => locationOptions.value.find(
    (location) => location.id === selectedLocationId.value,
));
const selectedLegalEntityName = computed(() =>
    selectedLocation.value?.legalEntityName ?? 'Sin razón social asignada',
);
const selectedLocationName = computed(() =>
    selectedLocation.value?.name ?? 'Sin sede asignada',
);
const organizationSelectorLocked = computed(() =>
    organizationContext.value.isLocked || locationOptions.value.length === 0,
);
const organizationSelector = ref<HTMLElement>();
const organizationSelectorSearchInput = ref<HTMLInputElement>();
const organizationSelectorOpen = ref(false);
const organizationSelectorQuery = ref('');
const normalizeSearch = (value: string): string => value
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('es-AR')
    .trim();
const filteredOrganizationEntities = computed(() => {
    const query = normalizeSearch(organizationSelectorQuery.value);

    if (!query) {
        return organizationContext.value.entities;
    }

    return organizationContext.value.entities
        .map((entity) => ({
            ...entity,
            locations: entity.locations.filter((location) =>
                normalizeSearch(`${entity.name} ${location.name}`).includes(query),
            ),
        }))
        .filter((entity) => entity.locations.length > 0);
});
const openOrganizationSelector = async (): Promise<void> => {
    if (organizationSelectorLocked.value) {
        return;
    }

    organizationSelectorOpen.value = true;
    await nextTick();
    organizationSelectorSearchInput.value?.focus();
};
const closeOrganizationSelector = (): void => {
    organizationSelectorOpen.value = false;
    organizationSelectorQuery.value = '';
};
const toggleOrganizationSelector = (): void => {
    if (organizationSelectorOpen.value) {
        closeOrganizationSelector();

        return;
    }

    void openOrganizationSelector();
};
const selectOrganizationLocation = (locationId: number): void => {
    selectedLocationId.value = locationId;
    closeOrganizationSelector();
    updateOrganizationContext();
};
const handleOrganizationSelectorOutsideClick = (event: MouseEvent): void => {
    if (
        event.target instanceof Node
        && !organizationSelector.value?.contains(event.target)
    ) {
        closeOrganizationSelector();
    }
};
const updateOrganizationContext = (): void => {
    if (selectedLocationId.value === null) {
        return;
    }

    router.post(
        '/contexto-organizacion',
        { location_id: selectedLocationId.value },
        { preserveScroll: true, preserveState: false },
    );
};
const accountInitials = computed(() =>
    accountName.value
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toLocaleUpperCase('es-AR'),
);
const flash = computed<AppFlashData>(() => page.flash ?? {});

const navigationItems = [
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: 'dashboard',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
            'reception',
            'professional',
            'nursing',
            'emergency',
            'accounting',
            'sales',
            'auditor',
        ],
    },
    {
        label: 'Mi portal',
        href: '/portal',
        icon: 'user',
        allowedRoles: ['patient'],
    },
    {
        label: 'Usuarios',
        href: '/usuarios',
        icon: 'users',
        allowedRoles: ['superadmin'],
    },
    {
        label: 'Profesionales',
        href: '/profesionales',
        icon: 'stethoscope',
        allowedRoles: ['superadmin'],
    },
    {
        label: 'Pacientes',
        href: '/pacientes',
        icon: 'patients',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
            'reception',
            'professional',
            'nursing',
            'emergency',
        ],
    },
    {
        label: 'Turnos',
        href: '/turnos',
        icon: 'calendar-days',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
            'reception',
            'professional',
            'nursing',
            'emergency',
            'patient',
        ],
    },
    {
        label: 'Facturación',
        href: '/facturacion',
        icon: 'wallet',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'accounting',
            'reception',
            'sales',
        ],
    },
    {
        label: 'Coberturas',
        href: '/coberturas',
        icon: 'wallet',
        allowedRoles: ['superadmin', 'administrator', 'manager', 'accounting', 'reception', 'sales'],
    },
    {
        label: 'Contabilidad',
        href: '/contabilidad',
        icon: 'receipt',
        allowedRoles: ['superadmin', 'administrator', 'manager', 'accounting'],
    },
    {
        label: 'Agenda operativa',
        href: '/agenda-operativa',
        icon: 'clock',
        allowedRoles: [
            'superadmin',
            'administrator',
            'supervisor',
            'reception',
            'professional',
        ],
    },
    {
        label: 'Sedes',
        href: '/organizacion/sedes',
        icon: 'building',
        allowedRoles: [
            'superadmin',
            'administrator',
            'manager',
            'supervisor',
        ],
    },
    {
        label: 'Auditoría',
        href: '/auditoria',
        icon: 'shield-check',
        allowedRoles: ['auditor'],
    },
];
const navigation = computed(() =>
    navigationItems.filter(
        (item) => item.allowedRoles?.includes(accountRoleCode.value) ?? true,
    ),
);
const navigationIsInert = computed(
    () => !desktopNavigationVisible.value && !mobileNavigationOpen.value,
);

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (href: string): boolean =>
    href === '/dashboard'
        ? currentPath.value === href
        : currentPath.value.startsWith(href);

watch(
    () => page.url,
    () => {
        mobileNavigationOpen.value = false;
        contextMenuOpen.value = false;
    },
);

const updateDesktopNavigation = (
    mediaQuery: MediaQueryList | MediaQueryListEvent,
): void => {
    desktopNavigationVisible.value = mediaQuery.matches;
};

onMounted(() => {
    desktopNavigationMediaQuery = window.matchMedia('(min-width: 1024px)');
    updateDesktopNavigation(desktopNavigationMediaQuery);
    desktopNavigationMediaQuery.addEventListener(
        'change',
        updateDesktopNavigation,
    );
    document.addEventListener('click', handleOrganizationSelectorOutsideClick);
});

watch(() => organizationContext.value.selectedLocationId, (locationId) => {
    selectedLocationId.value = locationId;
});

onUnmounted(() => {
    desktopNavigationMediaQuery?.removeEventListener(
        'change',
        updateDesktopNavigation,
    );
    document.removeEventListener('click', handleOrganizationSelectorOutsideClick);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <a
            href="#main-content"
            class="sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:not-sr-only focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-clinic-700 focus:shadow-lg dark:focus:bg-slate-900 dark:focus:text-clinic-300"
        >
            Ir al contenido
        </a>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <button
                v-if="mobileNavigationOpen"
                class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
                aria-label="Cerrar menú"
                @click="mobileNavigationOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200/80 bg-white px-4 py-5 transition-transform duration-200 dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0"
            :inert="navigationIsInert"
            :aria-hidden="navigationIsInert ? 'true' : undefined"
            :class="
                mobileNavigationOpen
                    ? 'translate-x-0 shadow-2xl'
                    : '-translate-x-full'
            "
        >
            <div class="flex items-center justify-between px-2">
                <BrandMark />
                <button
                    class="grid size-10 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="Cerrar menú"
                    @click="mobileNavigationOpen = false"
                >
                    <BaseIcon name="close" class="size-5" />
                </button>
            </div>

            <div
                class="mt-7 rounded-2xl border border-clinic-100 bg-clinic-50/80 p-3 dark:border-clinic-900/60 dark:bg-clinic-950/30"
            >
                <div ref="organizationSelector" class="relative">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl p-1 text-left transition focus-visible:ring-2 focus-visible:ring-clinic-600/40 disabled:cursor-default"
                        :class="organizationSelectorLocked
                            ? 'opacity-75'
                            : 'hover:bg-white/70 dark:hover:bg-slate-800/70'"
                        :disabled="organizationSelectorLocked"
                        aria-haspopup="listbox"
                        :aria-expanded="organizationSelectorOpen"
                        aria-label="Seleccionar razón social y sede"
                        @click="toggleOrganizationSelector"
                        @keydown.down.prevent="openOrganizationSelector"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-clinic-700 shadow-sm dark:bg-slate-800 dark:text-clinic-300"
                        >
                            <BaseIcon name="building" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-semibold text-slate-900 dark:text-white">
                                {{ selectedLegalEntityName }}
                            </span>
                            <span class="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">
                                {{ selectedLocationName }}
                            </span>
                        </span>
                        <BaseIcon
                            name="chevron-down"
                            class="size-4 shrink-0 text-slate-400 transition-transform"
                            :class="{ 'rotate-180': organizationSelectorOpen }"
                        />
                    </button>

                    <div
                        v-if="organizationSelectorOpen"
                        role="listbox"
                        aria-label="Razones sociales y sedes disponibles"
                        class="absolute inset-x-0 top-full z-[60] mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-950/15 dark:border-slate-700 dark:bg-slate-900"
                        @keydown.esc.prevent="closeOrganizationSelector"
                    >
                        <div class="relative px-1 pb-1.5">
                            <BaseIcon
                                name="search"
                                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                ref="organizationSelectorSearchInput"
                                v-model="organizationSelectorQuery"
                                type="search"
                                placeholder="Buscar razón social o sede"
                                class="min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pr-3 pl-9 text-xs text-slate-900 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </div>

                        <div class="max-h-64 overflow-y-auto px-1 pb-1">
                            <div
                                v-for="entity in filteredOrganizationEntities"
                                :key="entity.id"
                                class="py-1"
                            >
                                <p class="px-2 py-1 text-[10px] font-bold tracking-[0.12em] text-slate-400 uppercase dark:text-slate-500">
                                    {{ entity.name }}
                                </p>
                                <button
                                    v-for="location in entity.locations"
                                    :key="location.id"
                                    type="button"
                                    role="option"
                                    :aria-selected="location.id === selectedLocationId"
                                    class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-xs transition"
                                    :class="location.id === selectedLocationId
                                        ? 'bg-clinic-50 font-semibold text-clinic-900 dark:bg-clinic-950/60 dark:text-clinic-100'
                                        : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'"
                                    @click="selectOrganizationLocation(location.id)"
                                >
                                    <BaseIcon name="building" class="size-3.5 shrink-0 text-slate-400" />
                                    <span class="min-w-0 flex-1 truncate">{{ location.name }}</span>
                                    <BaseIcon
                                        v-if="location.id === selectedLocationId"
                                        name="check"
                                        class="size-3.5 shrink-0 text-clinic-600 dark:text-clinic-300"
                                    />
                                </button>
                            </div>

                            <p
                                v-if="filteredOrganizationEntities.length === 0"
                                class="px-2 py-5 text-center text-xs text-slate-500 dark:text-slate-400"
                            >
                                No se encontraron razones sociales ni sedes.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="mt-7 flex flex-1 flex-col gap-1" aria-label="Principal">
                <p
                    class="px-3 pb-2 text-[10px] font-bold tracking-[0.16em] text-slate-400 uppercase dark:text-slate-500"
                >
                    Gestión
                </p>
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition"
                    :class="
                        isActive(item.href)
                            ? 'bg-clinic-600 text-white shadow-md shadow-clinic-600/20'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                    "
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    <BaseIcon
                        :name="item.icon"
                        class="size-5 shrink-0"
                        :class="
                            isActive(item.href)
                                ? 'text-white'
                                : 'text-slate-400 group-hover:text-clinic-600 dark:text-slate-500 dark:group-hover:text-clinic-300'
                        "
                    />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div
                class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-items-center rounded-full bg-clinic-100 text-xs font-bold text-clinic-700 dark:bg-clinic-900/60 dark:text-clinic-200"
                    >
                        {{ accountInitials }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-xs font-semibold text-slate-900 dark:text-white"
                        >
                            {{ accountName }}
                        </p>
                        <p
                            class="truncate text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{ accountRole }}
                        </p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-white hover:text-rose-600 dark:hover:bg-slate-800"
                        aria-label="Cerrar sesión"
                    >
                        <BaseIcon name="log-out" class="size-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header
                class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90"
            >
                <div
                    class="mx-auto flex h-16 max-w-[1600px] items-center gap-3 px-4 sm:px-6 lg:px-8"
                >
                    <button
                        class="grid size-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
                        aria-label="Abrir menú"
                        @click="mobileNavigationOpen = true"
                    >
                        <BaseIcon name="menu" class="size-5" />
                    </button>

                    <div class="hidden min-w-0 flex-1 items-center gap-2 sm:flex">
                        <BaseIcon
                            name="map-pin"
                            class="size-4 text-clinic-600 dark:text-clinic-300"
                        />
                        <p
                            class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ appName }}
                        </p>
                    </div>

                    <div class="ml-auto flex items-center gap-1.5">
                        <button
                            class="grid size-10 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                            :aria-label="
                                isDark ? 'Activar modo claro' : 'Activar modo oscuro'
                            "
                            @click="toggleTheme"
                        >
                            <BaseIcon
                                :name="isDark ? 'sun' : 'moon'"
                                class="size-5"
                            />
                        </button>
                        <div class="relative">
                            <button
                                class="flex min-h-10 items-center gap-2 rounded-xl pl-1.5 text-left hover:bg-slate-100 sm:pr-2 dark:hover:bg-slate-800"
                                aria-label="Abrir menú de cuenta"
                                :aria-expanded="contextMenuOpen"
                                @click="contextMenuOpen = !contextMenuOpen"
                            >
                                <span
                                    class="grid size-8 place-items-center rounded-full bg-clinic-600 text-[11px] font-bold text-white"
                                >
                                    {{ accountInitials }}
                                </span>
                                <span class="hidden sm:block">
                                    <span
                                        class="block text-xs font-semibold text-slate-800 dark:text-slate-100"
                                    >
                                        {{ accountName }}
                                    </span>
                                    <span
                                        class="block text-[10px] text-slate-400 dark:text-slate-500"
                                    >
                                        {{ accountEmail }}
                                    </span>
                                </span>
                                <BaseIcon
                                    name="chevron-down"
                                    class="hidden size-3.5 text-slate-400 sm:block"
                                />
                            </button>
                            <div
                                v-if="contextMenuOpen"
                                class="absolute top-12 right-0 w-52 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-800"
                            >
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                >
                                    <BaseIcon name="log-out" class="size-4" />
                                    Cerrar sesión
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main
                id="main-content"
                class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
            >
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-[11px] font-bold tracking-[0.16em] text-clinic-600 uppercase dark:text-clinic-300"
                        >
                            {{ eyebrow }}
                        </p>
                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <Link
                        v-if="actionHref && actionLabel"
                        :href="actionHref"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700"
                    >
                        <BaseIcon :name="actionIcon" class="size-4.5" />
                        {{ actionLabel }}
                    </Link>
                </div>

                <FlashMessage
                    :message="flash.success"
                    :error="flash.error"
                    :invitation-url="flash.invitationUrl"
                />

                <slot />
            </main>
        </div>
    </div>
</template>
