import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from '../../resources/js/App.vue';
import { installAuthenticationGuard, routes } from '../../resources/js/router';
import { isRecord, type AuthUser } from '../../resources/js/lib/api';
import {
    deleteProduct,
    listProducts,
    type Product,
    type ProductInput,
} from '../../resources/js/lib/products';

const user: AuthUser = { id: 7, name: 'Product Planner', email: 'products@example.test' };
const input: ProductInput = {
    name: 'Oats',
    brand: 'Fixture brand',
    package_weight_g: '500.125',
    calories_per_100g: '367.10',
    protein_per_100g: '13.20',
    carbohydrates_per_100g: '60.00',
    fat_per_100g: '6.50',
};
let wrapper: VueWrapper | undefined;
let signedIn: AuthUser | null;
let products: Product[];
let failure: { method: string; status: number; body: unknown } | undefined;
let hold: { method: string; response: Promise<Response> } | undefined;

function product(id = 1, name = 'Oats'): Product {
    return {
        ...input,
        id,
        name,
        created_at: '2026-11-06T00:00:00.000000Z',
        updated_at: '2026-11-06T00:00:00.000000Z',
    };
}
function json(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), { status });
}

async function mountApp(path = '/products') {
    const pinia = createPinia();
    const router = createRouter({ history: createMemoryHistory(), routes });
    installAuthenticationGuard(router, pinia);
    await router.push(path);
    await router.isReady();
    wrapper = mount(App, { attachTo: document.body, global: { plugins: [pinia, router] } });
    await flushPromises();
    return { wrapper, router };
}
async function fillForm(page: VueWrapper, values: ProductInput = input) {
    for (const [name, value] of Object.entries(values))
        await page.get(`input[name="${name}"]`).setValue(value ?? '');
}
function button(page: VueWrapper, text: string) {
    const found = page.findAll('button').find((item) => item.text() === text);
    if (!found) throw new Error(`Missing button ${text}`);
    return found;
}

const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
beforeAll(() => {
    Object.defineProperties(HTMLDialogElement.prototype, {
        showModal: {
            configurable: true,
            value(this: HTMLDialogElement) {
                this.setAttribute('open', '');
            },
        },
        close: {
            configurable: true,
            value(this: HTMLDialogElement) {
                this.removeAttribute('open');
            },
        },
    });
});
afterAll(() => {
    if (originalShowModal)
        Object.defineProperty(HTMLDialogElement.prototype, 'showModal', originalShowModal);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal');
    if (originalClose) Object.defineProperty(HTMLDialogElement.prototype, 'close', originalClose);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'close');
});

beforeEach(() => {
    signedIn = user;
    products = [product()];
    failure = undefined;
    hold = undefined;
    localStorage.clear();
    document.cookie = 'XSRF-TOKEN=product%2Bcsrf; path=/';
    // JSDOM lacks dialog methods; actual focus trapping/Escape are checked in Chrome.
});

beforeEach(() => {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (path: string, options?: RequestInit) => {
            const method = options?.method ?? 'GET';
            if (path === '/api/auth/session') return json({ user: signedIn });
            if (!path.startsWith('/api/products')) throw new Error('Unexpected request');
            if (hold?.method === method) return hold.response;
            if (failure?.method === method) {
                if (failure.status === 401) signedIn = null;
                return failure.body === null
                    ? new Response('Not signed in', { status: failure.status })
                    : json(failure.body, failure.status);
            }
            const parsed: unknown = options?.body ? JSON.parse(String(options.body)) : null;
            const body = isRecord(parsed) ? parsed : {};
            if (method === 'GET') {
                const page = Number(
                    new URL(path, 'http://localhost').searchParams.get('page') ?? 1,
                );
                return json({
                    data: products.slice((page - 1) * 2, page * 2),
                    meta: {
                        current_page: page,
                        last_page: Math.max(1, Math.ceil(products.length / 2)),
                        total: products.length,
                    },
                });
            }
            if (method === 'POST' && typeof body === 'object' && body !== null) {
                const created = {
                    ...product(Math.max(0, ...products.map((item) => item.id)) + 1),
                    ...body,
                };
                products.push(created);
                return json({ data: created }, 201);
            }
            const id = Number(path.split('/').at(-1));
            if (method === 'PATCH') {
                products = products.map((item) => (item.id === id ? { ...item, ...body } : item));
                return json({ data: products.find((item) => item.id === id) });
            }
            products = products.filter((item) => item.id !== id);
            return new Response(null, { status: 204 });
        }),
    );
});

afterEach(() => {
    wrapper?.unmount();
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
    vi.unstubAllGlobals();
});

describe('Product management', () => {
    it('renders product nutrition and authenticated navigation', async () => {
        const { wrapper } = await mountApp();
        expect(wrapper.get('h1').text()).toBe('Products');
        expect(document.title).toBe('Products | Mealplaner');
        expect(wrapper.get('nav[aria-label="Main navigation"]').text()).toContain('Products');
        for (const text of [
            'Oats',
            'Fixture brand',
            '367.10 kcal',
            '13.20 g',
            '60.00 g',
            '6.50 g',
            '500.125 g',
            'Nutrition per 100 g',
        ])
            expect(wrapper.text()).toContain(text);
        expect(fetch).toHaveBeenCalledWith(
            '/api/products?page=1',
            expect.objectContaining({ credentials: 'same-origin', cache: 'no-store' }),
        );
    });
    it('redirects guests before loading private products', async () => {
        signedIn = null;
        const { wrapper, router } = await mountApp();
        expect(router.currentRoute.value.name).toBe('login');
        expect(wrapper.text()).not.toContain('Fixture brand');
        expect(
            vi.mocked(fetch).mock.calls.some(([url]) => String(url).startsWith('/api/products')),
        ).toBe(false);
    });
    it('shows empty and loading states', async () => {
        let resolve: ((response: Response) => void) | undefined;
        hold = {
            method: 'GET',
            response: new Promise((done) => {
                resolve = done;
            }),
        };
        const { wrapper } = await mountApp();
        expect(wrapper.text()).toContain('Loading products…');
        expect(button(wrapper, 'Add product').attributes('disabled')).toBeDefined();
        resolve?.(json({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } }));
        await flushPromises();
        expect(wrapper.text()).toContain('No products yet');
    });
    it('creates a product using decimal strings, CSRF and a last-page refresh', async () => {
        products.push(product(2, 'Rice'));
        const { wrapper, router } = await mountApp();
        await button(wrapper, 'Add product').trigger('click');
        await fillForm(wrapper, { ...input, name: 'New oats' });
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(fetch).toHaveBeenCalledWith(
            '/api/products',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'product+csrf' }),
                body: JSON.stringify({ ...input, name: 'New oats' }),
            }),
        );
        expect(wrapper.text()).toContain('Product created.');
        expect(wrapper.get('.product-list').text()).toContain('New oats');
        expect(wrapper.text()).toContain('Page 2 of 2');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(router.currentRoute.value.name).toBe('products');
    });
    it('validates required fields, negative values and precision before sending', async () => {
        const { wrapper } = await mountApp();
        await button(wrapper, 'Add product').trigger('click');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.get('#product-name').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#product-name-error').text()).toContain('Enter a product name');
        await fillForm(wrapper, {
            ...input,
            protein_per_100g: '-1',
            fat_per_100g: '1.001',
            package_weight_g: '0',
        });
        await wrapper.get('form').trigger('submit');
        expect(wrapper.get('#product-protein_per_100g-error').text()).toContain('0 to 100');
        expect(wrapper.get('#product-fat_per_100g-error').text()).toContain('2 decimal places');
        expect(wrapper.get('#product-weight-error').text()).toContain('positive weight');
        expect(vi.mocked(fetch).mock.calls.some(([, options]) => options?.method === 'POST')).toBe(
            false,
        );
    });
    it('shows Laravel field errors and keeps user input for correction', async () => {
        failure = {
            method: 'POST',
            status: 422,
            body: {
                errors: {
                    name: ['Choose another product name.'],
                    protein_per_100g: ['Protein must be at most 100.'],
                },
            },
        };
        const { wrapper } = await mountApp();
        await button(wrapper, 'Add product').trigger('click');
        await fillForm(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('#product-name-error').text()).toBe('Choose another product name.');
        expect(wrapper.get('#product-protein_per_100g-error').text()).toContain('at most 100');
        expect(wrapper.get<HTMLInputElement>('#product-name').element.value).toBe('Oats');
        expect(wrapper.get('#product-name').attributes('aria-describedby')).toBe(
            'product-name-error',
        );
    });
    it('prefills editing fields and clears optional information without a reload', async () => {
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Edit Oats"]').trigger('click');
        expect(wrapper.get<HTMLInputElement>('#product-weight').element.value).toBe('500.125');
        await fillForm(wrapper, {
            ...input,
            name: 'Updated oats',
            brand: null,
            package_weight_g: null,
        });
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(fetch).toHaveBeenCalledWith(
            '/api/products/1',
            expect.objectContaining({
                method: 'PATCH',
                body: JSON.stringify({
                    ...input,
                    name: 'Updated oats',
                    brand: null,
                    package_weight_g: null,
                }),
            }),
        );
        expect(wrapper.get('.product-list').text()).toContain('Updated oats');
        expect(wrapper.get('.product-list').text()).not.toContain('500.125 g');
        expect(wrapper.text()).toContain('Product updated.');
    });
    it('cancels an edit without writing', async () => {
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Edit Oats"]').trigger('click');
        await button(wrapper, 'Cancel').trigger('click');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(vi.mocked(fetch).mock.calls.some(([, options]) => options?.method === 'PATCH')).toBe(
            false,
        );
    });
    it('prevents duplicate submissions while a save is pending', async () => {
        let resolve: ((response: Response) => void) | undefined;
        hold = {
            method: 'POST',
            response: new Promise((done) => {
                resolve = done;
            }),
        };
        const { wrapper } = await mountApp();
        await button(wrapper, 'Add product').trigger('click');
        await fillForm(wrapper);
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');
        expect(button(wrapper, 'Saving…').attributes('disabled')).toBeDefined();
        expect(
            vi.mocked(fetch).mock.calls.filter(([, options]) => options?.method === 'POST'),
        ).toHaveLength(1);
        resolve?.(json({ data: product(2) }, 201));
        await flushPromises();
    });
    it('requires confirmation and supports cancellation and Escape', async () => {
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Delete Oats"]').trigger('click');
        expect(wrapper.get('dialog').attributes('open')).toBeDefined();
        expect(wrapper.get('dialog').text()).toContain('This cannot be undone.');
        await button(wrapper, 'Keep product').trigger('click');
        expect(wrapper.find('dialog').exists()).toBe(false);
        await wrapper.get('button[aria-label="Delete Oats"]').trigger('click');
        await wrapper.get('dialog').trigger('cancel');
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(
            vi.mocked(fetch).mock.calls.some(([, options]) => options?.method === 'DELETE'),
        ).toBe(false);
    });
    it('deletes only after confirmation and returns from an emptied final page', async () => {
        products.push(product(2, 'Rice'), product(3, 'Milk'));
        const { wrapper } = await mountApp();
        await button(wrapper, 'Next').trigger('click');
        await flushPromises();
        await wrapper.get('button[aria-label="Delete Milk"]').trigger('click');
        await button(wrapper, 'Delete product').trigger('click');
        await flushPromises();
        expect(fetch).toHaveBeenCalledWith(
            '/api/products/3',
            expect.objectContaining({
                method: 'DELETE',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'product+csrf' }),
            }),
        );
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(wrapper.text()).toContain('Product deleted.');
        expect(wrapper.text()).toContain('Page 1 of 1');
        expect(wrapper.get('.product-list').text()).not.toContain('Milk');
    });
    it('shows deletion errors in the dialog and prevents duplicate deletion', async () => {
        let resolve: ((response: Response) => void) | undefined;
        hold = {
            method: 'DELETE',
            response: new Promise((done) => {
                resolve = done;
            }),
        };
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Delete Oats"]').trigger('click');
        await button(wrapper, 'Delete product').trigger('click');
        await wrapper.get('dialog').trigger('cancel');
        expect(wrapper.find('dialog').exists()).toBe(true);
        expect(button(wrapper, 'Deleting…').attributes('disabled')).toBeDefined();
        resolve?.(json({ message: 'private server diagnostic' }, 500));
        await flushPromises();
        expect(wrapper.get('dialog').text()).toContain('Unable to complete');
        expect(wrapper.text()).not.toContain('private server diagnostic');
        expect(products).toHaveLength(1);
    });
    it('paginates with accessible boundary controls', async () => {
        products.push(product(2, 'Rice'), product(3, 'Milk'));
        const { wrapper } = await mountApp();
        expect(button(wrapper, 'Previous').attributes('disabled')).toBeDefined();
        await button(wrapper, 'Next').trigger('click');
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Milk');
        expect(wrapper.get('.product-list').text()).not.toContain('Oats');
        expect(button(wrapper, 'Next').attributes('disabled')).toBeDefined();
        await button(wrapper, 'Previous').trigger('click');
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Oats');
    });
    it('retries failed loads without exposing server diagnostics', async () => {
        failure = { method: 'GET', status: 503, body: { message: 'sensitive database message' } };
        const { wrapper } = await mountApp();
        expect(wrapper.text()).toContain('Unable to complete the request');
        expect(wrapper.text()).not.toContain('sensitive database message');
        failure = undefined;
        await button(wrapper, 'Retry loading products').trigger('click');
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Oats');
    });
    it('handles expired sessions even when the error body is not JSON', async () => {
        failure = { method: 'GET', status: 401, body: null };
        // The mock expires the session when the API returns 401.
        const { wrapper, router } = await mountApp();
        expect(wrapper.find('.product-list').exists()).toBe(false);
        expect(router.currentRoute.value.name).toBe('login');
    });
    it('refreshes CSRF credentials without automatically replaying a mutation', async () => {
        failure = { method: 'PATCH', status: 419, body: {} };
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Edit Oats"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Your session changed');
        expect(
            vi.mocked(fetch).mock.calls.filter(([, options]) => options?.method === 'PATCH'),
        ).toHaveLength(1);
    });
    it('keeps successful creation distinct from a failed list refresh', async () => {
        const { wrapper } = await mountApp();
        await button(wrapper, 'Add product').trigger('click');
        await fillForm(wrapper);
        failure = { method: 'GET', status: 503, body: {} };
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Product created.');
        expect(wrapper.text()).toContain('Retry loading products');
        expect(wrapper.find('form').exists()).toBe(false);
    });
    it('rejects malformed product data and handles network errors', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(json({ data: [{ id: 1 }], meta: {} }));
        await expect(listProducts()).rejects.toThrow('Unexpected product list');
        vi.mocked(fetch).mockRejectedValueOnce(new TypeError('offline'));
        await expect(deleteProduct(1)).rejects.toThrow('offline');
    });
});
