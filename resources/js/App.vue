<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import ThemeToggle from './components/ThemeToggle.vue';
import { usePwa } from './composables/usePwa';

const route = useRoute();
const mainContent = useTemplateRef<HTMLElement>('mainContent');
const { updateAvailable, error, update } = usePwa();

watch(
    () => route.path,
    async (_path, previousPath) => {
        document.title = typeof route.meta.title === 'string' ? route.meta.title : 'Mealplaner';

        if (previousPath !== undefined) {
            await nextTick();
            mainContent.value?.focus({ preventScroll: true });
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="app-shell">
        <a class="skip-link" href="#main-content">Skip to main content</a>
        <header class="site-header">
            <RouterLink class="brand" to="/" aria-label="Mealplaner home">
                <svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect width="32" height="32" rx="9" fill="currentColor" />
                    <path
                        d="M10 22V10l6 8 6-8v12"
                        stroke="var(--surface)"
                        stroke-width="2.2"
                        stroke-linejoin="round"
                    />
                </svg>
                <span>Mealplaner</span>
            </RouterLink>
            <nav aria-label="Main navigation">
                <RouterLink to="/" exact-active-class="active">Home</RouterLink>
                <RouterLink to="/about" active-class="active">About</RouterLink>
            </nav>
            <ThemeToggle />
        </header>
        <main id="main-content" ref="mainContent" tabindex="-1">
            <div v-if="updateAvailable" class="notice" role="status">
                <span>A new version is ready.</span>
                <button class="text-button" type="button" @click="update">
                    Update application →
                </button>
            </div>
            <p v-if="error" class="notice" role="status">{{ error }}</p>
            <RouterView />
        </main>
        <footer class="site-footer">
            <span
                ><span class="footer-dot" aria-hidden="true"></span> Mealplaner · In
                development</span
            >
        </footer>
    </div>
</template>
