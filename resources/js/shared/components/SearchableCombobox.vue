<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';
import { computed, nextTick, ref, watch } from 'vue';

type ComboboxValue = number | string | '';

interface ComboboxOption {
    value: number | string;
    label: string;
    description?: string;
    searchText?: string;
}

const props = withDefaults(
    defineProps<{
        inputId: string;
        options: ComboboxOption[];
        placeholder: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
        ariaDescribedby?: string;
        invalid?: boolean;
        required?: boolean;
    }>(),
    {
        searchPlaceholder: 'Buscar...',
        emptyMessage: 'No se encontraron resultados.',
        ariaDescribedby: undefined,
        invalid: false,
        required: false,
    },
);

const model = defineModel<ComboboxValue>({ required: true });
const input = ref<HTMLInputElement>();
const query = ref('');
const isOpen = ref(false);
const activeIndex = ref(0);

const normalizeSearch = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase('es-AR')
        .trim();

const selectedOption = computed(() =>
    props.options.find((option) => option.value === model.value),
);

const filteredOptions = computed(() => {
    const search = normalizeSearch(query.value);

    if (!search || query.value === selectedOption.value?.label) {
        return props.options;
    }

    return props.options.filter((option) =>
        normalizeSearch(
            [option.label, option.description, option.searchText]
                .filter(Boolean)
                .join(' '),
        ).includes(search),
    );
});

watch(
    selectedOption,
    (option) => {
        if (!isOpen.value) {
            query.value = option?.label ?? '';
        }
    },
    { immediate: true },
);

const open = async (): Promise<void> => {
    isOpen.value = true;
    activeIndex.value = 0;
    await nextTick();
    input.value?.focus();
    input.value?.select();
};

const close = (): void => {
    isOpen.value = false;
    query.value = selectedOption.value?.label ?? '';
};

const selectOption = (option: ComboboxOption): void => {
    model.value = option.value;
    query.value = option.label;
    isOpen.value = false;
};

const handleInput = (): void => {
    isOpen.value = true;
    activeIndex.value = 0;
};

const moveActiveOption = (direction: 1 | -1): void => {
    if (!isOpen.value) {
        void open();

        return;
    }

    const optionCount = filteredOptions.value.length;

    if (optionCount === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + direction + optionCount) % optionCount;
};

const selectActiveOption = (): void => {
    const option = filteredOptions.value[activeIndex.value];

    if (isOpen.value && option) {
        selectOption(option);
    }
};
</script>

<template>
    <div class="relative mt-2">
        <div class="relative">
            <BaseIcon
                name="search"
                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
            />
            <input
                :id="inputId"
                ref="input"
                v-model="query"
                type="text"
                role="combobox"
                aria-autocomplete="list"
                autocomplete="off"
                :placeholder="isOpen ? searchPlaceholder : placeholder"
                :aria-expanded="isOpen"
                :aria-controls="`${inputId}-options`"
                :aria-activedescendant="isOpen && filteredOptions[activeIndex] ? `${inputId}-option-${activeIndex}` : undefined"
                :aria-describedby="ariaDescribedby"
                :aria-invalid="invalid"
                :aria-required="required"
                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white py-2.5 pr-10 pl-10 text-sm text-slate-950 placeholder:text-slate-400 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                @focus="open"
                @input="handleInput"
                @blur="close"
                @keydown.down.prevent="moveActiveOption(1)"
                @keydown.up.prevent="moveActiveOption(-1)"
                @keydown.enter.prevent="selectActiveOption"
                @keydown.esc.prevent="close"
            />
            <button
                type="button"
                tabindex="-1"
                aria-hidden="true"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400"
                @mousedown.prevent="isOpen ? close() : open()"
            >
                <BaseIcon name="chevron-down" class="size-4 transition" :class="{ 'rotate-180': isOpen }" />
            </button>
        </div>

        <div
            v-if="isOpen"
            :id="`${inputId}-options`"
            role="listbox"
            class="absolute z-30 mt-2 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900"
        >
            <button
                v-for="(option, index) in filteredOptions"
                :id="`${inputId}-option-${index}`"
                :key="option.value"
                type="button"
                role="option"
                :aria-selected="option.value === model"
                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition"
                :class="index === activeIndex
                    ? 'bg-clinic-50 text-clinic-900 dark:bg-clinic-950/60 dark:text-clinic-100'
                    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'"
                @mousedown.prevent="selectOption(option)"
                @mouseenter="activeIndex = index"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{ option.label }}</span>
                    <span
                        v-if="option.description"
                        class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ option.description }}
                    </span>
                </span>
                <BaseIcon
                    v-if="option.value === model"
                    name="check"
                    class="size-4 shrink-0 text-clinic-600 dark:text-clinic-300"
                />
            </button>

            <p
                v-if="filteredOptions.length === 0"
                class="px-3 py-5 text-center text-sm text-slate-500 dark:text-slate-400"
            >
                {{ emptyMessage }}
            </p>
        </div>
    </div>
</template>
