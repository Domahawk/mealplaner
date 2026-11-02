import { onMounted, onUnmounted, ref } from 'vue';

export function usePwa() {
    const updateAvailable = ref(false);
    const error = ref('');
    let registration: ServiceWorkerRegistration | undefined;
    let reloading = false;

    function onControllerChange(): void {
        if (reloading) window.location.reload();
    }

    function update(): void {
        if (!registration?.waiting) return;
        reloading = true;
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    }

    onMounted(async () => {
        if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return;
        navigator.serviceWorker.addEventListener('controllerchange', onControllerChange);
        try {
            registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            });
            updateAvailable.value = Boolean(registration.waiting);
            registration.addEventListener('updatefound', () => {
                const worker = registration?.installing;
                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        updateAvailable.value = true;
                    }
                });
            });
        } catch {
            error.value =
                'Offline support is unavailable. You can continue using the application online.';
        }
    });

    onUnmounted(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.removeEventListener('controllerchange', onControllerChange);
        }
    });

    return { updateAvailable, error, update };
}
