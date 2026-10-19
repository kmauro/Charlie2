<script setup lang="ts">
import FieldError from '@/shared/components/FieldError.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Location = { id: number; name: string; legal_entity_id: number };
type Patient = { id: number; first_name: string; last_name: string };
type Benefit = { id: number; name: string; coverage_percentage: string; copay_amount: string | null; annual_limit: number | null };
type Plan = { id: number; legal_entity_id: number; name: string; code: string; billing_cycle: string; recurring_amount: string; is_active: boolean; benefits: Benefit[] };
type Contract = { id: number; patient: Patient; plan: Pick<Plan, 'name' | 'billing_cycle'>; member_number: string | null; payer_type: string; recurring_amount: string; coverage_amount: string; starts_on: string; next_charge_on: string; status: string; charges: Array<{ id: number; due_date: string; amount: string; invoice: { number: string; status: string } | null }> };

const props = defineProps<{ locations: Location[]; patients: Patient[]; plans: Plan[]; contracts: Contract[]; permissions: { canManage: boolean } }>();
const planPanelOpen = ref(false);
const contractPanelOpen = ref(false);
const selectedPlan = ref<Plan | null>(null);
const planForm = useForm({ legal_entity_id: props.locations[0]?.legal_entity_id ?? '', name: '', code: '', billing_cycle: 'monthly', recurring_amount: 0 });
const benefitForm = useForm({ name: '', coverage_percentage: 100, copay_amount: '', annual_limit: '' });
const contractForm = useForm({ location_id: props.locations[0]?.id ?? '', patient_id: '', coverage_plan_id: '', member_number: '', payer_type: 'patient', coverage_amount: 0, recurring_amount: 0, starts_on: new Date().toISOString().slice(0, 10), ends_on: '' });
const chargeForm = useForm({});
const availablePlans = computed(() => props.plans.filter((plan) => plan.legal_entity_id === props.locations.find((location) => location.id === Number(contractForm.location_id))?.legal_entity_id && plan.is_active));
const currency = (amount: string | number): string => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(amount));
const cycleLabel = (cycle: string): string => ({ monthly: 'Mensual', quarterly: 'Trimestral', yearly: 'Anual' }[cycle] ?? cycle);
const payerLabel = (payer: string): string => ({ patient: 'Paciente', coverage: 'Cobertura', mixed: 'Mixto' }[payer] ?? payer);
const submitPlan = (): void => planForm.post('/coberturas/planes', { preserveScroll: true, onSuccess: () => { planPanelOpen.value = false; planForm.reset(); } });
const openBenefitPanel = (plan: Plan): void => { selectedPlan.value = plan; benefitForm.reset(); };
const submitBenefit = (): void => { if (selectedPlan.value) benefitForm.post(`/coberturas/planes/${selectedPlan.value.id}/prestaciones`, { preserveScroll: true, onSuccess: () => { selectedPlan.value = null; benefitForm.reset(); } }); };
const selectPlan = (): void => { const plan = props.plans.find((item) => item.id === Number(contractForm.coverage_plan_id)); if (plan) contractForm.recurring_amount = Number(plan.recurring_amount); };
const submitContract = (): void => contractForm.post('/coberturas/contratos', { preserveScroll: true, onSuccess: () => { contractPanelOpen.value = false; contractForm.reset(); } });
const generateCharge = (contract: Contract): void => chargeForm.post(`/coberturas/contratos/${contract.id}/cobros`, { preserveScroll: true });
</script>

<template>
    <Head title="Coberturas y contratos" />
    <AppShell title="Coberturas y contratos" eyebrow="Facturación" description="Planes, prestaciones, contratos y cobros periódicos conectados a la facturación.">
        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500 dark:text-slate-400">Planes activos</p><p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ plans.filter((plan) => plan.is_active).length }}</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500 dark:text-slate-400">Contratos activos</p><p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ contracts.filter((contract) => contract.status === 'active').length }}</p></article>
            <article class="rounded-2xl border border-clinic-200 bg-clinic-50 p-5 dark:border-clinic-900/60 dark:bg-clinic-950/20"><p class="text-sm font-semibold text-clinic-800 dark:text-clinic-200">Cobro recurrente</p><p class="mt-2 text-sm leading-6 text-clinic-700 dark:text-clinic-300">Los vencimientos emiten facturas y asientos con las reglas de cobro ya vigentes.</p></article>
        </section>

        <section v-if="permissions.canManage" class="mt-6 flex flex-wrap gap-3">
            <button type="button" class="min-h-11 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 hover:bg-clinic-700" @click="planPanelOpen = !planPanelOpen">Nuevo plan</button>
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" @click="contractPanelOpen = !contractPanelOpen">Nuevo contrato</button>
        </section>

        <form v-if="planPanelOpen" class="mt-6 rounded-2xl border border-clinic-200 bg-white p-5 shadow-sm dark:border-clinic-900/60 dark:bg-slate-900" @submit.prevent="submitPlan">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Nuevo plan</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Razón social
                    <select
                    v-model="planForm.legal_entity_id"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                        <option v-for="location in locations" :key="location.legal_entity_id" :value="location.legal_entity_id">
                            {{ location.name }}
                        </option>
                    </select>
                    <FieldError :message="planForm.errors.legal_entity_id" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Nombre
                    <input
                    v-model="planForm.name"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                    <FieldError :message="planForm.errors.name" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Código
                    <input
                    v-model="planForm.code"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                    <FieldError :message="planForm.errors.code" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Ciclo
                    <select
                    v-model="planForm.billing_cycle"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                        <option value="monthly">
                            Mensual
                        </option>
                        <option value="quarterly">
                            Trimestral
                        </option>
                        <option value="yearly">
                            Anual
                        </option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Valor
                    <input
                    v-model="planForm.recurring_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                    <FieldError :message="planForm.errors.recurring_amount" />
                </label>
            </div>
            <button class="mt-5 min-h-10 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700">Guardar plan</button>
        </form>

        <form v-if="selectedPlan" class="mt-6 rounded-2xl border border-clinic-200 bg-white p-5 shadow-sm dark:border-clinic-900/60 dark:bg-slate-900" @submit.prevent="submitBenefit">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Prestación de {{ selectedPlan.name }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-4">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Prestación
                    <input
                    v-model="benefitForm.name"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                    <FieldError :message="benefitForm.errors.name" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Cobertura %
                    <input
                    v-model="benefitForm.coverage_percentage"
                    type="number"
                    min="0"
                    max="100"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Copago
                    <input
                    v-model="benefitForm.copay_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Límite anual
                    <input
                    v-model="benefitForm.annual_limit"
                    type="number"
                    min="1"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
            </div>
            <button class="mt-5 min-h-10 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700">Agregar prestación</button>
        </form>

        <form v-if="contractPanelOpen" class="mt-6 rounded-2xl border border-clinic-200 bg-white p-5 shadow-sm dark:border-clinic-900/60 dark:bg-slate-900" @submit.prevent="submitContract">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Nuevo contrato</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Sede
                    <select
                    v-model="contractForm.location_id"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                        <option v-for="location in locations" :key="location.id" :value="location.id">
                            {{ location.name }}
                        </option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Paciente
                    <select
                    v-model="contractForm.patient_id"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                        <option value="">
                            Seleccionar
                        </option>
                        <option v-for="patient in patients" :key="patient.id" :value="patient.id">
                            {{ patient.last_name }}, {{ patient.first_name }}
                        </option>
                    </select>
                    <FieldError :message="contractForm.errors.patient_id" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Plan
                    <select
                    v-model="contractForm.coverage_plan_id"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950"
                    @change="selectPlan">
                        <option value="">
                            Seleccionar
                        </option>
                        <option v-for="plan in availablePlans" :key="plan.id" :value="plan.id">
                            {{ plan.name }}
                        </option>
                    </select>
                    <FieldError :message="contractForm.errors.coverage_plan_id" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    N° afiliado
                    <input
                    v-model="contractForm.member_number"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Responsable
                    <select
                    v-model="contractForm.payer_type"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                        <option value="patient">
                            Paciente
                        </option>
                        <option value="coverage">
                            Cobertura
                        </option>
                        <option value="mixed">
                            Mixto
                        </option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    A cargo de cobertura
                    <input
                    v-model="contractForm.coverage_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                    <FieldError :message="contractForm.errors.coverage_amount" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Importe recurrente
                    <input
                    v-model="contractForm.recurring_amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Primer vencimiento
                    <input
                    v-model="contractForm.starts_on"
                    type="date"
                    class="mt-1 w-full min-h-8 pl-15 rounded-xl border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950" />
                </label>
            </div>
            <button class="mt-5 min-h-10 rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700">Guardar contrato</button>
        </form>

        <section class="mt-8 grid gap-6 xl:grid-cols-2">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="font-bold text-slate-950 dark:text-white">Planes y prestaciones</h2>
                </div>
                <div v-if="plans.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="plan in plans" :key="plan.id" class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950 dark:text-white">{{ plan.name }}
                                    <span class="text-sm font-normal text-slate-500">{{ plan.code }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ cycleLabel(plan.billing_cycle) }} · {{ currency(plan.recurring_amount) }}
                                </p>
                            </div>
                            <button
                            v-if="permissions.canManage"
                            class="text-sm font-semibold text-clinic-700 hover:text-clinic-800 dark:text-clinic-300"
                            @click="openBenefitPanel(plan)">
                            Agregar prestación
                            </button>
                        </div>
                        <ul v-if="plan.benefits.length" class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                            <li v-for="benefit in plan.benefits" :key="benefit.id">
                                {{ benefit.name }} · {{ benefit.coverage_percentage }}%
                                <span v-if="benefit.copay_amount">· copago {{ currency(benefit.copay_amount) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <p v-else class="p-5 text-sm text-slate-500">Todavía no hay planes creados.</p>
            </article>

            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="font-bold text-slate-950 dark:text-white">Contratos</h2>
                </div>
                <div v-if="contracts.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="contract in contracts" :key="contract.id" class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950 dark:text-white">
                                    {{ contract.patient.last_name }}, {{ contract.patient.first_name }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ contract.plan.name }} · {{ payerLabel(contract.payer_type) }} · {{ currency(contract.recurring_amount) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Próximo vencimiento: {{ contract.next_charge_on }}
                                </p>
                            </div>
                            <button
                            v-if="permissions.canManage"
                            class="min-h-9 rounded-lg border border-clinic-300 px-3 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-800 dark:text-clinic-300"
                            @click="generateCharge(contract)">
                            Emitir cobro
                            </button>
                        </div>
                        <p v-if="contract.charges[0]?.invoice" class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            Último comprobante: {{ contract.charges[0].invoice.number }} · {{ currency(contract.charges[0].amount) }}
                        </p>
                    </div>
                </div>
                <p v-else class="p-5 text-sm text-slate-500">
                    Todavía no hay contratos creados.
                </p>
            </article>
        </section>

    </AppShell>
</template>
