import { test, expect } from '@playwright/test';

test('Laravel serves the SPA and the browser calls the same-origin API on demand', async ({
    page,
    request,
}) => {
    let checks = 0;
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === '/api/ready') checks++;
    });
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Mealplaner');
    await expect(page.getByText('Your food, recipes, and nutrition in one place.')).toBeVisible();
    await expect(page).toHaveTitle('Mealplaner');
    expect(checks).toBe(0);
    await page.getByRole('button', { name: 'Check server connection' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'All connected.' })).toBeVisible();
    expect(checks).toBe(1);
    await page.getByRole('link', { name: 'About', exact: true }).click();
    await expect(page).toHaveURL(/\/about$/);
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('About Mealplaner');
    await expect(page).toHaveTitle('About | Mealplaner');
    await page.goto('/unknown/nested-page');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Page not found');
    await expect(page).toHaveTitle('Page not found | Mealplaner');
    const unknownApi = await request.get('/api/missing');
    expect(unknownApi.status()).toBe(404);
    expect(unknownApi.headers()['content-type']).toContain('application/json');
});

test('development assets and the Vite client are loaded', async ({ page }) => {
    test.skip(process.env.TEST_MODE !== 'development');
    const assetResponses: string[] = [];
    page.on('response', (response) => {
        if (response.ok()) assetResponses.push(response.url());
    });
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    expect(assetResponses.some((url) => url.includes('/@vite/client'))).toBe(true);
    expect(assetResponses.some((url) => url.includes('/resources/js/app.ts'))).toBe(true);
    expect(
        await page.evaluate(() =>
            navigator.serviceWorker.getRegistrations().then((items) => items.length),
        ),
    ).toBe(0);
});

test('production PWA installs, precaches assets, and reopens a client route offline', async ({
    page,
    context,
    request,
}) => {
    test.skip(process.env.TEST_MODE !== 'production');
    const manifestResponse = await request.get('/build/manifest.webmanifest');
    expect(manifestResponse.ok()).toBe(true);
    const manifest = (await manifestResponse.json()) as {
        scope: string;
        display: string;
        icons: { src: string }[];
    };
    expect(manifest.scope).toBe('/');
    expect(manifest.display).toBe('standalone');
    for (const icon of manifest.icons) expect((await request.get(icon.src)).ok()).toBe(true);
    const worker = await request.get('/sw.js');
    expect(worker.ok()).toBe(true);
    expect(worker.headers()['content-type']).toContain('javascript');

    await page.goto('/');
    await page.evaluate(async () => {
        await navigator.serviceWorker.ready;
    });
    await expect
        .poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller)))
        .toBe(true);
    const cachedUrls = await page.evaluate(async () => {
        const keys = await caches.keys();
        const urls: string[] = [];
        for (const key of keys) {
            const cache = await caches.open(key);
            urls.push(...(await cache.keys()).map((request) => request.url));
        }
        return urls;
    });
    expect(cachedUrls.some((url) => url.includes('offline.html'))).toBe(true);
    expect(cachedUrls.some((url) => /\/build\/assets\/.+\.js/.test(url))).toBe(true);
    expect(cachedUrls.some((url) => url.includes('/api/'))).toBe(false);

    await context.setOffline(true);
    await page.goto('/about');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('About Mealplaner');
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Mealplaner');
    await page.getByRole('button', { name: 'Check server connection' }).click();
    await expect(
        page.getByRole('status').filter({ hasText: 'Could not reach the server' }),
    ).toBeVisible();
    // Private server routes must not be replaced by the public offline shell.
    await expect(page.goto('/account')).rejects.toThrow();
    await expect(page.goto('/products')).rejects.toThrow();
    await context.setOffline(false);
});
