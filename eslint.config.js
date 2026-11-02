import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import vue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
            'test-results/**',
            'playwright-report/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        rules: { 'no-undef': 'off' },
        languageOptions: { parserOptions: { parser: tseslint.parser } },
    },
    {
        files: ['**/*.{js,mjs,ts,vue}'],
        languageOptions: {
            globals: {
                window: 'readonly',
                document: 'readonly',
                navigator: 'readonly',
                localStorage: 'readonly',
                fetch: 'readonly',
                console: 'readonly',
                process: 'readonly',
                URL: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                AbortController: 'readonly',
                AbortSignal: 'readonly',
                ServiceWorkerRegistration: 'readonly',
            },
        },
    },
    prettier,
);
