<script setup lang="ts">
import { nextTick, ref, useTemplateRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ThemeToggle from './components/ThemeToggle.vue';
import { usePwa } from './composables/usePwa';
import { useAuthStore } from './stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const signingOut = ref(false);
const logoutError = ref('');
const mainContent = useTemplateRef<HTMLElement>('mainContent');
const { updateAvailable, error, update } = usePwa();

async function logout(): Promise<void> {
    if (signingOut.value) return;
    signingOut.value = true;
    logoutError.value = '';
    try {
        await auth.submit('logout', {});
        await router.replace('/login');
    } catch {
        logoutError.value = 'Unable to log out. Please try again.';
        // Do not leave private content visible after an expired or invalidated session.
        if (!auth.user) await router.replace('/login');
    } finally {
        signingOut.value = false;
    }
}

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
                <template v-if="auth.user">
                    <RouterLink to="/account" active-class="active">Account</RouterLink>
                    <button
                        type="button"
                        class="text-button"
                        :disabled="signingOut"
                        @click="logout"
                    >
                        {{ signingOut ? 'Logging out…' : 'Log out' }}
                    </button>
                </template>
                <template v-else>
                    <RouterLink to="/login" active-class="active">Log in</RouterLink>
                    <RouterLink to="/register" active-class="active">Register</RouterLink>
                </template>
            </nav>
            <ThemeToggle />
        </header>
        <main id="main-content" ref="mainContent" tabindex="-1">
            <p v-if="auth.loading" class="notice" role="status">Checking your session…</p>
            <p v-if="auth.sessionError" class="notice" role="alert">{{ auth.sessionError }}</p>
            <p v-if="logoutError" class="notice" role="alert">{{ logoutError }}</p>
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
