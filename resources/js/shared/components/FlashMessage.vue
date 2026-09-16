<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';
import { ref } from 'vue';

const props = defineProps<{
    message?: string;
    error?: string;
    invitationUrl?: string;
}>();

const copyStatus = ref('');

const copyInvitation = async (): Promise<void> => {
    if (!props.invitationUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.invitationUrl);
        copyStatus.value = 'Enlace copiado.';
    } catch {
        copyStatus.value = 'No se pudo copiar. Seleccioná el enlace manualmente.';
    }
};
</script>

<template>
    <div
        v-if="message || error || invitationUrl"
        class="mb-6 grid gap-3"
        aria-live="polite"
    >
        <div
            v-if="message"
            class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            role="status"
        >
            <BaseIcon
                name="check-circle"
                class="mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <p class="leading-6">{{ message }}</p>
        </div>

        <div
            v-if="error"
            class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
            role="alert"
        >
            <BaseIcon
                name="circle-alert"
                class="mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <p class="leading-6">{{ error }}</p>
        </div>

        <div
            v-if="invitationUrl"
            class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/40"
        >
            <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">
                Enlace de invitación
            </p>
            <p class="pt-1 text-xs leading-5 text-blue-800 dark:text-blue-300">
                Compartilo por un canal seguro. El enlace es personal y vence
                según la vigencia configurada.
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input
                    :value="invitationUrl"
                    readonly
                    class="min-h-11 min-w-0 flex-1 rounded-xl border border-blue-200 bg-white px-3 text-xs text-slate-700 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 dark:border-blue-800 dark:bg-slate-950 dark:text-slate-200"
                    aria-label="Enlace de invitación generado"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                    @click="copyInvitation"
                >
                    <BaseIcon name="copy" class="size-4" aria-hidden="true" />
                    Copiar enlace
                </button>
            </div>
            <p v-if="copyStatus" class="pt-2 text-xs text-blue-800 dark:text-blue-300">
                {{ copyStatus }}
            </p>
        </div>
    </div>
</template>
