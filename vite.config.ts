import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { defineConfig, loadEnv, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { VitePWA } from 'vite-plugin-pwa';

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (character) => {
        const entities: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        };
        return entities[character] ?? character;
    });
}

// Blade is the online entry. Generate a public, secret-free shell from the same entry chunk.
function offlineShell(appName: string): Plugin {
    return {
        name: 'laravel-offline-shell',
        apply: 'build',
        enforce: 'post',
        generateBundle(_options, bundle) {
            const entry = Object.values(bundle).find(
                (output) =>
                    output.type === 'chunk' &&
                    output.isEntry &&
                    output.facadeModuleId?.endsWith('/resources/js/app.ts'),
            );
            if (!entry || entry.type !== 'chunk')
                throw new Error('SPA entry chunk was not generated.');
            const css = [...(entry.viteMetadata?.importedCss ?? [])]
                .map((file) => `<link rel="stylesheet" href="/build/${file}">`)
                .join('\n');
            this.emitFile({
                type: 'asset',
                fileName: 'offline.html',
                source: `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="application-name" content="${escapeHtml(appName)}">
<meta name="theme-color" content="#12332e">
<title>${escapeHtml(appName)}</title>
<link rel="manifest" href="/build/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png">
${css}
<script type="module" src="/build/${entry.fileName}"></script>
</head>
<body><div id="app"></div><noscript>Please enable JavaScript to use this application.</noscript></body>
</html>`,
            });
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const name = env.VITE_APP_NAME || env.APP_NAME || 'Mealplaner';
    const port = Number(env.VITE_PORT || 5173);
    const host = env.VITE_HMR_HOST || 'localhost';

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: ['resources/views/**', 'routes/**'],
            }),
            vue(),
            offlineShell(name),
            VitePWA({
                strategies: 'generateSW',
                outDir: 'public/build',
                base: '/build/',
                scope: '/',
                filename: 'sw.js',
                injectRegister: false,
                registerType: 'prompt',
                manifest: {
                    id: '/',
                    name,
                    short_name: env.VITE_PWA_SHORT_NAME || name,
                    description: 'Your food, recipes, and nutrition in one place.',
                    start_url: '/',
                    scope: '/',
                    display: 'standalone',
                    background_color: '#f5f7f4',
                    theme_color: '#12332e',
                    icons: [
                        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                        {
                            src: '/icons/maskable-512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'maskable',
                        },
                    ],
                },
                workbox: {
                    // The worker is served at /sw.js; every precache URL must be root-absolute.
                    inlineWorkboxRuntime: true,
                    modifyURLPrefix: { '': '/build/' },
                    globPatterns: ['assets/**/*.{js,css,woff2}', 'offline.html'],
                    additionalManifestEntries: [
                        ...['icon-192.png', 'icon-512.png', 'maskable-512.png'].map((icon) => ({
                            url: `/icons/${icon}`,
                            revision: createHash('sha256')
                                .update(readFileSync(`public/icons/${icon}`))
                                .digest('hex'),
                        })),
                    ],
                    navigateFallback: '/build/offline.html',
                    navigateFallbackDenylist: [
                        /^\/api(?:\/|$)/,
                        /^\/(?:up|build|storage|icons|sanctum|_ignition|_debugbar|horizon|telescope|livewire|account)(?:\/|$)/,
                        /\.[^/]+$/,
                    ],
                    cleanupOutdatedCaches: true,
                    clientsClaim: true,
                    skipWaiting: false,
                },
                integration: {
                    beforeBuildServiceWorker(options) {
                        // The plugin appends its manifest after Workbox's URL-prefix transform.
                        options.workbox.additionalManifestEntries = (
                            options.workbox.additionalManifestEntries ?? []
                        ).map((entry) => {
                            if (typeof entry === 'string')
                                return entry.startsWith('/') ? entry : `/build/${entry}`;
                            return {
                                ...entry,
                                url: entry.url.startsWith('/') ? entry.url : `/build/${entry.url}`,
                            };
                        });
                    },
                },
                devOptions: { enabled: false },
            }),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            origin: `http://${host}:${port}`,
            cors: { origin: env.APP_URL || 'http://localhost:8000' },
            hmr: { host, clientPort: port },
            watch: {
                usePolling: env.VITE_USE_POLLING === 'true',
                interval: Number(env.VITE_POLL_INTERVAL || 300),
            },
        },
    };
});
