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

## Docker setup from a fresh clone

**Current checkout warning:** the initial TASK-05 verification probe created two empty
development cache tables. Their targeted removal awaits approval. See the
[incident record](VERIFICATION.md#verification-incident--cleanup-awaiting-approval)
before any development schema changes. New-clone setup below does not authorize
resetting an existing database.

Docker Engine and Docker Compose v2 with `--wait` support are the only required host
runtimes. PHP, Composer, Node/npm, and PostgreSQL run in containers. Use the root
checkout throughout; do not create separate backend/frontend applications.

1. Prepare the ignored environment once, preserving any existing file:

    ```bash
    test -f .env || cp .env.example .env
    id -u
    id -g
    ```

2. Edit `.env` privately. Set a local `DB_PASSWORD` (the blank example deliberately
   fails Compose validation), confirm the database/user and `DB_HOST=postgres`, and
   set `LOCAL_UID`/`LOCAL_GID` to the host IDs above. Keep `APP_ENV=local`, file
   sessions/cache, and synchronous queues. Leave a new `APP_KEY` blank; preserve an
   existing key. Default URLs are `APP_URL=http://localhost:8000`, `APP_PORT=8000`,
   `VITE_HMR_HOST=localhost`, and `VITE_PORT=5173`.
3. Validate, build, and start the same application:

    ```bash
    docker compose config --quiet
    docker compose up -d --build --wait --wait-timeout 300
    docker compose ps
    ```

    Internet access is needed to pull images and initially install locked dependencies.
    Entrypoints install missing/changed dependencies with `composer install` / `npm ci`
    into named volumes. Both tracked lockfiles are required; startup never resolves a
    replacement lockfile. Subsequent unchanged starts reuse installed dependencies.
    PHP initializes only an empty application key, under a file lock, without printing
    it. A missing `.env` fails rather than creating an incomplete configuration.
    No startup command runs migrations, seeds, or resets.

4. Check the database connection and inspect migration status:

    ```bash
    docker compose exec -T --user app backend php artisan --version
    curl --fail http://localhost:8000/api/ready
    docker compose exec -T --user app backend php artisan migrate:status
    ```

    `Migration table not found` means history is uninitialized, not that the connection
    failed. **Only after confirming a new, empty, dedicated Mealplaner development
    database is safe to initialize**, run reviewed pending migrations:

    ```bash
    docker compose exec -T --user app backend php artisan migrate --force
    ```

    For existing or uncertain data, stop at inspection; obtain authorization for schema
    changes. Preserve its history. Never use reset/fresh/refresh/wipe commands.

5. Open <http://localhost:8000>. Vite already runs automatically. Check `/up`,
   `/api/health` (database-independent JSON), and `/api/ready` (database readiness).
   Run the test/quality commands below. No host language runtime is needed.

## Containers and networking

| Service  | Pinned runtime                  | Host ports     | Named volumes           |
| -------- | ------------------------------- | -------------- | ----------------------- |
| backend  | PHP 8.5.12 FPM, Composer 2.10.3 | None           | vendor, composer_cache  |
| frontend | Node 24.22.2, npm 11.19.1       | 127.0.0.1:5173 | node_modules, npm_cache |
| postgres | PostgreSQL 18.6                 | None           | postgres_data           |
| nginx    | Nginx 1.30.6                    | 127.0.0.1:8000 | None                    |

Official image tags and immutable registry digests pin these compatible versions.
PHP/Node use Debian Bookworm; PostgreSQL/Nginx use Alpine. Updates require deliberate
pin changes, rebuilds, and verification; image pins do not replace security maintenance.
Debian package repositories are not snapshot-pinned, so OS package patch versions may
change on a clean rebuild. Preserve the dependency lockfiles.

These are process roles for one Laravel release. Nginx serves `public/` and forwards
PHP to FPM; Node serves this application's root Vite entry. No extra proxy, application,
authentication layer, or API origin is introduced. PostgreSQL stays on the private
Compose network. Browser URLs use `localhost`; PHP connects to `postgres:5432`, and
Nginx connects to `backend:9000`. Never put those internal names in browser URLs.

The source checkout is shared through bind mounts, so PHP changes require no image
rebuild and Vue/CSS changes reach Vite immediately. `vendor`/`node_modules` and package
caches use named volumes; `public/build` and `public/hot` stay in the shared checkout.
Dependency volumes hide host directories by design: run tools in the containers.
The Docker build context excludes environments, private storage, credentials/tool
metadata, dependencies, builds, logs, and database dumps. Images contain runtime files,
not application secrets. `VITE_*` variables are public and must never contain secrets.

PostgreSQL readiness gates FPM, the FastCGI ping gates Vite, and Vite's HTTP check gates
Nginx. Nginx also checks Laravel `/up`. Services restart unless stopped; PostgreSQL
gets 30 seconds for graceful shutdown. Vite uses an init process for signal/child
handling. Health checks do not mutate databases. Initial dependency installation may
need longer readiness time on a slow network; inspect status/logs rather than resetting.

## Commands

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f
docker compose exec --user app backend php artisan <command>
docker compose exec --user app backend composer <command>
docker compose exec --user node frontend npm <command>
docker compose exec --user node frontend npm run <script>
docker compose down
```

Logs can contain application data; do not share unreviewed output or dump resolved
configuration. `config --quiet` validates without exposing credentials. `down` preserves
named volumes; never add volume-removal flags. There are no destructive cleanup wrappers.

All development tool commands explicitly use the mapped non-root user. A root
entrypoint prepares only project writable directories and dependency/cache mount roots;
FPM's master keeps its normal root privilege and workers run as `app`. Vite/npm and
Composer run as `node`/`app`. Dependency stamps are also owned by those users. Rebuild
images after Dockerfile or UID/GID changes; recreate services after Compose environment
or port changes. Plain `restart` does not apply changed Compose settings.

For a stopped service, one-off commands reuse its volumes and mapped user:

```bash
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps frontend npm ci
```

Do not run another `npm run dev` alongside the existing Vite service. After changing
lockfiles, restart the affected service to synchronize dependencies. A manual `npm ci`
removes the dependency stamp, so the next Vite startup will synchronize again.

## Tests and quality checks

```bash
docker compose exec -T --user app backend php artisan test
sh scripts/verify-postgres.sh
docker compose exec -T --user app backend composer validate --strict
docker compose exec -T --user app backend composer check-platform-reqs
docker compose exec -T --user app backend ./vendor/bin/pint --test
docker compose exec -T --user app backend composer analyse
docker compose exec -T --user node frontend npm run type-check
docker compose exec -T --user node frontend npm run lint
docker compose exec -T --user node frontend npm run format:check
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

Default PHPUnit forces SQLite memory storage and array drivers. Its guarded bootstrap
rejects cached/unsafe effective settings. The PostgreSQL runner creates a unique database
and restricted login, supplies only those credentials to `phpunit.postgres.xml`, applies
the existing migration, checks commit/reconnect persistence, and removes only its own
resources. It never restarts services or modifies development tables. Do not invoke
that XML manually with development credentials. The runner needs local provisioning
permissions and fails safely if they are absent. Tools preserve nonzero exit status.
See [database.md](database.md) for isolation and migration details.

## Hot reload and environment changes

Open Laravel at port 8000, not the Vite module server. Laravel's Vite plugin writes
`public/hot`, and Blade loads development modules from port 5173. Vue SFCs and imported
CSS update through HMR. PHP OPcache validates timestamps immediately in this dev image.

`APP_URL` must match the browser origin for Vite's CORS allowlist. `VITE_HMR_HOST` is
the browser-visible host, normally `localhost`, never `frontend`. `VITE_PORT` controls
both published and container ports. If `APP_PORT` changes, update `APP_URL` too.
After editing these settings, apply them with:

```bash
docker compose up -d --force-recreate frontend nginx
```

This preserves named volumes. Backend/database
settings belong in the existing `.env`; do not overwrite keys or credentials.

Polling supports bind-mount watching reliably. Set `VITE_USE_POLLING=false` to try native
events, or increase `VITE_POLL_INTERVAL` from 300 milliseconds to reduce CPU use. All
published ports bind loopback; sharing beyond this host requires intentional security,
origin, and port configuration. Production uses compiled assets and does not need Vite.

## Browser integration checks

Playwright checks real asset loading, routing, explicit API requests, production worker installation, precaching, offline reopening, and Vue/CSS HMR. The HMR test temporarily edits two source files and restores them in a `finally` block. Run it when no one else is editing those files.

With local Node available, after installing project dependencies:

```bash
npx playwright install chromium
TEST_MODE=development npm run test:e2e
```

No host Node is needed for the application or unit checks. Browser automation additionally
needs a Chromium installation and its OS libraries. On Linux, a temporary runner can
share host networking so its browser reaches the usual localhost application/HMR URLs
without changing `.env` (installations stay inside the disposable container):

```bash
docker run --rm --init --network host --volumes-from "$(docker compose ps -q frontend)" --workdir /var/www/html --entrypoint sh mealplaner-frontend -c 'export PLAYWRIGHT_BROWSERS_PATH=/tmp/mealplaner-playwright; npx playwright install --with-deps chromium && chmod -R a+rX "$PLAYWRIGHT_BROWSERS_PATH" && gosu node env TEST_MODE=development npm run test:e2e'
```

`mealplaner-frontend` is the default Compose-built image name; substitute the image
for your project if you changed the Compose project name. This optional runner needs
network access for browser downloads. It runs only the browser tests as the mapped
user, shares source/dependencies for the HMR check, and does not create database
services or change application credentials. Host networking is a Linux-only testing
choice, separate from the isolated normal development stack.

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

For a Docker browser production run, use the temporary host-network runner above with `TEST_MODE=production`. Stop Vite first so Laravel uses the built assets. Localhost is a browser secure context; production installation requires HTTPS. With host Node: `TEST_MODE=production npm run test:e2e`.

The PWA plugin runs only during a production build. The app registers `/sw.js` through the browser API only when `import.meta.env.PROD` is true. The plugin cannot discover Blade as a Vite HTML entry, so `offlineShell` emits `public/build/offline.html` from the compiled entry and CSS. Workbox precaches it, hashed assets, and content-revisioned icons. Navigation uses that static shell; API and framework namespaces, dotted static URLs, and non-GET requests are excluded. No API caching, IndexedDB, background sync, or polling is configured.

Once the service worker is activated and controls the page, enable browser offline mode and reopen `/` and `/about`. Navigation and theme controls should work. The connectivity button should show failure. Offline support requires one successful installation and precache; an initial visit while offline cannot install the app.

To test an update, keep a production tab open, edit welcome copy, rebuild, and reload the page online. A newly installed worker waits while the current version stays usable. The application offers **Update application**; clicking it activates the waiting worker and reloads the tab. Old precaches are cleaned during activation. Check the new text without deleting browser storage. Other open tabs may need a reload after activation.

Before returning to HMR, unregister this project's service worker in browser DevTools → Application → Service Workers (or use a fresh browser profile). Then run `docker compose up -d frontend`. A worker installed by a previous production smoke test can otherwise keep serving its cached production shell. Development itself never installs a worker.

## Backup and restore

Keep the checkout directory/Compose project name stable: it selects the existing named
volumes. PostgreSQL 18 stores data under `/var/lib/postgresql/18/docker`, covered by
`postgres_data:/var/lib/postgresql`. Ordinary restarts/recreation and `docker compose
down` preserve it. Changing database environment values does **not** change users or
passwords inside an initialized volume; mismatches require deliberate administration,
not volume deletion. Never change the PostgreSQL major against an existing data volume.
Named-volume durability is not a backup: keep private dumps outside Git and test restore
into a fresh database before relying on recovery. No backup restore has been verified.

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

If a required port is occupied, choose `APP_PORT`/`VITE_PORT`, update `APP_URL`, and
recreate the affected services. If startup fails, use `docker compose ps` and private
service logs; check the first dependency failure, network access, lockfiles, and disk
space. `unhealthy` does not justify resetting data. For missing-table errors, inspect
migration status and the selected file/session/cache settings before changing schema.

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

For runtime updates, review the official image version/digest and edit the relevant pins before building/pulling; `pull` alone intentionally retains the pinned image. Review and retain both lockfiles, run all quality checks, build and verify the PWA, and test startup after upgrades. Major PostgreSQL upgrades require a separate backup/migration procedure; never point a new incompatible major at an old data volume. Keep production runtimes compatible with `composer.json` and `package.json`.
