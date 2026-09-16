<script setup lang="ts">
import LocationForm from '@/domains/organizations/components/LocationForm.vue';
import type {
    LegalEntityOption,
    OrganizationOption,
} from '@/domains/organizations/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    legalEntities: LegalEntityOption[];
    statusOptions: OrganizationOption[];
    serviceOptions: OrganizationOption[];
}>();
</script>

<template>
    <Head title="Nueva sede" />

    <AppShell
        title="Nueva sede"
        eyebrow="Organización"
        description="Definí la pertenencia legal, ubicación, horarios y servicios de la sede."
    >
        <LocationForm
            :legal-entities="legalEntities"
            :status-options="statusOptions"
            :service-options="serviceOptions"
        />
    </AppShell>
</template>
