# Development

## Integrated frontend foundation

The browser application lives in `resources/js`, bootstrapped by `app.ts` and mounted
into the Blade entry `resources/views/app.blade.php`. `App.vue` supplies the Mealplaner
header, skip link, main router outlet, existing theme controls, and PWA update notice.
`resources/css/app.css` supplies the reused responsive light/dark styling.

Vue Router keeps HTML5 history and the existing `/`, `/about`, and Not Found routes.
The `/` route keeps its `welcome` name for compatibility. Route metadata provides
page titles; navigation moves keyboard focus to the main region. Pinia is registered
once in the existing entry and holds only the real `theme` and `connection` stores.
No authentication pages or guards are currently implemented.

The verified frontend versions are Vue 3.5.44, Vite 7.3.7, TypeScript 5.8.3, Vue Router
4.6.4, Pinia 3.0.4, and Laravel's Vite plugin 2.1.0. Verification used the existing
Node.js 24.22.2/npm 11.19.1 container and the preserved root lockfile.

Use these root scripts through `docker compose exec -T --user node frontend` when
host dependencies are hidden by Compose volumes:

| Command                | Purpose                                                                                                     |
| ---------------------- | ----------------------------------------------------------------------------------------------------------- |
| `npm ci`               | Reinstall exactly the locked dependencies when installation is needed; does not create another application. |
| `npm run dev`          | Existing Vite process, serving development modules and HMR for Laravel.                                     |
| `npm run type-check`   | Strict Vue TSC checking of application, tests, and tool configuration.                                      |
| `npm run test:unit`    | Alias for the existing Vitest `npm run test`; runs once without backend services.                           |
| `npm run lint`         | Existing non-mutating ESLint check.                                                                         |
| `npm run format:check` | Existing non-mutating Prettier check.                                                                       |
| `npm run build`        | Type checks, then emits production assets under `public/build`.                                             |
| `npm run verify:build` | Checks the compiled entry, CSS, manifest, worker, offline shell, and icons.                                 |

Unit tests remain in `tests/frontend` and use Vue Test Utils, memory routing, fresh
Pinia, typed browser fixtures, and mocked HTTP responses. Browser tests remain under
`tests/e2e`. The development suite verifies real assets, routing/refresh, connectivity,
and Vue/CSS HMR. It temporarily edits the Home template and CSS and restores both;
do not run it while someone else is editing those files.

No `VITE_API_BASE_URL` is required: the existing API helper uses relative `/api/ready`.
Public `VITE_APP_NAME`/`VITE_PWA_SHORT_NAME` control generated PWA/offline metadata;
they do not override the product's Mealplaner header and route titles. The new example
short name is Mealplaner; actual local overrides are preserved. Existing `APP_URL`,
`VITE_HMR_HOST`, and port variables still control local asset/HMR origins. Restart
Vite after changing environment settings and rebuild for production metadata changes.
Never put credentials or private tokens into `VITE_*`; their values are public.

TASK-03 verified browser rendering at 1440, 768, 390, and 320 pixels, keyboard access,
light/dark themes, direct navigation/refresh, and backend endpoint isolation. The
production-offline browser branch was not run in development mode. See
[VERIFICATION.md](VERIFICATION.md) for results, the temporary host-Chrome testing
method, and existing dependency advisories. No additional dependencies were introduced.

## Containers

| Service  | Responsibility                 | Host ports     | Persistent data         |
| -------- | ------------------------------ | -------------- | ----------------------- |
| backend  | PHP 8.5 FPM, Composer, Laravel | None           | vendor, Composer cache  |
| frontend | Node 24, npm, Vite             | 127.0.0.1:5173 | node_modules, npm cache |
| postgres | PostgreSQL 18                  | None           | postgres_data           |
| nginx    | Static files and Laravel HTTP  | 127.0.0.1:8000 | None                    |

The project is bind-mounted into the application containers, so PHP changes need no rebuild. Shared mounts use SELinux `:z` labels. The database is available only on the Compose network. PHP starts after PostgreSQL is healthy; Vite starts after FPM's FastCGI ping succeeds; Nginx starts after Vite is ready. Startup failures stay visible through logs and health status.

The entrypoints compare dependency-file fingerprints to stamps in their named volumes. Changed Composer files trigger `composer install`; changed npm files trigger `npm ci`. The PHP entrypoint locks `.env`, reads the existing key, and only fills an empty key. It does not migrate the database. Do not share a development `.env` between projects.

## Commands

```bash
docker compose exec backend php artisan <command>
docker compose exec --user app backend composer <command>
docker compose exec --user node frontend npm <command>
docker compose exec --user node frontend npm run lint
docker compose exec --user node frontend npm run format
docker compose exec --user node frontend npm run format:check
docker compose exec --user node frontend npm run type-check
docker compose exec --user node frontend npm run test:unit
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
docker compose exec backend ./vendor/bin/pint
docker compose exec backend ./vendor/bin/phpstan analyse
```

Vite starts automatically through `npm run dev`. To restart it: `docker compose restart frontend`. For one-off npm commands when it is stopped, use `docker compose run --rm --no-deps frontend npm <command>`. Use `docker compose run --rm --no-deps backend composer <command>` for Composer without FPM.

## Hot reload

Open Laravel at port 8000. Laravel's official Vite plugin writes `public/hot`, and Blade uses it to load assets from port 5173. Vue SFCs and imported CSS update through Vite's HMR connection.

`APP_URL` must match the URL in your browser for the Vite CORS allowlist. `VITE_HMR_HOST` is the browser-visible host, normally `localhost`; it must never be `frontend`. `VITE_PORT` configures both published and container ports. If `APP_PORT` changes, update `APP_URL` too. Restart Vite after editing environment variables. Production has no dependency on these development-only Vite settings.

Polling is enabled for reliable bind-mount watching. Set `VITE_USE_POLLING=false` to try native events, or increase `VITE_POLL_INTERVAL` to reduce CPU use. Both services bind their public ports to loopback. Sharing a development server requires intentional port and CORS changes.

## Migrations and isolation

Start with status inspection; this does not initialize the schema:

```bash
docker compose exec -T --user app backend php artisan migrate:status
docker compose exec -T --user app backend php artisan test
sh scripts/verify-postgres.sh
```

The existing development database has no migration repository. TASK-04 preserved it
rather than assuming an empty database was safe to initialize. Apply reviewed pending
migrations only after confirming the target is new, empty, dedicated, and authorized;
otherwise leave its data/history untouched. No migrations run at startup and no reset
commands are part of verification.

Default PHPUnit still forces SQLite memory storage and array drivers. Both test
configurations use a guarded bootstrap that rejects cached/unsafe effective settings.
The PostgreSQL runner creates a unique database and restricted login, supplies only
those credentials to `phpunit.postgres.xml`, applies the existing migration, and tests
committed-record persistence after a new connection. It cleans up only the resources
it created. It does not restart/recreate PostgreSQL or modify development data.

`pdo_pgsql` is installed in the PHP container; PostgreSQL 18.6 was verified. The
example now has a blank password and Mealplaner database/user placeholders; preserve
actual `.env` settings and provide private local credentials when creating a new
environment. `DB_HOST=postgres` is for the existing Compose network; host PostgreSQL
normally uses `127.0.0.1`. No database port is currently published to the host.

See [database.md](database.md) for exact variables, permissions, initial inspection,
test isolation, migration rules, verified reconnect persistence, and the remaining
TASK-05 container-lifecycle checks. The PostgreSQL test runner requires uncached local
configuration and permission to provision isolated roles/databases; it fails rather
than changing existing permissions or falling back to development data.

## Browser integration checks

Playwright checks real asset loading, routing, explicit API requests, production worker installation, precaching, offline reopening, and Vue/CSS HMR. The HMR test temporarily edits two source files and restores them in a `finally` block. Run it when no one else is editing those files.

With local Node available, after installing project dependencies:

```bash
npx playwright install chromium
TEST_MODE=development npm run test:e2e
```

No host Node is needed for the application. For browser checks entirely in Docker, run a temporary browser-test container on the Compose network (this is a one-off test runner, not an application service):

```bash
docker compose run --rm --no-deps --entrypoint sh --user root frontend -c 'export PLAYWRIGHT_BROWSERS_PATH=/tmp/starter-playwright; npx playwright install --with-deps chromium && gosu node env BASE_URL=http://nginx TEST_MODE=development npm run test:e2e'
```

That browser needs to resolve the HMR hostname. For the Docker browser run, temporarily set `VITE_HMR_HOST=frontend` and `APP_URL=http://nginx` in `.env`, run `docker compose up -d --force-recreate frontend`, then run the tests. Restore both values afterward and recreate frontend again before browsing from Fedora. This is a test-only change; normally the host browser must use `localhost`.

Alternatively use an existing compatible Chromium via `CHROMIUM_PATH=/path/to/chromium`. Downloading browsers needs network access. Real mobile installation and browser install prompts remain manual checks.

## Production build and PWA smoke test

```bash
docker compose exec --user node frontend npm ci
docker compose exec --user node frontend npm run build
docker compose exec --user node frontend npm run verify:build
docker compose stop frontend
```

Vite removes `public/hot` on normal shutdown. If the container was killed and it remains, remove only `public/hot` with `docker compose exec backend php -r 'if (is_file("public/hot")) unlink("public/hot");'`.

Open http://localhost:8000, reload, and inspect the network panel. JavaScript and CSS must come from `/build/assets/`, with no requests to port 5173. Deep-link to `/about`. Check `/build/manifest.webmanifest` and `/sw.js`; the root worker must have a JavaScript MIME type and scope `/`.

For a Docker browser production run, use the browser-test command above with `TEST_MODE=production PWA_TEST_ORIGIN=http://nginx`; production assets use only relative same-origin URLs, so no HMR hostname override is needed. `PWA_TEST_ORIGIN` adds a Chromium test-only secure-origin exception for the internal HTTP hostname. Real installation uses localhost or production HTTPS. With host Node: `TEST_MODE=production npm run test:e2e`.

The PWA plugin runs only during a production build. The app registers `/sw.js` through the browser API only when `import.meta.env.PROD` is true. The plugin cannot discover Blade as a Vite HTML entry, so `offlineShell` emits `public/build/offline.html` from the compiled entry and CSS. Workbox precaches it, hashed assets, and content-revisioned icons. Navigation uses that static shell; API and framework namespaces, dotted static URLs, and non-GET requests are excluded. No API caching, IndexedDB, background sync, or polling is configured.

Once the service worker is activated and controls the page, enable browser offline mode and reopen `/` and `/about`. Navigation and theme controls should work. The connectivity button should show failure. Offline support requires one successful installation and precache; an initial visit while offline cannot install the app.

To test an update, keep a production tab open, edit welcome copy, rebuild, and reload the page online. A newly installed worker waits while the current version stays usable. The application offers **Update application**; clicking it activates the waiting worker and reloads the tab. Old precaches are cleaned during activation. Check the new text without deleting browser storage. Other open tabs may need a reload after activation.

Before returning to HMR, unregister this project's service worker in browser DevTools → Application → Service Workers (or use a fresh browser profile). Then run `docker compose up -d frontend`. A worker installed by a previous production smoke test can otherwise keep serving its cached production shell. Development itself never installs a worker.

## Backup and restore

Back up the development database:

```bash
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > local-backup.dump
```

Restore into a **new** database to avoid overwriting existing data:

```bash
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" starter_restore'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d starter_restore --no-owner' < local-backup.dump
```

Choose a fresh database name if it exists. To use the restored database, set `DB_DATABASE=starter_restore` in `.env` and restart backend. Keep backups outside Git. `docker compose down` preserves the named database volume. Changing the directory or Compose project name selects different volumes; keep track of your project name.

## Fedora and ownership troubleshooting

Use Docker's standard Fedora installation and ensure the daemon is running and your user is authorized to access its socket. This project does not change the host firewall, Docker daemon, group memberships, or SELinux policy.

All shared bind mounts use `:z`, including Nginx configuration. Do not replace it with private `:Z` on a directory mounted into multiple containers. Keep the project in a user-owned directory that Docker can relabel. SELinux failures usually appear as permission errors in container logs; inspect your system's audit diagnostics rather than disabling SELinux.

Set `LOCAL_UID`/`LOCAL_GID` before building. Rebuild backend/frontend after changing them. Composer/npm commands above use the matching application user; Nginx reads the source. If existing dependency volumes have old ownership, repair just those volumes:

```bash
docker compose exec --user root backend chown -R app:app vendor storage bootstrap/cache
docker compose exec --user root frontend chown -R node:node node_modules /home/node/.npm
```

If the backend cannot update `.env`, make that specific file writable by your host user. Do not use world-writable permissions or recursively change the ownership of unrelated directories. Dependency volumes intentionally hide vendor/node_modules from host tools; run checks through Compose.

## Updates

```bash
docker compose exec --user app backend composer outdated
docker compose exec --user app backend composer update
docker compose exec --user node frontend npm outdated
docker compose exec --user node frontend npm update
docker compose build --pull backend frontend
docker compose pull postgres nginx
```

Review and retain both lockfiles, run all quality checks, build and verify the PWA, and test startup after upgrades. Major PostgreSQL upgrades require a separate backup/migration procedure; never point a new incompatible major at an old data volume. Keep production runtimes compatible with `composer.json` and `package.json`.
