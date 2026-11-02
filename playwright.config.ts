import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        ...devices['Desktop Chrome'],
        baseURL: process.env.BASE_URL || 'http://localhost:8000',
        trace: 'retain-on-failure',
        launchOptions: {
            ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
            args: process.env.PWA_TEST_ORIGIN
                ? [`--unsafely-treat-insecure-origin-as-secure=${process.env.PWA_TEST_ORIGIN}`]
                : [],
        },
    },
});
