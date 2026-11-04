import { ref } from 'vue';
import { defineStore } from 'pinia';
import { AuthenticationError, authRequest, type AuthUser } from '../lib/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null);
    const initialized = ref(false);
    const loading = ref(false);
    const sessionError = ref('');
    let pending: Promise<void> | undefined;

    function loadSession(refresh = false): Promise<void> {
        if (pending) return pending;
        if (initialized.value && !refresh) return Promise.resolve();
        initialized.value = false;
        loading.value = true;
        sessionError.value = '';
        pending = authRequest('session')
            .then((account) => {
                user.value = account;
                initialized.value = true;
            })
            .catch((error: unknown) => {
                user.value = null;
                sessionError.value =
                    'Unable to verify your session. Check your connection and try again.';
                throw error;
            })
            .finally(() => {
                loading.value = false;
                pending = undefined;
            });
        return pending;
    }

    async function submit(
        action: 'login' | 'register' | 'logout',
        fields: Record<string, string>,
    ): Promise<void> {
        await loadSession();
        try {
            user.value = await authRequest(action, fields);
        } catch (error: unknown) {
            if (error instanceof AuthenticationError && [401, 419, 409].includes(error.status)) {
                initialized.value = false;
                user.value = null;
                if (error.status === 409 && action !== 'logout') {
                    await loadSession();
                    if (user.value !== null) return;
                }
            }
            throw error;
        }
    }

    return { user, initialized, loading, sessionError, loadSession, submit };
});
