<script setup lang="ts">
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    invoices: Array<{ id: number; number: string; type: string; status: string; total_amount: string; paid_amount: string; issued_at: string }>;
    cashRegisters: Array<{ id: number; location_id: number; opening_amount: string; opened_at: string | null }>;
}>();

const currency = (amount: string): string => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS',
}).format(Number(amount));
</script>

<template>
    <Head title="Facturación" />
    <AppShell title="Facturación y cajas" eyebrow="Administración" description="Facturas, cobranzas, anticipos, cuotas, devoluciones y cierres con impacto contable automático.">
        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Comprobantes recientes</p>
                <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ invoices.length }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">Cajas abiertas</p>
                <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ cashRegisters.filter((cashRegister) => cashRegister.opened_at).length }}</p>
            </article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/20">
                <p class="text-sm font-medium text-amber-900 dark:text-amber-200">ARCA</p>
                <p class="mt-2 text-sm leading-6 text-amber-800 dark:text-amber-300">La emisión electrónica se habilitará al completar el punto de venta y las credenciales fiscales.</p>
            </article>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h2 class="font-semibold text-slate-950 dark:text-white">Últimos comprobantes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-175 text-left text-sm">
                    <thead class="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-5 py-3">Número</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Cobrado</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="invoice in invoices" :key="invoice.id"><td class="px-5 py-4 font-mono font-medium text-slate-950 dark:text-white">{{ invoice.number }}</td><td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ invoice.type === 'credit_note' ? 'Nota de crédito' : invoice.type === 'advance' ? 'Anticipo' : 'Factura' }}</td><td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ invoice.status }}</td><td class="px-5 py-4 text-right font-medium text-slate-950 dark:text-white">{{ currency(invoice.total_amount) }}</td><td class="px-5 py-4 text-right text-slate-600 dark:text-slate-300">{{ currency(invoice.paid_amount) }}</td></tr>
                        <tr v-if="!invoices.length"><td colspan="5" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">Todavía no hay comprobantes emitidos.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppShell>
</template>
