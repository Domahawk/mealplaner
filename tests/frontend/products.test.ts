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
                const params = new URL(path, 'http://localhost').searchParams;
                const page = Number(params.get('page') ?? 1);
                const term = params.get('search')?.toLowerCase() ?? '';
                let matches = products.filter(
                    (item) =>
                        item.name.toLowerCase().includes(term) ||
                        item.brand?.toLowerCase().includes(term),
                );
                for (const nutrient of ['calories', 'protein', 'carbohydrates', 'fat'] as const) {
                    const minimum = params.get(`${nutrient}_min`);
                    const maximum = params.get(`${nutrient}_max`);
                    matches = matches.filter(
                        (item) =>
                            (minimum === null ||
                                Number(item[`${nutrient}_per_100g`]) >= Number(minimum)) &&
                            (maximum === null ||
                                Number(item[`${nutrient}_per_100g`]) <= Number(maximum)),
                    );
                }
                return json({
                    data: matches.slice((page - 1) * 2, page * 2),
                    meta: {
                        current_page: page,
                        last_page: Math.max(1, Math.ceil(matches.length / 2)),
                        total: matches.length,
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
    wrapper = undefined;
    vi.useRealTimers();
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
    it('restores bookmarked search, nutrition bounds and page before the first request', async () => {
        products = [
            product(1, 'Oats one'),
            product(2, 'Oats two'),
            product(3, 'Oats three'),
            product(4, 'Rice'),
        ];
        const { wrapper, router } = await mountApp(
            '/products?search=oAtS&protein_min=10&protein_max=15&page=2',
        );
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('oAtS');
        expect(wrapper.get<HTMLInputElement>('#filter-protein-min').element.value).toBe('10');
        expect(wrapper.get('details').attributes('open')).toBeDefined();
        expect(wrapper.get('.product-list').text()).toContain('Oats three');
        expect(wrapper.text()).toContain('Page 2 of 2');
        expect(router.currentRoute.value.query.page).toBe('2');
        expect(fetch).toHaveBeenCalledWith(
            '/api/products?page=2&search=oAtS&protein_min=10&protein_max=15',
            expect.anything(),
        );
    });

    it('debounces typing, encodes literal search and resets page in the URL', async () => {
        products = [product(1), product(2), product(3)];
        const { wrapper, router } = await mountApp('/products?page=2');
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        const before = vi.mocked(fetch).mock.calls.length;
        await wrapper.get('#product-search').setValue('O');
        await vi.advanceTimersByTimeAsync(200);
        await wrapper.get('#product-search').setValue('Oats & 100%');
        await vi.advanceTimersByTimeAsync(299);
        expect(fetch).toHaveBeenCalledTimes(before);
        expect(wrapper.text()).toContain('Loading products');
        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({ search: 'Oats & 100%' });
        expect(fetch).toHaveBeenCalledWith(
            '/api/products?page=1&search=Oats+%26+100%25',
            expect.anything(),
        );
        expect(wrapper.text()).toContain('No products match your search and filters');
    });

    it('combines nutrition filters with search and preserves them during pagination', async () => {
        products = [
            product(1),
            product(2),
            product(3),
            { ...product(4, 'Oats light'), protein_per_100g: '1.00' },
        ];
        const { wrapper, router } = await mountApp();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        await wrapper.get('#product-search').setValue('oats');
        await wrapper.get('#filter-protein-min').setValue('10');
        await wrapper.get('#filter-fat-max').setValue('7');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.text()).toContain('Page 1 of 2 · 3 products');
        await button(wrapper, 'Next').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({
            search: 'oats',
            protein_min: '10',
            fat_max: '7',
            page: '2',
        });
        expect(wrapper.get('.product-list').text()).not.toContain('Oats light');
        expect(fetch).toHaveBeenCalledWith(
            '/api/products?page=2&search=oats&protein_min=10&fat_max=7',
            expect.anything(),
        );
    });

    it('clears search, all ranges and pagination together', async () => {
        products.push(product(2), product(3));
        const { wrapper, router } = await mountApp('/products?search=oats&protein_min=10&page=2');
        await button(wrapper, 'Clear search and filters').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({});
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('');
        expect(wrapper.get<HTMLInputElement>('#filter-protein-min').element.value).toBe('');
        expect(wrapper.text()).toContain('Page 1 of 2');
    });

    it('restores controls and results through browser back and forward', async () => {
        products = [product(1), product(2), product(3), product(4, 'Milk')];
        const { wrapper, router } = await mountApp('/products?search=Oats');
        await button(wrapper, 'Next').trigger('click');
        await flushPromises();
        await router.push('/products?search=Milk&protein_min=0');
        await flushPromises();
        router.back();
        await flushPromises();
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('Oats');
        expect(wrapper.text()).toContain('Page 2 of 2');
        router.forward();
        await flushPromises();
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('Milk');
        expect(wrapper.get<HTMLInputElement>('#filter-protein-min').element.value).toBe('0');
        expect(wrapper.get('.product-list').text()).toContain('Milk');
    });

    it('ignores stale requests as soon as a new search is typed', async () => {
        products = [product(1), product(2), product(3, 'Milk')];
        const { wrapper } = await mountApp();
        let resolve: ((response: Response) => void) | undefined;
        hold = {
            method: 'GET',
            response: new Promise((done) => {
                resolve = done;
            }),
        };
        await button(wrapper, 'Next').trigger('click');
        await flushPromises();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        await wrapper.get('#product-search').setValue('Milk');
        hold = undefined;
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Milk');
        resolve?.(
            json({
                data: [product(99, 'Stale product')],
                meta: { current_page: 2, last_page: 2, total: 3 },
            }),
        );
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Milk');
        expect(wrapper.text()).not.toContain('Stale product');
    });

    it('shows field-specific filter errors and recovers after correction', async () => {
        const { wrapper } = await mountApp();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        failure = {
            method: 'GET',
            status: 422,
            body: { errors: { protein_max: ['Maximum protein must be at least the minimum.'] } },
        };
        await wrapper.get('#filter-protein-min').setValue('10');
        await wrapper.get('#filter-protein-max').setValue('5');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.get('#filter-protein-max').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#filter-protein-max-error').text()).toContain('at least the minimum');
        failure = undefined;
        await wrapper.get('#filter-protein-max').setValue('20');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Oats');
        expect(wrapper.get('#filter-protein-max-error').text()).toBe('');
    });

    it('does not lose zero-valued bounds or clear criteria after CRUD', async () => {
        products = [{ ...product(1, 'Water'), protein_per_100g: '0.00' }, product(2)];
        const { wrapper, router } = await mountApp('/products?protein_min=0&protein_max=0');
        expect(wrapper.get('.product-list').text()).toContain('Water');
        expect(wrapper.get('.product-list').text()).not.toContain('Oats');
        await wrapper.get('button[aria-label="Edit Water"]').trigger('click');
        await wrapper.get('#product-name').setValue('Clear water');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({ protein_min: '0', protein_max: '0' });
        expect(wrapper.get('.product-list').text()).toContain('Clear water');
    });

    it('reloads the original query when typing is reverted before debounce completes', async () => {
        const { wrapper } = await mountApp('/products?search=Oats');
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        await wrapper.get('#product-search').setValue('Oats new');
        await wrapper.get('#product-search').setValue('Oats');
        await flushPromises();
        expect(wrapper.get('.product-list').text()).toContain('Oats');
        expect(wrapper.text()).not.toContain('Loading products');
    });

    it('cancels queued searches when navigating away', async () => {
        const { wrapper, router } = await mountApp();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        await wrapper.get('#product-search').setValue('Milk');
        await router.push('/about');
        await flushPromises();
        const before = vi.mocked(fetch).mock.calls.length;
        await vi.advanceTimersByTimeAsync(1000);
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('about');
        expect(fetch).toHaveBeenCalledTimes(before);
    });
    it('does not overwrite newer typing when an earlier URL navigation finishes late', async () => {
        const { wrapper, router } = await mountApp();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        let resume: ((response: Response) => void) | undefined;
        vi.mocked(fetch).mockImplementationOnce(
            () =>
                new Promise<Response>((resolve) => {
                    resume = resolve;
                }),
        );
        await wrapper.get('#product-search').setValue('Oat');
        await vi.advanceTimersByTimeAsync(300);
        await wrapper.get('#product-search').setValue('Oats latest');
        await vi.advanceTimersByTimeAsync(100);
        resume?.(json({ user }));
        await flushPromises();
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('Oats latest');
        await vi.advanceTimersByTimeAsync(200);
        await flushPromises();
        expect(router.currentRoute.value.query.search).toBe('Oats latest');
    });
    it('preserves a trailing space when a user pauses between words', async () => {
        const { wrapper, router } = await mountApp();
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        await wrapper.get('#product-search').setValue('Rolled ');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(wrapper.get<HTMLInputElement>('#product-search').element.value).toBe('Rolled ');
        await wrapper.get('#product-search').setValue('Rolled Oats');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(router.currentRoute.value.query.search).toBe('Rolled Oats');
    });
    it('explains a recipe reference conflict without deleting or hiding the product', async () => {
        failure = {
            method: 'DELETE',
            status: 409,
            body: { message: 'This product is used in a recipe.' },
        };
        const { wrapper } = await mountApp();
        await wrapper.get('button[aria-label="Delete Oats"]').trigger('click');
        await button(wrapper, 'Delete product').trigger('click');
        await flushPromises();
        expect(wrapper.get('dialog').text()).toContain(
            'Remove it from recipes before deleting it.',
        );
        expect(products).toHaveLength(1);
        await button(wrapper, 'Keep product').trigger('click');
        expect(wrapper.get('.product-list').text()).toContain('Oats');
    });
});
