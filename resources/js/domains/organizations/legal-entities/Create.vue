<script setup lang="ts">
import LegalEntityForm from '@/domains/organizations/components/LegalEntityForm.vue';
import type { OrganizationOption } from '@/domains/organizations/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ statusOptions: OrganizationOption[] }>();
</script>

<template>
    <Head title="Nueva razón social" />
    <AppShell
        title="Nueva razón social"
        eyebrow="Organización"
        description="Registrá la entidad legal que respaldará la facturación y operación de sus sedes."
    >
        <LegalEntityForm :status-options="statusOptions" />
    </AppShell>
</template>
