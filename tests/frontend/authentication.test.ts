import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from '../../resources/js/App.vue';
import { installAuthenticationGuard, routes } from '../../resources/js/router';
import { useAuthStore } from '../../resources/js/stores/auth';
import type { AuthUser } from '../../resources/js/lib/api';

const fixtureUser: AuthUser = { id: 7, name: 'Test Planner', email: 'planner@example.test' };
let wrapper: VueWrapper | undefined;
let signedIn: AuthUser | null;
let failure: { status: number; body: unknown } | undefined;

async function mountApp(path: string) {
    const pinia = createPinia();
    const router = createRouter({ history: createMemoryHistory(), routes });
    installAuthenticationGuard(router, pinia);
    await router.push(path);
    await router.isReady();
    wrapper = mount(App, { attachTo: document.body, global: { plugins: [pinia, router] } });
    await flushPromises();
    return { wrapper, router, auth: useAuthStore(pinia) };
}

async function fillLogin(page: VueWrapper) {
    await page.get('input[name="email"]').setValue(fixtureUser.email);
    await page.get('input[name="password"]').setValue('authentication-fixture-123');
}

beforeEach(() => {
    signedIn = null;
    failure = undefined;
    localStorage.clear();
    document.cookie = 'XSRF-TOKEN=synthetic%2Bcsrf; path=/';
    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL) => {
            if (input === '/api/auth/session')
                return new Response(JSON.stringify({ user: signedIn }));
            if (failure)
                return new Response(JSON.stringify(failure.body), { status: failure.status });
            if (input === '/api/auth/logout') {
                signedIn = null;
                return new Response(null, { status: 204 });
            }
            signedIn = fixtureUser;
            return new Response(JSON.stringify({ user: fixtureUser }), {
                status: input === '/api/auth/register' ? 201 : 200,
            });
        }),
    );
});

afterEach(() => {
    wrapper?.unmount();
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
    vi.unstubAllGlobals();
});

describe('native session authentication integration', () => {
    it('redirects guests from private navigation to login and preserves public navigation', async () => {
        const { wrapper, router } = await mountApp('/account');
        expect(router.currentRoute.value.name).toBe('login');
        expect(wrapper.get('h1').text()).toBe('Log in');
        expect(wrapper.text()).not.toContain(fixtureUser.email);
        await router.push('/about');
        expect(wrapper.get('h1').text()).toBe('About Mealplaner');
    });

    it('registers, sends same-origin CSRF credentials and opens the account page', async () => {
        const { wrapper, router, auth } = await mountApp('/register');
        await wrapper.get('input[name="name"]').setValue(fixtureUser.name);
        await fillLogin(wrapper);
        await wrapper
            .get('input[name="password_confirmation"]')
            .setValue('authentication-fixture-123');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('account');
        expect(wrapper.text()).toContain(fixtureUser.email);
        expect(auth.user).toEqual(fixtureUser);
        expect(fetch).toHaveBeenCalledWith(
            '/api/auth/register',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'synthetic+csrf' }),
                body: JSON.stringify({
                    email: fixtureUser.email,
                    password: 'authentication-fixture-123',
                    name: fixtureUser.name,
                    password_confirmation: 'authentication-fixture-123',
                }),
            }),
        );
        expect(localStorage.length).toBe(0);
    });

    it('logs in and restores identity in a new application using the server session', async () => {
        const first = await mountApp('/login');
        await fillLogin(first.wrapper);
        await first.wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(first.router.currentRoute.value.name).toBe('account');
        first.wrapper.unmount();
        const restored = await mountApp('/account');
        expect(restored.auth.user).toEqual(fixtureUser);
        expect(restored.wrapper.text()).toContain(fixtureUser.email);
        expect(document.title).toBe('Your account | Mealplaner');
        await restored.router.push('/login');
        expect(restored.router.currentRoute.value.name).toBe('account');
    });

    it('shows invalid credentials and clears submitted passwords without navigating', async () => {
        failure = {
            status: 422,
            body: { errors: { email: ['The provided credentials are incorrect.'] } },
        };
        const { wrapper, router, auth } = await mountApp('/login');
        await fillLogin(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('#email-error').text()).toBe('The provided credentials are incorrect.');
        expect(wrapper.get<HTMLInputElement>('#auth-password').element.value).toBe('');
        expect(wrapper.get('#auth-email').attributes('aria-invalid')).toBe('true');
        expect(document.activeElement).toBe(wrapper.get('.form-error').element);
        expect(router.currentRoute.value.name).toBe('login');
        expect(auth.user).toBeNull();
    });

    it('renders registration validation errors and clears password confirmation', async () => {
        failure = {
            status: 422,
            body: {
                errors: {
                    email: ['The email has already been taken.'],
                    password: ['The password confirmation does not match.'],
                },
            },
        };
        const { wrapper } = await mountApp('/register');
        await fillLogin(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('The email has already been taken.');
        expect(wrapper.text()).toContain('The password confirmation does not match.');
        expect(wrapper.get<HTMLInputElement>('#auth-confirmation').element.value).toBe('');
    });

    it('logs out, clears account state and rejects subsequent private navigation', async () => {
        signedIn = fixtureUser;
        const { wrapper, router, auth } = await mountApp('/account');
        await wrapper.get('nav button').trigger('click');
        await flushPromises();
        expect(auth.user).toBeNull();
        expect(router.currentRoute.value.name).toBe('login');
        expect(wrapper.text()).not.toContain(fixtureUser.email);
        expect(fetch).toHaveBeenCalledWith(
            '/api/auth/logout',
            expect.objectContaining({ method: 'POST', body: '{}' }),
        );
        await router.push('/account');
        expect(router.currentRoute.value.name).toBe('login');
    });

    it('rechecks the server before allowing navigation after session expiry', async () => {
        signedIn = fixtureUser;
        const { router, wrapper } = await mountApp('/account');
        await router.push('/about');
        signedIn = null;
        await router.push('/account');
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('login');
        expect(wrapper.text()).not.toContain(fixtureUser.email);
    });

    it('clears private account content when an API mutation rejects an expired session', async () => {
        signedIn = fixtureUser;
        const { wrapper, router, auth } = await mountApp('/account');
        signedIn = null;
        failure = { status: 401, body: { message: 'Unauthenticated.' } };

        await wrapper.get('nav button').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('login');
        expect(auth.user).toBeNull();
        expect(wrapper.get('h1').text()).toBe('Log in');
        expect(wrapper.text()).not.toContain(fixtureUser.email);
    });

    it('recovers from CSRF expiry on the next explicit submission', async () => {
        failure = { status: 419, body: {} };
        const { wrapper, auth } = await mountApp('/login');
        await fillLogin(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Your session has expired. Please try again.');
        expect(auth.initialized).toBe(false);
        failure = undefined;
        await fillLogin(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('h1').text()).toBe('Your account');
    });

    it('rejects forged local account state when the server still considers the visitor a guest', async () => {
        const { wrapper, router, auth } = await mountApp('/about');
        auth.user = fixtureUser;
        auth.initialized = true;
        localStorage.setItem('auth', JSON.stringify({ user: fixtureUser }));

        await router.push('/account');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('login');
        expect(auth.user).toBeNull();
        expect(wrapper.text()).not.toContain(fixtureUser.email);
    });

    it('uses the current server identity instead of a client-supplied user selector', async () => {
        signedIn = fixtureUser;
        const { wrapper, router, auth } = await mountApp('/about');
        const otherUser = { id: 8, name: 'Other Fixture', email: 'other@example.test' };
        signedIn = otherUser;

        await router.push('/account?user_id=' + fixtureUser.id);
        await flushPromises();

        expect(auth.user).toEqual(otherUser);
        expect(wrapper.text()).toContain(otherUser.email);
        expect(wrapper.text()).not.toContain(fixtureUser.email);
    });

    it.each([429, 500])(
        'shows a safe error for status %s without rendering server diagnostics',
        async (status) => {
            failure = { status, body: { message: 'private server diagnostic fixture' } };
            const { wrapper } = await mountApp('/login');
            await fillLogin(wrapper);
            await wrapper.get('form').trigger('submit');
            await flushPromises();
            expect(wrapper.text()).not.toContain('private server diagnostic fixture');
            expect(wrapper.get('.form-error').text()).toContain(
                status === 429 ? 'Too many attempts' : 'Unable to complete authentication',
            );
        },
    );

    it('does not render the account when session verification fails, then retries', async () => {
        const backend = vi.mocked(fetch).getMockImplementation();
        vi.mocked(fetch).mockRejectedValue(new TypeError('Network unavailable'));
        const { wrapper, router } = await mountApp('/account');
        expect(router.currentRoute.value.name).toBe('login');
        expect(wrapper.text()).toContain('Unable to verify your session.');
        if (backend) vi.mocked(fetch).mockImplementation(backend);
        await fillLogin(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('h1').text()).toBe('Your account');
    });
});
