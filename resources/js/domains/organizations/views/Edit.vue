<script setup lang="ts">
import LocationForm from '@/domains/organizations/components/LocationForm.vue';
import type {
    LegalEntityOption,
    Location,
    OrganizationOption,
} from '@/domains/organizations/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    location: Location;
    legalEntities: LegalEntityOption[];
    statusOptions: OrganizationOption[];
    serviceOptions: OrganizationOption[];
}>();
</script>

<template>
    <Head :title="`Editar ${location.name}`" />

    <AppShell
        :title="`Editar ${location.name}`"
        eyebrow="Organización"
        description="Actualizá la configuración administrativa y operativa de la sede."
    >
        <LocationForm
            :location="location"
            :legal-entities="legalEntities"
            :status-options="statusOptions"
            :service-options="serviceOptions"
        />
    </AppShell>
</template>
