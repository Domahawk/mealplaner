import { ref } from 'vue';
import { defineStore } from 'pinia';
import { checkHealth } from '../lib/api';

export const useConnectionStore = defineStore('connection', () => {
    const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle');
    const message = ref('Ready when you are. Run a check to connect to the API and database.');

    async function check(): Promise<void> {
        if (status.value === 'loading') return;
        status.value = 'loading';
        message.value = 'Checking the API and database…';
        try {
            await checkHealth();
            status.value = 'success';
            message.value = 'All connected. Laravel is responding and PostgreSQL is available.';
        } catch (error: unknown) {
            status.value = 'error';
            message.value =
                error instanceof Error && error.name !== 'TypeError'
                    ? error.message
                    : 'Could not reach the server. Check your connection and try again.';
        }
    }

    return { status, message, check };
});
