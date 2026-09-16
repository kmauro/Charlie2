<script setup lang="ts">
import type {
    Patient,
    PatientContactFormData,
    PatientFormData,
    PatientOption,
} from '@/domains/patients/types';
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        patient?: Patient;
        statusOptions: PatientOption[];
        registeredSexOptions: PatientOption[];
    }>(),
    {
        patient: undefined,
    },
);

const newContact = (): PatientContactFormData => ({
    full_name: '',
    relationship: '',
    phone: '',
    email: '',
    is_emergency_contact: false,
    is_legal_representative: false,
    is_financially_responsible: false,
});

const initialContacts = (): PatientContactFormData[] => {
    if (!props.patient?.contacts.length) {
        return [newContact()];
    }

    return props.patient.contacts.map((contact) => ({
        full_name: contact.full_name,
        relationship: contact.relationship,
        phone: contact.phone ?? '',
        email: contact.email ?? '',
        is_emergency_contact: contact.is_emergency_contact,
        is_legal_representative: contact.is_legal_representative,
        is_financially_responsible: contact.is_financially_responsible,
    }));
};

const isEditing = computed(() => props.patient !== undefined);
const editableStatusOptions = computed(() =>
    props.statusOptions.filter((option) => option.value !== 'archived'),
);
const initialStatus = computed(
    () =>
        props.patient?.status.value ??
        editableStatusOptions.value.find((option) => option.value === 'active')
            ?.value ??
        editableStatusOptions.value[0]?.value ??
        'active',
);

const form = useForm<PatientFormData>({
    ...(props.patient ? { lock_version: props.patient.lock_version } : {}),
    first_name: props.patient?.first_name ?? '',
    last_name: props.patient?.last_name ?? '',
    document_number: props.patient?.document_number ?? '',
    birth_date: props.patient?.birth_date?.slice(0, 10) ?? '',
    registered_sex:
        props.patient?.registered_sex.value ??
        props.registeredSexOptions[0]?.value ??
        '',
    gender: props.patient?.gender ?? '',
    phone: props.patient?.phone ?? '',
    email: props.patient?.email ?? '',
    address: props.patient?.address ?? '',
    coverage_name: props.patient?.coverage_name ?? '',
    member_number: props.patient?.member_number ?? '',
    status: initialStatus.value,
    contacts: initialContacts(),
});

const submitLabel = computed(() =>
    isEditing.value ? 'Guardar cambios' : 'Registrar paciente',
);
const isPrivateCoverage = computed(
    () =>
        form.coverage_name.trim().toLocaleLowerCase('es-AR') === 'particular',
);
const fieldError = (field: string): string | undefined =>
    (form.errors as Record<string, string>)[field];

const addContact = (): void => {
    form.contacts.push(newContact());
};

const removeContact = (index: number): void => {
    form.contacts.splice(index, 1);
};

const submit = (): void => {
    form.transform((data) => ({
        ...data,
        first_name: data.first_name.trim(),
        last_name: data.last_name.trim(),
        document_number: data.document_number.trim(),
        gender: data.gender.trim(),
        phone: data.phone.trim(),
        email: data.email.trim(),
        address: data.address.trim(),
        coverage_name: data.coverage_name.trim(),
        member_number: data.member_number.trim(),
        contacts: data.contacts.map((contact) => ({
            ...contact,
            full_name: contact.full_name.trim(),
            relationship: contact.relationship.trim(),
            phone: contact.phone.trim(),
            email: contact.email.trim(),
        })),
    }));

    if (isEditing.value && props.patient) {
        form.put(`/pacientes/${props.patient.id}`, {
            preserveScroll: true,
        });

        return;
    }

    form.post('/pacientes', {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="mx-auto flex max-w-6xl flex-col gap-6" @submit.prevent="submit">
        <div
            v-if="form.hasErrors"
            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
            role="alert"
        >
            {{
                form.errors.lock_version ??
                'Revisá los campos marcados antes de continuar.'
            }}
        </div>

        <section
            aria-labelledby="patient-identity-title"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-clinic-50 text-clinic-700 dark:bg-clinic-400/10 dark:text-clinic-300"
                    >
                        <BaseIcon name="user-round" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="patient-identity-title"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Identidad
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            Cargá los datos tal como figuran en el documento.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
                <div>
                    <label
                        for="patient-first-name"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Nombre
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-first-name"
                        v-model="form.first_name"
                        type="text"
                        autocomplete="given-name"
                        maxlength="120"
                        required
                        :aria-invalid="Boolean(form.errors.first_name)"
                        aria-describedby="patient-first-name-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <FieldError
                        id="patient-first-name-error"
                        :message="form.errors.first_name"
                    />
                </div>

                <div>
                    <label
                        for="patient-last-name"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Apellido
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-last-name"
                        v-model="form.last_name"
                        type="text"
                        autocomplete="family-name"
                        maxlength="120"
                        required
                        :aria-invalid="Boolean(form.errors.last_name)"
                        aria-describedby="patient-last-name-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <FieldError
                        id="patient-last-name-error"
                        :message="form.errors.last_name"
                    />
                </div>

                <div>
                    <label
                        for="patient-document"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        DNI
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-document"
                        v-model="form.document_number"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        minlength="7"
                        maxlength="9"
                        pattern="[0-9]{7,9}"
                        required
                        :aria-invalid="Boolean(form.errors.document_number)"
                        aria-describedby="patient-document-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                        placeholder="7 a 9 dígitos, sin puntos"
                    />
                    <FieldError
                        id="patient-document-error"
                        :message="form.errors.document_number"
                    />
                </div>

                <div>
                    <label
                        for="patient-birth-date"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Fecha de nacimiento
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-birth-date"
                        v-model="form.birth_date"
                        type="date"
                        autocomplete="bday"
                        required
                        :aria-invalid="Boolean(form.errors.birth_date)"
                        aria-describedby="patient-birth-date-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <FieldError
                        id="patient-birth-date-error"
                        :message="form.errors.birth_date"
                    />
                </div>

                <div>
                    <label
                        for="patient-registered-sex"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Sexo registral
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="patient-registered-sex"
                        v-model="form.registered_sex"
                        required
                        :aria-invalid="Boolean(form.errors.registered_sex)"
                        aria-describedby="patient-registered-sex-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    >
                        <option value="" disabled>Seleccioná una opción</option>
                        <option
                            v-for="option in registeredSexOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <FieldError
                        id="patient-registered-sex-error"
                        :message="form.errors.registered_sex"
                    />
                </div>

                <div>
                    <label
                        for="patient-gender"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Género
                    </label>
                    <input
                        id="patient-gender"
                        v-model="form.gender"
                        type="text"
                        autocomplete="off"
                        maxlength="100"
                        :aria-invalid="Boolean(form.errors.gender)"
                        aria-describedby="patient-gender-help patient-gender-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                        placeholder="Opcional"
                    />
                    <p
                        id="patient-gender-help"
                        class="pt-1.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        Identidad de género informada por el paciente.
                    </p>
                    <FieldError
                        id="patient-gender-error"
                        :message="form.errors.gender"
                    />
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label
                        for="patient-status"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Estado
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <select
                        v-if="editableStatusOptions.length > 1"
                        id="patient-status"
                        v-model="form.status"
                        required
                        :aria-invalid="Boolean(form.errors.status)"
                        aria-describedby="patient-status-help patient-status-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 sm:max-w-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    >
                        <option
                            v-for="option in editableStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div
                        v-else
                        id="patient-status"
                        class="mt-2 flex min-h-11 w-full items-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-medium text-slate-700 sm:max-w-sm dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200"
                    >
                        {{ editableStatusOptions[0]?.label ?? 'Activo' }}
                    </div>
                    <input v-model="form.status" type="hidden" name="status" />
                    <p
                        id="patient-status-help"
                        class="pt-1.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        El archivado se realiza desde la ficha para conservar la
                        trazabilidad.
                    </p>
                    <FieldError
                        id="patient-status-error"
                        :message="form.errors.status"
                    />
                </div>
            </div>
        </section>

        <section
            aria-labelledby="patient-contact-title"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300"
                    >
                        <BaseIcon name="contact" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="patient-contact-title"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Contacto y cobertura
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            Datos administrativos para comunicarse y validar la
                            cobertura.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                    <label
                        for="patient-phone"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Teléfono
                    </label>
                    <input
                        id="patient-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        maxlength="32"
                        :aria-invalid="Boolean(form.errors.phone)"
                        aria-describedby="patient-phone-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                        placeholder="+54 11 5555 5555"
                    />
                    <FieldError
                        id="patient-phone-error"
                        :message="form.errors.phone"
                    />
                </div>

                <div>
                    <label
                        for="patient-email"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Correo electrónico
                        <span v-if="!isEditing" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        :required="!isEditing"
                        :aria-invalid="Boolean(form.errors.email)"
                        aria-describedby="patient-email-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <FieldError
                        id="patient-email-error"
                        :message="form.errors.email"
                    />
                    <p
                        v-if="!isEditing"
                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Enviaremos a este correo el enlace de registro de uso único.
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label
                        for="patient-address"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Domicilio
                    </label>
                    <input
                        id="patient-address"
                        v-model="form.address"
                        type="text"
                        autocomplete="street-address"
                        maxlength="1000"
                        :aria-invalid="Boolean(form.errors.address)"
                        aria-describedby="patient-address-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <FieldError
                        id="patient-address-error"
                        :message="form.errors.address"
                    />
                </div>

                <div>
                    <label
                        for="patient-coverage"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Cobertura
                        <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="patient-coverage"
                        v-model="form.coverage_name"
                        type="text"
                        autocomplete="off"
                        maxlength="120"
                        required
                        :aria-invalid="Boolean(form.errors.coverage_name)"
                        aria-describedby="patient-coverage-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                        placeholder="Particular u obra social"
                    />
                    <FieldError
                        id="patient-coverage-error"
                        :message="form.errors.coverage_name"
                    />
                </div>

                <div>
                    <label
                        for="patient-member-number"
                        class="text-sm font-medium text-slate-800 dark:text-slate-200"
                    >
                        Número de afiliado
                        <span
                            v-if="!isPrivateCoverage"
                            class="text-rose-600"
                            aria-hidden="true"
                            >*</span
                        >
                    </label>
                    <input
                        id="patient-member-number"
                        v-model="form.member_number"
                        type="text"
                        autocomplete="off"
                        maxlength="80"
                        :required="!isPrivateCoverage"
                        :aria-invalid="Boolean(form.errors.member_number)"
                        aria-describedby="patient-member-number-help patient-member-number-error"
                        class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                    />
                    <p
                        id="patient-member-number-help"
                        class="pt-1.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        No es necesario para pacientes particulares.
                    </p>
                    <FieldError
                        id="patient-member-number-error"
                        :message="form.errors.member_number"
                    />
                </div>
            </div>
        </section>

        <section
            aria-labelledby="patient-related-contacts-title"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6 dark:border-slate-800"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300"
                    >
                        <BaseIcon name="users" class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="patient-related-contacts-title"
                            class="font-semibold text-slate-950 dark:text-white"
                        >
                            Contactos relacionados
                        </h2>
                        <p class="pt-1 text-sm text-slate-500 dark:text-slate-400">
                            En menores debe existir al menos un representante
                            legal.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-clinic-200 bg-clinic-50 px-3 text-sm font-semibold text-clinic-700 transition hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-clinic-800 dark:bg-clinic-950/40 dark:text-clinic-300 dark:hover:bg-clinic-950/70"
                    @click="addContact"
                >
                    <BaseIcon name="plus" class="size-4" />
                    Agregar contacto
                </button>
            </div>

            <div class="flex flex-col gap-4 p-5 sm:p-6">
                <p
                    v-if="form.contacts.length === 0"
                    class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                >
                    No hay contactos relacionados cargados.
                </p>

                <fieldset
                    v-for="(contact, index) in form.contacts"
                    :key="index"
                    class="rounded-2xl border border-slate-200 p-4 sm:p-5 dark:border-slate-800"
                >
                    <legend class="sr-only">Contacto {{ index + 1 }}</legend>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            Contacto {{ index + 1 }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 dark:text-rose-300 dark:hover:bg-rose-950/30"
                            :aria-label="`Quitar contacto ${index + 1}`"
                            @click="removeContact(index)"
                        >
                            <BaseIcon name="close" class="size-4" />
                            Quitar
                        </button>
                    </div>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                :for="`contact-name-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Nombre completo
                                <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <input
                                :id="`contact-name-${index}`"
                                v-model="contact.full_name"
                                type="text"
                                autocomplete="name"
                                maxlength="255"
                                required
                                :aria-invalid="
                                    Boolean(
                                        fieldError(`contacts.${index}.full_name`),
                                    )
                                "
                                :aria-describedby="`contact-name-error-${index}`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                            />
                            <FieldError
                                :id="`contact-name-error-${index}`"
                                :message="
                                    fieldError(`contacts.${index}.full_name`)
                                "
                            />
                        </div>

                        <div>
                            <label
                                :for="`contact-relationship-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Vínculo
                                <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <input
                                :id="`contact-relationship-${index}`"
                                v-model="contact.relationship"
                                type="text"
                                maxlength="120"
                                required
                                :aria-invalid="
                                    Boolean(
                                        fieldError(
                                            `contacts.${index}.relationship`,
                                        ),
                                    )
                                "
                                :aria-describedby="`contact-relationship-error-${index}`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                                placeholder="Madre, hermano, tutor…"
                            />
                            <FieldError
                                :id="`contact-relationship-error-${index}`"
                                :message="
                                    fieldError(`contacts.${index}.relationship`)
                                "
                            />
                        </div>

                        <div>
                            <label
                                :for="`contact-phone-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Teléfono
                            </label>
                            <input
                                :id="`contact-phone-${index}`"
                                v-model="contact.phone"
                                type="tel"
                                autocomplete="tel"
                                maxlength="32"
                                :aria-invalid="
                                    Boolean(fieldError(`contacts.${index}.phone`))
                                "
                                :aria-describedby="`contact-phone-error-${index}`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                            />
                            <FieldError
                                :id="`contact-phone-error-${index}`"
                                :message="fieldError(`contacts.${index}.phone`)"
                            />
                        </div>

                        <div>
                            <label
                                :for="`contact-email-${index}`"
                                class="text-sm font-medium text-slate-800 dark:text-slate-200"
                            >
                                Correo electrónico
                            </label>
                            <input
                                :id="`contact-email-${index}`"
                                v-model="contact.email"
                                type="email"
                                autocomplete="email"
                                maxlength="255"
                                :aria-invalid="
                                    Boolean(fieldError(`contacts.${index}.email`))
                                "
                                :aria-describedby="`contact-email-error-${index}`"
                                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none focus:border-clinic-600 focus:ring-4 focus:ring-clinic-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-clinic-400"
                            />
                            <FieldError
                                :id="`contact-email-error-${index}`"
                                :message="fieldError(`contacts.${index}.email`)"
                            />
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 lg:grid-cols-3">
                        <label
                            class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-950/60"
                        >
                            <input
                                v-model="contact.is_emergency_contact"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-slate-300 text-clinic-600 focus:ring-clinic-600 dark:border-slate-700 dark:bg-slate-950"
                            />
                            <span>
                                <span class="block font-medium">Emergencias</span>
                                <span class="block pt-0.5 text-xs text-slate-500">
                                    Contacto prioritario ante una urgencia.
                                </span>
                            </span>
                        </label>
                        <label
                            class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-950/60"
                        >
                            <input
                                v-model="contact.is_legal_representative"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-slate-300 text-clinic-600 focus:ring-clinic-600 dark:border-slate-700 dark:bg-slate-950"
                            />
                            <span>
                                <span class="block font-medium"
                                    >Representante legal</span
                                >
                                <span class="block pt-0.5 text-xs text-slate-500">
                                    Obligatorio para pacientes menores.
                                </span>
                            </span>
                        </label>
                        <label
                            class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-950/60"
                        >
                            <input
                                v-model="contact.is_financially_responsible"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-slate-300 text-clinic-600 focus:ring-clinic-600 dark:border-slate-700 dark:bg-slate-950"
                            />
                            <span>
                                <span class="block font-medium"
                                    >Responsable de pago</span
                                >
                                <span class="block pt-0.5 text-xs text-slate-500">
                                    Referente para asuntos administrativos.
                                </span>
                            </span>
                        </label>
                    </div>

                    <FieldError
                        :message="fieldError(`contacts.${index}`)"
                        class="mt-3"
                    />
                </fieldset>

                <FieldError :message="form.errors.contacts" />
            </div>
        </section>

        <div
            class="sticky bottom-3 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:static sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none dark:border-slate-800 dark:bg-slate-900/95 sm:dark:bg-transparent"
        >
            <Link
                :href="
                    isEditing && patient
                        ? `/pacientes/${patient.id}`
                        : '/pacientes'
                "
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                <BaseIcon name="arrow-left" class="size-4" />
                Cancelar
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-clinic-600 px-5 text-sm font-semibold text-white shadow-md shadow-clinic-600/20 transition hover:bg-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <BaseIcon
                    :name="form.processing ? 'loader' : 'save'"
                    class="size-4"
                    :class="{ 'animate-spin': form.processing }"
                />
                {{ form.processing ? 'Guardando…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
