<script setup lang="ts">
import type {
    LegalEntityOption,
    Location,
    LocationFormData,
    OrganizationOption,
} from '@/domains/organizations/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    location?: Location;
    legalEntities: LegalEntityOption[];
    statusOptions: OrganizationOption[];
    serviceOptions: OrganizationOption[];
}>();

const isEditing = computed(() => Boolean(props.location));
const form = useForm<LocationFormData>({
    legal_entity_id: props.location?.legal_entity_id ?? '',
    name: props.location?.name ?? '',
    code: props.location?.code ?? '',
    address: props.location?.address ?? '',
    neighborhood: props.location?.neighborhood ?? '',
    postal_code: props.location?.postal_code ?? '',
    phone: props.location?.phone ?? '',
    email: props.location?.email ?? '',
    opening_date: props.location?.opening_date ?? '',
    opening_time: props.location?.opening_time ?? '08:00',
    closing_time: props.location?.closing_time ?? '20:00',
    timezone:
        props.location?.timezone ?? 'America/Argentina/Buenos_Aires',
    services: props.location?.services ?? ['outpatient', 'cash_desk'],
    status: props.location?.status.value ?? 'active',
});

const submitLabel = computed(() =>
    isEditing.value ? 'Guardar cambios' : 'Crear sede',
);

const submit = (): void => {
    if (props.location) {
        form.put(`/organizacion/sedes/${props.location.id}`, {
            preserveScroll: true,
        });

        return;
    }

    form.post('/organizacion/sedes', { preserveScroll: true });
};
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <header
                class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6"
            >
                <h2 class="font-semibold text-slate-950 dark:text-white">
                    Identificación y pertenencia
                </h2>
                <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                    Vinculá la sede a la entidad que facturará sus prestaciones.
                </p>
            </header>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div class="sm:col-span-2">
                    <label
                        for="location-legal-entity"
                        class="text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Razón social <span class="text-rose-600">*</span>
                    </label>
                    <select
                        id="location-legal-entity"
                        v-model="form.legal_entity_id"
                        required
                        :aria-invalid="Boolean(form.errors.legal_entity_id)"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        <option value="" disabled>Seleccionar razón social</option>
                        <option
                            v-for="legalEntity in legalEntities"
                            :key="legalEntity.id"
                            :value="legalEntity.id"
                        >
                            {{ legalEntity.label }} · {{ legalEntity.cuit }}
                        </option>
                    </select>
                    <FieldError :message="form.errors.legal_entity_id" />
                    <p
                        v-if="legalEntities.length === 0"
                        class="pt-3 text-sm text-amber-700 dark:text-amber-300"
                    >
                        No hay razones sociales activas.
                        <Link
                            href="/organizacion/razones-sociales/nueva"
                            class="font-semibold underline underline-offset-2"
                        >
                            Creá una antes de continuar.
                        </Link>
                    </p>
                </div>

                <div>
                    <label for="location-name" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Nombre <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="location-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        autocomplete="organization"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="Sede Palermo"
                    />
                    <FieldError :message="form.errors.name" />
                </div>

                <div>
                    <label for="location-code" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Código <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="location-code"
                        v-model="form.code"
                        required
                        minlength="2"
                        maxlength="20"
                        pattern="[A-Za-z0-9-]+"
                        autocapitalize="characters"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 font-mono text-sm uppercase text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        placeholder="PAL"
                    />
                    <FieldError :message="form.errors.code" />
                </div>

                <div class="sm:col-span-2">
                    <label for="location-address" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Dirección <span class="text-rose-600">*</span>
                    </label>
                    <input
                        id="location-address"
                        v-model="form.address"
                        required
                        maxlength="1000"
                        autocomplete="street-address"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.address" />
                </div>

                <div>
                    <label for="location-neighborhood" class="text-sm font-medium text-slate-700 dark:text-slate-300">Barrio</label>
                    <input
                        id="location-neighborhood"
                        v-model="form.neighborhood"
                        maxlength="120"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.neighborhood" />
                </div>

                <div>
                    <label for="location-postal-code" class="text-sm font-medium text-slate-700 dark:text-slate-300">Código postal</label>
                    <input
                        id="location-postal-code"
                        v-model="form.postal_code"
                        maxlength="16"
                        autocomplete="postal-code"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.postal_code" />
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <header class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <h2 class="font-semibold text-slate-950 dark:text-white">Operación y contacto</h2>
                <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                    Los horarios son configurables por sede y se usarán para validar agendas.
                </p>
            </header>

            <div class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
                <div>
                    <label for="location-phone" class="text-sm font-medium text-slate-700 dark:text-slate-300">Teléfono</label>
                    <input
                        id="location-phone"
                        v-model="form.phone"
                        type="tel"
                        maxlength="32"
                        autocomplete="tel"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.phone" />
                </div>

                <div>
                    <label for="location-email" class="text-sm font-medium text-slate-700 dark:text-slate-300">Correo</label>
                    <input
                        id="location-email"
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.email" />
                </div>

                <div>
                    <label for="location-opening-date" class="text-sm font-medium text-slate-700 dark:text-slate-300">Fecha de apertura</label>
                    <input
                        id="location-opening-date"
                        v-model="form.opening_date"
                        type="date"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.opening_date" />
                </div>

                <div>
                    <label for="location-opening-time" class="text-sm font-medium text-slate-700 dark:text-slate-300">Apertura <span class="text-rose-600">*</span></label>
                    <input
                        id="location-opening-time"
                        v-model="form.opening_time"
                        type="time"
                        required
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.opening_time" />
                </div>

                <div>
                    <label for="location-closing-time" class="text-sm font-medium text-slate-700 dark:text-slate-300">Cierre <span class="text-rose-600">*</span></label>
                    <input
                        id="location-closing-time"
                        v-model="form.closing_time"
                        type="time"
                        required
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.closing_time" />
                </div>

                <div>
                    <label for="location-status" class="text-sm font-medium text-slate-700 dark:text-slate-300">Estado <span class="text-rose-600">*</span></label>
                    <select
                        id="location-status"
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

                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="location-timezone" class="text-sm font-medium text-slate-700 dark:text-slate-300">Zona horaria <span class="text-rose-600">*</span></label>
                    <input
                        id="location-timezone"
                        v-model="form.timezone"
                        required
                        maxlength="64"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 font-mono text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                    <FieldError :message="form.errors.timezone" />
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"
        >
            <h2 class="font-semibold text-slate-950 dark:text-white">Servicios disponibles</h2>
            <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                Seleccioná al menos un servicio operativo.
            </p>
            <div class="grid gap-3 pt-5 sm:grid-cols-2 xl:grid-cols-3">
                <label
                    v-for="option in serviceOptions"
                    :key="option.value"
                    class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700 transition hover:border-clinic-300 hover:bg-clinic-50/40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-clinic-700 dark:hover:bg-clinic-950/20"
                >
                    <input
                        v-model="form.services"
                        type="checkbox"
                        :value="option.value"
                        class="size-4 rounded border-slate-300 text-clinic-600 focus:ring-clinic-600"
                    />
                    <span class="font-medium">{{ option.label }}</span>
                </label>
            </div>
            <FieldError :message="form.errors.services" />
        </section>

        <div
            class="sticky bottom-3 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:static sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none dark:border-slate-800 dark:bg-slate-900/95 sm:dark:bg-transparent"
        >
            <Link
                href="/organizacion/sedes"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <BaseIcon name="arrow-left" class="size-4" /> Cancelar
            </Link>
            <button
                type="submit"
                :disabled="form.processing || legalEntities.length === 0"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <BaseIcon :name="form.processing ? 'loader' : 'save'" class="size-4" :class="{ 'animate-spin': form.processing }" />
                {{ form.processing ? 'Guardando…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
