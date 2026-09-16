import { computed, ref } from 'vue';

type Theme = 'light' | 'dark';

const storedTheme =
    typeof window !== 'undefined'
        ? (window.localStorage.getItem('clinic-theme') as Theme | null)
        : null;

const systemTheme: Theme =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';

const theme = ref<Theme>(storedTheme ?? systemTheme);

const applyTheme = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', theme.value === 'dark');
    document.documentElement.dataset.theme = theme.value;
};

applyTheme();

export function useTheme() {
    const isDark = computed(() => theme.value === 'dark');

    const toggleTheme = (): void => {
        theme.value = isDark.value ? 'light' : 'dark';
        window.localStorage.setItem('clinic-theme', theme.value);
        applyTheme();
    };

    return {
        isDark,
        toggleTheme,
    };
}
