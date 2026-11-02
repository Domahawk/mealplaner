<script setup lang="ts">
import ThemeToggle from './components/ThemeToggle.vue';
import { usePwa } from './composables/usePwa';

const appName =
    document.querySelector<HTMLMetaElement>('meta[name="application-name"]')?.content ||
    import.meta.env.VITE_APP_NAME ||
    'My Application';
const { updateAvailable, error, update } = usePwa();
</script>

<template>
    <div class="app-shell">
        <header class="site-header">
            <RouterLink class="brand" to="/" :aria-label="`${appName} home`">
                <svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect width="32" height="32" rx="9" fill="currentColor" />
                    <path
                        d="M10 22V10h5.5a4 4 0 0 1 0 8H10m8-2 4 6"
                        stroke="var(--surface)"
                        stroke-width="2.2"
                        stroke-linejoin="round"
                    />
                </svg>
                <span>{{ appName }}</span>
            </RouterLink>
            <nav aria-label="Main navigation">
                <RouterLink to="/" exact-active-class="active">Overview</RouterLink>
                <RouterLink to="/about" active-class="active">About</RouterLink>
            </nav>
            <ThemeToggle />
        </header>
        <main id="main-content">
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
                ><span class="footer-dot" aria-hidden="true"></span> A foundation for your next
                chapter.</span
            >
            <span
                >Laravel <span class="footer-separator">/</span> Vue
                <span class="footer-separator">/</span> PostgreSQL</span
            >
        </footer>
    </div>
</template>
