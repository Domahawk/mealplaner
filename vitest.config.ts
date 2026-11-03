import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['tests/frontend/**/*.test.ts'],
        setupFiles: ['tests/frontend/setup.ts'],
        restoreMocks: true,
        clearMocks: true,
        reporters: ['default', 'junit'],
        outputFile: { junit: 'test-results/frontend.xml' },
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: ['resources/js/**/*.d.ts'],
            reportsDirectory: 'coverage/frontend',
            reporter: ['text', 'html', 'lcov', 'json-summary'],
        },
    },
});
