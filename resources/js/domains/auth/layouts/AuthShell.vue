<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    eyebrow?: string;
}>();

const currentYear = new Date().getFullYear();
</script>

<template>
    <div
        class="relative min-h-dvh overflow-hidden bg-slate-50 text-slate-950 scheme-light dark:bg-slate-950 dark:text-white dark:scheme-dark"
    >
        <a
            href="#auth-content"
            class="absolute top-3 left-3 z-50 -translate-y-20 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-lg ring-1 ring-slate-200 transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:bg-slate-900 dark:text-blue-300 dark:ring-slate-700"
        >
            Ir al contenido
        </a>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <div
                class="absolute -top-48 -right-44 size-[34rem] rounded-full bg-blue-200/40 blur-3xl dark:bg-blue-600/10"
            />
            <div
                class="absolute -bottom-64 -left-40 size-[38rem] rounded-full bg-cyan-200/30 blur-3xl dark:bg-cyan-500/10"
            />
        </div>

        <div
            class="relative mx-auto grid min-h-dvh w-full max-w-[100rem] lg:grid-cols-[minmax(0,1.05fr)_minmax(28rem,0.95fr)]"
        >
            <aside
                class="relative hidden overflow-hidden border-r border-white/10 bg-blue-950 px-12 py-10 text-white lg:flex lg:flex-col xl:px-16 xl:py-12"
            >
                <div
                    aria-hidden="true"
                    class="absolute inset-0 bg-[radial-gradient(circle_at_18%_15%,rgba(56,189,248,0.24),transparent_32%),radial-gradient(circle_at_82%_78%,rgba(37,99,235,0.34),transparent_36%)]"
                />
                <div
                    aria-hidden="true"
                    class="absolute inset-0 opacity-[0.12] [background-image:linear-gradient(rgba(255,255,255,.4)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.4)_1px,transparent_1px)] [background-size:44px_44px]"
                />

                <Link
                    href="/"
                    class="relative flex w-fit items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-4 focus-visible:ring-offset-blue-950"
                    aria-label="Clínica, ir al inicio"
                >
                    <span
                        class="grid size-11 place-items-center rounded-2xl bg-white text-blue-700 shadow-lg shadow-blue-950/20"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                            class="size-6"
                        >
                            <path
                                d="M9.25 4.5h5.5v4.75h4.75v5.5h-4.75v4.75h-5.5v-4.75H4.5v-5.5h4.75V4.5Z"
                                fill="currentColor"
                            />
                        </svg>
                    </span>
                    <span class="text-xl font-semibold tracking-tight">Clínica</span>
                </Link>

                <div class="relative my-auto max-w-2xl py-16">
                    <div
                        class="mb-8 flex w-fit items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-cyan-100 uppercase"
                    >
                        <span class="size-1.5 rounded-full bg-cyan-300" />
                        Gestión clínica integral
                    </div>

                    <h2
                        class="max-w-xl text-4xl leading-[1.08] font-semibold tracking-[-0.035em] text-balance xl:text-5xl"
                    >
                        Toda la atención, conectada en un solo lugar.
                    </h2>
                    <p class="mt-6 max-w-lg text-base leading-7 text-blue-100/80">
                        Una experiencia segura para administrar pacientes,
                        profesionales y sedes con información clara en cada paso.
                    </p>

                    <div class="mt-10 grid max-w-lg grid-cols-3 gap-3">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                                class="size-5 text-cyan-300"
                            >
                                <path
                                    d="M12 21s7-3.2 7-10V5l-7-2-7 2v6c0 6.8 7 10 7 10Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="m9.3 12 1.7 1.7 3.8-4"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <p class="mt-3 text-sm font-medium">Acceso seguro</p>
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                                class="size-5 text-cyan-300"
                            >
                                <path
                                    d="M4 19V9m8 10V5m8 14v-7M2 19h20"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <p class="mt-3 text-sm font-medium">Datos claros</p>
                        </div>
                        <div
                            class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                                class="size-5 text-cyan-300"
                            >
                                <path
                                    d="M8 7V5m8 2V5M5 10h14M7 3h10a2 2 0 0 1 2 2v14H5V5a2 2 0 0 1 2-2Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="m9 14 2 2 4-4"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <p class="mt-3 text-sm font-medium">Trabajo simple</p>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-center gap-3 text-xs text-blue-100/65">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                        class="size-4"
                    >
                        <path
                            d="M8 10V7a4 4 0 1 1 8 0v3m-9 0h10a2 2 0 0 1 2 2v7H5v-7a2 2 0 0 1 2-2Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    La actividad del sistema se registra para proteger la
                    información clínica.
                </div>
            </aside>

            <main
                id="auth-content"
                class="flex min-h-dvh flex-col px-5 py-6 sm:px-8 sm:py-8 lg:px-12 xl:px-20"
            >
                <header class="flex items-center justify-between gap-4 lg:justify-end">
                    <Link
                        href="/"
                        class="flex items-center gap-2.5 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-4 focus-visible:ring-offset-slate-50 lg:hidden dark:focus-visible:ring-offset-slate-950"
                        aria-label="Clínica, ir al inicio"
                    >
                        <span
                            class="grid size-9 place-items-center rounded-xl bg-blue-700 text-white shadow-sm shadow-blue-900/20"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                                class="size-5"
                            >
                                <path
                                    d="M9.25 4.5h5.5v4.75h4.75v5.5h-4.75v4.75h-5.5v-4.75H4.5v-5.5h4.75V4.5Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <span class="font-semibold tracking-tight">Clínica</span>
                    </Link>

                    <span
                        v-if="eyebrow"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                    >
                        <span
                            class="size-1.5 rounded-full bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.12)]"
                        />
                        {{ eyebrow }}
                    </span>
                </header>

                <div class="flex flex-1 items-center justify-center py-10 sm:py-14">
                    <div class="w-full max-w-[29rem]">
                        <slot />
                    </div>
                </div>

                <footer
                    class="flex flex-col items-center justify-between gap-2 text-center text-xs text-slate-500 sm:flex-row sm:text-left dark:text-slate-500"
                >
                    <p>© {{ currentYear }} Clínica. Uso interno autorizado.</p>
                    <p>Privacidad y seguridad por diseño</p>
                </footer>
            </main>
        </div>
    </div>
</template>
