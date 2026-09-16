<script setup lang="ts">
import LegalEntityForm from '@/domains/organizations/components/LegalEntityForm.vue';
import type {
    LegalEntity,
    OrganizationOption,
} from '@/domains/organizations/types';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    legalEntity: LegalEntity;
    statusOptions: OrganizationOption[];
}>();
</script>

<template>
    <Head :title="`Editar ${legalEntity.business_name}`" />
    <AppShell
        :title="`Editar ${legalEntity.business_name}`"
        eyebrow="Organización"
        description="Actualizá los datos fiscales y el estado administrativo de la entidad."
    >
        <LegalEntityForm
            :legal-entity="legalEntity"
            :status-options="statusOptions"
        />
    </AppShell>
</template>
