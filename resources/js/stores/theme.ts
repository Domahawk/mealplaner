import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';

export type ThemePreference = 'light' | 'dark' | 'system';

export const useThemeStore = defineStore('theme', () => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const systemDark = ref(media.matches);
    let saved: string | null = null;
    try {
        saved = localStorage.getItem('theme');
    } catch {
        // Theme still works when browser storage is unavailable.
    }
    const preference = ref<ThemePreference>(
        saved === 'light' || saved === 'dark' ? saved : 'system',
    );
    const dark = computed(
        () => preference.value === 'dark' || (preference.value === 'system' && systemDark.value),
    );

    function onSystemChange(event: MediaQueryListEvent): void {
        systemDark.value = event.matches;
    }
    media.addEventListener('change', onSystemChange);

    watch(
        dark,
        (enabled) => {
            document.documentElement.dataset.theme = enabled ? 'dark' : 'light';
            document.documentElement.style.colorScheme = enabled ? 'dark' : 'light';
        },
        { immediate: true },
    );

    function setPreference(value: ThemePreference): void {
        preference.value = value;
        try {
            if (value === 'system') localStorage.removeItem('theme');
            else localStorage.setItem('theme', value);
        } catch {
            // Explicit preferences remain active for this session.
        }
    }

    function toggle(): void {
        setPreference(dark.value ? 'light' : 'dark');
    }

    return { preference, dark, toggle, setPreference };
});
