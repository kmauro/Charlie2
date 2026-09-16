<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        class="text-xs font-medium text-rose-600 dark:text-rose-400"
        role="alert"
    >
        {{ message }}
    </p>
</template>
