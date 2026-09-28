<script setup lang="ts">
import type {
    LegalEntity,
    LegalEntityFormData,
    OrganizationOption,
} from '@/domains/organizations/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    legalEntity?: LegalEntity;
    statusOptions: OrganizationOption[];
}>();

const isEditing = computed(() => Boolean(props.legalEntity));
const form = useForm<LegalEntityFormData>({
    business_name: props.legalEntity?.business_name ?? '',
    cuit: props.legalEntity?.cuit ?? '',
    tax_condition: props.legalEntity?.tax_condition ?? 'responsable_inscripto',
    fiscal_address: props.legalEntity?.fiscal_address ?? '',
    fiscal_point_of_sale: props.legalEntity?.fiscal_point_of_sale ?? '',
    arca_environment: props.legalEntity?.arca_environment ?? '',
    arca_certificate: '',
    arca_private_key: '',
    status: props.legalEntity?.status.value ?? 'active',
});

const submit = (): void => {
    if (props.legalEntity) {
        form.put(`/organizacion/razones-sociales/${props.legalEntity.id}`, {
            preserveScroll: true,
        });

        return;
    }

    form.post('/organizacion/razones-sociales', { preserveScroll: true });
};
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <header class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <h2 class="font-semibold text-slate-950 dark:text-white">Datos fiscales</h2>
                <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                    Esta información identificará a la entidad en facturación y contabilidad.
                </p>
            </header>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div class="sm:col-span-2">
                    <label for="legal-business-name" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Razón social <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="legal-business-name"
                        v-model="form.business_name"
                        required
                        maxlength="255"
                        autocomplete="organization"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.business_name" />
                </div>

                <div>
                    <label for="legal-cuit" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        CUIT <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="legal-cuit"
                        v-model="form.cuit"
                        required
                        inputmode="numeric"
                        maxlength="13"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 font-mono text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="30-12345678-9"
                    />
                    <p class="pt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Se normaliza a 11 dígitos y se verifica el dígito de control.
                    </p>
                    <FieldError :message="form.errors.cuit" />
                </div>

                <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Credenciales ARCA</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Opcionales hasta integrar emisión electrónica. Se cifran al guardarse y no vuelven a mostrarse.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label for="legal-arca-certificate" class="text-sm font-medium text-slate-700 dark:text-slate-300">Certificado
                            <textarea id="legal-arca-certificate" v-model="form.arca_certificate" rows="3" autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                            <FieldError :message="form.errors.arca_certificate" />
                        </label>
                        <label for="legal-arca-private-key" class="text-sm font-medium text-slate-700 dark:text-slate-300">Clave privada
                            <textarea id="legal-arca-private-key" v-model="form.arca_private_key" rows="3" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                            <FieldError :message="form.errors.arca_private_key" />
                        </label>
                    </div>
                </div>

                <div>
                    <label for="legal-tax-condition" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Condición fiscal <span class="text-rose-600">*</span>
                    </label>
                    <select id="legal-tax-condition" v-model="form.tax_condition" required class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="responsable_inscripto">Responsable inscripto</option>
                        <option value="monotributo">Monotributo</option>
                        <option value="exento">Exento</option>
                        <option value="consumidor_final">Consumidor final</option>
                    </select>
                    <FieldError :message="form.errors.tax_condition" />
                </div>

                <div>
                    <label for="legal-status" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Estado <span class="text-rose-600">*</span>
                    </label>
                    <select
                        id="legal-status"
                        v-model="form.status"
                        required
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <FieldError :message="form.errors.status" />
                </div>

                <div class="sm:col-span-2">
                    <label for="legal-fiscal-address" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Domicilio fiscal <span class="text-rose-600">*</span>
                    </label>
                    <textarea
                        id="legal-fiscal-address"
                        v-model="form.fiscal_address"
                        required
                        rows="3"
                        maxlength="1000"
                        autocomplete="street-address"
                        class="mt-2 w-full resize-y rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.fiscal_address" />
                </div>

                <div>
                    <label for="legal-point-of-sale" class="text-sm font-medium text-slate-700 dark:text-slate-300">Punto de venta ARCA</label>
                    <input id="legal-point-of-sale" v-model="form.fiscal_point_of_sale" inputmode="numeric" min="1" max="99999" type="number" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                    <FieldError :message="form.errors.fiscal_point_of_sale" />
                </div>

                <div>
                    <label for="legal-arca-environment" class="text-sm font-medium text-slate-700 dark:text-slate-300">Ambiente ARCA</label>
                    <select id="legal-arca-environment" v-model="form.arca_environment" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">Sin configurar</option>
                        <option value="homologacion">Homologación</option>
                        <option value="produccion">Producción</option>
                    </select>
                    <p class="pt-1.5 text-xs text-slate-500 dark:text-slate-400">Opcional hasta activar la emisión electrónica.</p>
                    <FieldError :message="form.errors.arca_environment" />
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <Link
                href="/organizacion/razones-sociales"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <BaseIcon name="arrow-left" class="size-4" /> Cancelar
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <BaseIcon :name="form.processing ? 'loader' : 'save'" class="size-4" :class="{ 'animate-spin': form.processing }" />
                {{ form.processing ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear razón social' }}
            </button>
        </div>
    </form>
</template>
