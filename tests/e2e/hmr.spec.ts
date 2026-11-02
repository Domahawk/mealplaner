import { readFile, writeFile } from 'node:fs/promises';
import { test, expect } from '@playwright/test';

test('Vue and CSS changes reach the browser through HMR without navigation', async ({ page }) => {
    test.skip(process.env.TEST_MODE !== 'development');
    const view = new URL('../../resources/js/views/WelcomeView.vue', import.meta.url);
    const css = new URL('../../resources/css/app.css', import.meta.url);
    const originalView = await readFile(view, 'utf8');
    const originalCss = await readFile(css, 'utf8');
    let navigations = 0;
    page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame()) navigations++;
    });
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const before = navigations;
    try {
        await writeFile(view, originalView.replace('Make it yours.', 'HMR is working.'));
        await expect(page.getByRole('heading', { level: 1 })).toContainText('HMR is working.');
        await writeFile(css, originalCss + '\n.hero { opacity: 0.81; }\n');
        await expect(page.locator('.hero')).toHaveCSS('opacity', '0.81');
        expect(navigations).toBe(before);
    } finally {
        await writeFile(view, originalView);
        await writeFile(css, originalCss);
    }
});
