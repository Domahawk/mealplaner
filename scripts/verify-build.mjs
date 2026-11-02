import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';

const root = new URL('../public/build/', import.meta.url);
const manifest = JSON.parse(await readFile(new URL('manifest.json', root), 'utf8'));
const pwa = JSON.parse(await readFile(new URL('manifest.webmanifest', root), 'utf8'));
const shell = await readFile(new URL('offline.html', root), 'utf8');
const worker = await readFile(new URL('sw.js', root), 'utf8');
assert.ok(manifest['resources/js/app.ts']?.isEntry);
assert.equal(pwa.scope, '/');
assert.equal(pwa.display, 'standalone');
assert.equal(pwa.icons.length, 3);
assert.ok(worker.includes('/build/offline.html'));
const precacheUrls = [...worker.matchAll(/url:"([^"]+)"/g)].map((match) => match[1]);
assert.ok(precacheUrls.some((url) => url.startsWith('/build/assets/')));
for (const url of precacheUrls) {
    assert.ok(url.startsWith('/'), `Precache URL must be absolute for the root worker: ${url}`);
    await stat(new URL('../public' + url, import.meta.url));
}
assert.ok(shell.includes('/build/' + manifest['resources/js/app.ts'].file));
assert.ok(
    !shell.includes('localhost') && !shell.includes('frontend:') && !shell.includes('backend:'),
);
for (const entry of Object.values(manifest)) {
    await stat(new URL(entry.file, root));
    for (const css of entry.css ?? []) await stat(new URL(css, root));
}
for (const icon of pwa.icons) {
    const data = await readFile(new URL('../public' + icon.src, import.meta.url));
    assert.equal(data.toString('hex', 1, 4), '504e47');
    const size = Number(icon.sizes.split('x')[0]);
    assert.equal(data.readUInt32BE(16), size);
    assert.equal(data.readUInt32BE(20), size);
}
console.log('Production entry, CSS, offline shell, manifest, worker, and PNG icons are valid.');
