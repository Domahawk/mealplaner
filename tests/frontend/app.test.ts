import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from '../../resources/js/App.vue';
import { installAuthenticationGuard, routes } from '../../resources/js/router';
import { useThemeStore } from '../../resources/js/stores/theme';

let wrapper: VueWrapper | undefined;
const healthFetch = vi.fn<typeof fetch>();

async function mountApp() {
    const pinia = createPinia();
    setActivePinia(pinia);
    const router = createRouter({ history: createMemoryHistory(), routes });
    installAuthenticationGuard(router, pinia);
    await router.push('/');
    await router.isReady();
    wrapper = mount(App, { attachTo: document.body, global: { plugins: [pinia, router] } });
    await flushPromises();
    return { wrapper, router, pinia };
}

beforeEach(() => {
    localStorage.clear();
    document.documentElement.removeAttribute('data-theme');
    healthFetch.mockReset();
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL, init?: RequestInit) =>
            input === '/api/auth/session'
                ? Promise.resolve(new Response(JSON.stringify({ user: null })))
                : healthFetch(input, init),
        ),
    );
});

afterEach(() => {
    wrapper?.unmount();
    vi.unstubAllGlobals();
});

describe('Mealplaner application', () => {
    it('renders the Mealplaner home page without issuing a network check', async () => {
        const { wrapper } = await mountApp();
        expect(wrapper.get('.brand').text()).toBe('Mealplaner');
        expect(wrapper.get('main h1').text()).toBe('Mealplaner');
        expect(wrapper.text()).toContain('Your food, recipes, and nutrition in one place.');
        expect(wrapper.text()).toContain('initial development stage');
        expect(document.title).toBe('Mealplaner');
        expect(wrapper.text()).toContain('Not checked');
        expect(healthFetch).not.toHaveBeenCalled();
    });

    it('navigates between Vue Router views', async () => {
        const { wrapper, router } = await mountApp();
        await wrapper.get('a[href="/about"]').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.path).toBe('/about');
        expect(wrapper.get('main h1').text()).toBe('About Mealplaner');
        expect(document.title).toBe('About | Mealplaner');
        expect(document.activeElement).toBe(wrapper.get('main').element);
        await router.push('/missing');
        await flushPromises();
        expect(wrapper.get('main h1').text()).toBe('Page not found');
        expect(document.title).toBe('Page not found | Mealplaner');
        await wrapper.get('main .inline-link').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.path).toBe('/');
        expect(document.title).toBe('Mealplaner');
    });

    it('switches and persists an explicit theme, then restores system preference', async () => {
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Switch to dark theme"]').trigger('click');
        expect(document.documentElement.dataset.theme).toBe('dark');
        expect(localStorage.getItem('theme')).toBe('dark');
        await wrapper.get('button[aria-label="Switch to light theme"]').trigger('click');
        expect(document.documentElement.dataset.theme).toBe('light');
        expect(localStorage.getItem('theme')).toBe('light');
        await wrapper.get('.system-theme').trigger('click');
        expect(localStorage.getItem('theme')).toBeNull();
    });

    it('uses a saved preference when the application reopens', async () => {
        localStorage.setItem('theme', 'dark');
        await mountApp();
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('follows the system until an explicit preference is set', async () => {
        vi.mocked(window.matchMedia).mockReturnValueOnce({
            ...window.matchMedia('(prefers-color-scheme: dark)'),
            matches: true,
        });
        const { pinia } = await mountApp();
        const theme = useThemeStore(pinia);
        expect(theme.preference).toBe('system');
        expect(theme.dark).toBe(true);
    });

    it('checks the same-origin API when requested and displays success', async () => {
        healthFetch.mockResolvedValue(
            new Response(JSON.stringify({ status: 'ok', database: 'connected' })),
        );
        const { wrapper } = await mountApp();
        await wrapper.get('.primary-button').trigger('click');
        await flushPromises();
        expect(healthFetch).toHaveBeenCalledWith(
            '/api/ready',
            expect.objectContaining({ cache: 'no-store' }),
        );
        expect(wrapper.text()).toContain('All connected.');
        expect(wrapper.get('.status-badge').text()).toBe('Connected');
    });

    it('shows loading state and prevents duplicate requests', async () => {
        let resolve: ((value: Response) => void) | undefined;
        healthFetch.mockReturnValue(
            new Promise<Response>((complete) => {
                resolve = complete;
            }),
        );
        const { wrapper } = await mountApp();
        await wrapper.get('.primary-button').trigger('click');
        expect(wrapper.get('.primary-button').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Checking the API and database');
        resolve?.(new Response(JSON.stringify({ status: 'ok', database: 'connected' })));
        await flushPromises();
        expect(healthFetch).toHaveBeenCalledTimes(1);
    });

    it('handles a failed database response without exposing server details', async () => {
        healthFetch.mockResolvedValue(
            new Response(JSON.stringify({ message: 'secret connection details' }), { status: 503 }),
        );
        const { wrapper } = await mountApp();
        await wrapper.get('.primary-button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('the database is unavailable');
        expect(wrapper.text()).not.toContain('secret connection details');
    });

    it('handles network failure and supports retry', async () => {
        healthFetch.mockRejectedValueOnce(new TypeError('Failed to fetch'));
        const { wrapper } = await mountApp();
        await wrapper.get('.primary-button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Could not reach the server');
        healthFetch.mockResolvedValue(
            new Response(JSON.stringify({ status: 'ok', database: 'connected' })),
        );
        await wrapper.get('.primary-button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('All connected.');
    });

    it('rejects malformed success responses', async () => {
        healthFetch.mockResolvedValue(new Response(JSON.stringify({ status: 'unexpected' })));
        const { wrapper } = await mountApp();
        await wrapper.get('.primary-button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('unexpected response');
    });
});
