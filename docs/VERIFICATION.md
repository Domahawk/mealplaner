# Verification record

## TASK-04 — PostgreSQL persistence (2026-11-02)

Initial inspection is recorded in [database.md](database.md). The existing runtime
is PostgreSQL 18.6, connected through Laravel 13.39.0/PHP 8.5.12 with `pdo_pgsql`.
The separate PHP `pgsql` extension is absent and unnecessary. Current development
permissions permit connectivity and DDL; its existing privileged role was preserved.

Before changes, development had zero user relations/extra schemas and no migration
repository. `php artisan migrate:status` returned **Migration table not found**.
No development migration was run: pre-existing/empty did not establish newness or
exclusive ownership. The source migration and history remain untouched. Only the
newly created test database was migrated.

The existing writable named volume covers the PostgreSQL data directory, with
`fsync` and `synchronous_commit` on. The revised probe does not recreate containers.
It uses administrative provisioning only to create a new random database/login,
then runs tests with the restricted role's generated credentials. Its `finally`
removes only successfully created resources. No actual environment file, application
key, existing role/credential, service configuration, or frontend setting was changed.

| Check                                      | Result                                                                                                                                                            |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Native Laravel PostgreSQL connection       | Passed; configured and connected driver is `pgsql`.                                                                                                               |
| `php artisan --version`                    | Passed: Laravel 13.39.0.                                                                                                                                          |
| Development `php artisan migrate:status`   | Executed; no migration repository. Left uninitialized rather than applying pending migrations.                                                                    |
| `php artisan test --compact`               | Passed: **24 tests, 98 assertions**, isolated SQLite and guard regressions.                                                                                       |
| `sh scripts/verify-postgres.sh`            | Passed: **3 PostgreSQL tests, 49 assertions**, existing migration/cache tables, actual restricted-role identity, readiness, and committed record after reconnect. |
| `./vendor/bin/pint --test`                 | Passed: 33 PHP files.                                                                                                                                             |
| Configured PHPStan/Larastan analysis       | Passed; no errors.                                                                                                                                                |
| `npm run test:unit`                        | Passed: existing 10 frontend tests.                                                                                                                               |
| Real HTTP `/api/health`, `/api/ready`, `/` | HTTP 200; health/readiness JSON contracts preserved.                                                                                                              |

The persistence test observed a different `pg_backend_pid()` after Laravel purged
and reconnected, then retrieved the committed probe record. Only that probe row and
the fresh database/login created by the runner were removed. Post-checks found zero
development relations, zero remaining generated test databases, and zero generated
roles. There was no development reset or row modification.

The new guards reproduced and resolved an inherited-environment issue: values in
`$_SERVER` could retain local settings despite PHPUnit/`putenv` values. The bootstrap
now matches Laravel's effective precedence and rejects unsafe configuration before
boot; the child process explicitly receives testing/array settings and does not
inherit a development application key. Regressions cover the conflicting inherited
environment, custom cache path, non-testing mode, file-backed SQLite, URL overrides,
ordinary PostgreSQL names, and mismatched roles. No test checks were disabled.

The tested persistence claim is **commit/reconnect survival**, not PostgreSQL or
Docker restart recovery. Container recreation, volume recovery, backup restore, and
broader startup/lifecycle acceptance remain for TASK-05. No new Docker infrastructure,
packages, auth/domain models, or migrations were introduced. The existing privileged
development role also needs deliberate least-privilege review before production.

## TASK-03 — integrated Vue frontend (2026-11-02)

The existing root Vue/Laravel integration, Router, Pinia, strict TypeScript, styling,
unit/browser tools, and PWA behavior were reused. No application was scaffolded and
no dependency versions changed. `package-lock.json` is unchanged. Root `npm ci`
succeeded in the existing Node.js 24.22.2/npm 11.19.1 container using that lockfile.
Detected versions remain Vue 3.5.44, Vite 7.3.7, TypeScript 5.8.3, Router 4.6.4,
Pinia 3.0.4, Vue TSC 3.3.14, Laravel Vite plugin 2.1.0, and Vitest 4.1.12.

The shell/Home now show Mealplaner and the requested welcome message. Existing About
and Not Found routes remain available. Page titles, a skip link, main focus on route
changes, and improved light-theme muted-text contrast were added; template-only
components remain template-only. Theme/connection stores and API behavior are unchanged.
`test:unit` aliases `test`, preserving existing callers.

| Check                                   | Result                                                                                                                                           |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `npm ci`                                | Passed: 571 platform-applicable packages installed from the unchanged lockfile.                                                                  |
| `npm run type-check`                    | Passed with all strict rules retained.                                                                                                           |
| `npm run lint`                          | Passed without disabling rules.                                                                                                                  |
| `npm run format:check`                  | Passed for the full repository after formatting the scoped source/documentation changes.                                                         |
| `npm run test:unit`                     | Passed: 10 tests, including Home content, routes/titles/focus, existing theme and connection behavior.                                           |
| `npm run build`                         | Passed: type check plus Vite 7.3.7; 45 modules compiled.                                                                                         |
| `npm run verify:build`                  | Passed: static assets and existing PWA/offline artifacts; seven precache entries.                                                                |
| Development Playwright suite            | Passed: 3 tests; 1 production-offline test skipped because `TEST_MODE=development`.                                                              |
| Additional Chrome checks                | Passed: keyboard skip link/focus, title updates, no-reload navigation, nested unknown-route refresh, theme switching, and no runtime exceptions. |
| Responsive checks and visual inspection | Passed: 1440, 768, 390, and 320 pixel widths with no horizontal overflow or clipped controls; light/dark and mobile screenshots inspected.       |
| Backend smoke suite / Pint              | Passed: 15 tests, 76 assertions; 29 PHP files pass Pint.                                                                                         |

The normal Laravel/Nginx origin `http://localhost:8000` served the app, with Vite assets
and the actual HMR connection on port 5173. The existing browser tests verified Home,
About navigation and refresh, nested Not Found content, readiness requests only on
user action, development assets, no development service worker, and both Vue template
and CSS HMR without a full navigation. The HMR fixture edits were restored in `finally`.
No local environment or backend route changes were made.

Host dependency directories expose no usable Node packages because they live in the
container volume. For browser verification, the already-installed Playwright 1.64.1
packages and existing test/config files were copied to a temporary host test workspace,
with its resources path pointing to this checkout. Installed Google Chrome was used
through `CHROMIUM_PATH=/usr/bin/google-chrome`; no browser/framework package was added
or downloaded. An initial discovery error was resolved by preserving the root
package's `type: module` metadata in that temporary workspace. Browser processes
closed after checks. The production-offline branch was not executed, and production
hosting/device installation remains unverified.

Read-only HTTP checks returned 200 for `/api/health`, `/api/ready`, and `/up`;
`/api/health` retained its exact `{"status":"ok"}` body. `/api/missing` returned JSON
404 rather than the SPA. Backend business logic, routes, database settings, and all
actual environment files remain unchanged. API URLs remain relative; no new API
client or `VITE_API_BASE_URL` was introduced. Existing PWA name overrides remain public
build metadata, while the header and page titles explicitly identify Mealplaner.

`npm ci` reported **26 existing dependency-graph findings: 2 low and 24 moderate**,
with no high or critical findings. Audit traced the originating advisories to
`deepmerge`, `glob`, and `minimatch` in the development graph; **the production-only
audit reported zero findings**. Suggested remediation includes incompatible tool
changes/downgrades, so no forced fixes or dependency overrides were applied. npm also
warned that locked ESLint/glob versions are deprecated and that an esbuild postinstall
was not approved by its install-script policy. The installed platform binary worked:
type checks, tests, and the production build all passed without relaxing that policy.
Dependency/security maintenance remains follow-up work within its own scope.

## TASK-02 — Laravel backend stabilization (2026-11-02)

The existing root Laravel application was extended without framework/dependency
installation, upgrades, authentication changes, database configuration changes,
migrations/resets, or Docker/service restarts. The already-running development
containers were reused. Laravel remains **13.39.0**, on **PHP 8.5.12**.

- `GET /api/health` is public, returns exactly `{"status":"ok"}` with HTTP 200 and
  `Content-Type: application/json`, and makes no database query.
- The previous database probe is retained as `GET /api/ready` in the renamed
  `ReadinessController`; connection success and sanitized 503 failures are unchanged.
  The existing Vue connection-check URL, label, tests, and PostgreSQL probe reference
  were updated to readiness. The PostgreSQL probe itself was not run.
- `/up`, SPA/web routing, middleware/session/CSRF behavior, and signed storage routes
  are preserved. The two existing smoke-test mismatches are corrected using explicit
  binary-file and signed-storage assertions, retaining coverage.
- `APP_NAME=Mealplaner` is set in the example and Laravel fallback. The existing
  local override, publicly visible as `MealPlaner`, was preserved. No actual local
  environment file was opened or overwritten, and no application key was rotated.
  Existing stack/single logging, file sessions/cache, synchronous queues, and
  PostgreSQL settings were verified through nonsecret runtime metadata and preserved.

Commands ran from the root application using the existing container command prefixes
listed in the README. Host PHP/dependency limitations remain as recorded below.

| Check                                        | Current result                                                                         |
| -------------------------------------------- | -------------------------------------------------------------------------------------- |
| `php artisan --version`                      | Passed: Laravel 13.39.0.                                                               |
| `php artisan route:list -v`                  | Passed: seven entries; health/readiness use standard API middleware and `/api` prefix. |
| `composer validate --strict`                 | Passed; no manifest/lockfile changes.                                                  |
| `composer check-platform-reqs`               | Passed with PHP 8.5.12.                                                                |
| `php artisan test`                           | Passed: **15 tests, 76 assertions**, including production assets; no skipped tests.    |
| `./vendor/bin/pint --test`                   | Passed: 29 files. Existing formatting issues corrected without disabling rules.        |
| `./vendor/bin/phpstan analyse --no-progress` | Passed at configured level 8; no errors.                                               |
| `npm run test`                               | Passed: 10 component/router/connectivity tests.                                        |
| `npm run type-check` / `npm run lint`        | Passed.                                                                                |
| `npm run build` / `npm run verify:build`     | Passed with the updated readiness URL.                                                 |

The new database-independence regression was run before the route change and failed
with HTTP 503, reproducing the old behavior. It now passes with
`database.default=unconfigured`, without PostgreSQL or database writes. Existing
readiness tests use isolated SQLite and a synthetic failure to verify safe errors.

Actual HTTP requests through existing Nginx at `127.0.0.1:8000` returned:

- `/api/health`: **200**, `application/json`, exact body `{"status":"ok"}`.
- `/api/ready`: **200**, `application/json`, body
  `{"status":"ok","database":"connected"}`.
- `/up` and `/about`: **200**.

Both API responses retain no-store caching. Browser/PWA/HMR tests and the database
recreation probe were not run. No duplicate applications, infrastructure, dependencies,
Git commits, or pushes were introduced. Future PostgreSQL domain schema and persistence
acceptance remain separate work; readiness is connectivity only.

## TASK-01 — historical audit baseline

The record below describes the audit before TASK-02. Its two backend test failures
and two formatting issues are now resolved; it is retained to explain their causes.

Audited on **2026-11-02**. This record supersedes the earlier sandbox-only verification
record: both lockfiles now exist, dependencies are installed in Compose named volumes,
and four services were already running. No dependency installation, service startup,
restart, database migration/reset, or application-code change was performed here.

## Environment and method

Source, manifests, lockfiles, routes, Vite/Blade/Vue integration, tests, deployment
references, and runtime scripts were inspected before changes. Checks used existing
running containers rather than substituting dependencies or changing PHP requirements.

| Location                | Detected runtime                                        |
| ----------------------- | ------------------------------------------------------- |
| Host                    | PHP 8.4.1, Composer 2.8.3, Node.js 24.19.0, npm 11.17.0 |
| `backend` container     | PHP 8.5.12, Composer 2.10.3, Laravel 13.39.0            |
| `frontend` container    | Node.js 24.22.2, npm 11.19.1                            |
| Database / HTTP service | PostgreSQL 18.6 / Nginx 1.30.6                          |

`docker compose ps --services --status running` listed `backend`, `frontend`, `nginx`,
and `postgres`. The configured application database driver is `pgsql`. All 107
installed Composer packages match the root lockfile; direct npm packages match their
lockfile and `npm ls --depth=0` succeeds. See [architecture.md](architecture.md) for versions.

Sandbox Docker socket access was initially denied. Approved execution outside the
sandbox allowed read-only inspection and verification in those already-running services.
No permission or dependency changes were used to bypass the project's requirements.

## Host checks

| Command                               | Actual result                                                        |
| ------------------------------------- | -------------------------------------------------------------------- |
| `php artisan --version`               | Failed: host `vendor/autoload.php` is unavailable.                   |
| `php artisan route:list`              | Failed for the same missing autoloader.                              |
| `php artisan test`                    | Failed before tests could run for the same missing autoloader.       |
| `composer validate --strict`          | Passed; manifest and lockfile are valid.                             |
| `composer check-platform-reqs --lock` | Failed: host PHP 8.4.1 does not satisfy the root `^8.5` requirement. |
| `npm run`                             | Passed; existing scripts enumerated and inspected.                   |
| `npm run type-check`                  | Failed: host `vue-tsc` is unavailable.                               |
| `npm run lint`                        | Failed: host `eslint` is unavailable.                                |
| `npm run format:check`                | Failed: host `prettier` is unavailable.                              |
| `npm run test`                        | Failed: host `vitest` is unavailable.                                |
| `npm run build`                       | Failed at type checking because host `vue-tsc` is unavailable.       |
| `docker compose config --quiet`       | Passed without printing resolved secrets.                            |
| PHP source syntax via `php -l`        | Passed for all 29 application/configuration/test/helper PHP files.   |

These are host environment limitations, not evidence of missing lockfiles or a need
to initialize/reinstall the application. Dependencies are available in container volumes.

## Checks in running containers

Backend commands use the prefix `docker compose exec -T --user app backend`;
frontend commands use `docker compose exec -T --user node frontend`.

| Command after the prefix                     | Actual result                                                                                                          |
| -------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `php artisan --version`                      | Passed: Laravel Framework 13.39.0.                                                                                     |
| `php artisan route:list -v`                  | Passed: API health, framework liveness, worker, SPA fallback, and generated GET/PUT storage routes; six entries total. |
| `composer validate --strict`                 | Passed.                                                                                                                |
| `composer check-platform-reqs`               | Passed with PHP 8.5.12.                                                                                                |
| `php artisan test`                           | **Failed: 10 passed, 2 failed, 57 assertions.** No tests skipped after the frontend build.                             |
| `./vendor/bin/pint --test`                   | **Failed: 2 style issues across 29 checked files.**                                                                    |
| `./vendor/bin/phpstan analyse --no-progress` | Passed at configured level 8 with Larastan; no errors.                                                                 |
| `npm ls --depth=0`                           | Passed; installed direct package versions inspected.                                                                   |
| `npm run type-check`                         | Passed, including application and test/tool configuration.                                                             |
| `npm run lint`                               | Passed.                                                                                                                |
| `npm run format:check`                       | Passed before and after documentation formatting.                                                                      |
| `npm run test`                               | Passed: 10 tests in `tests/frontend/app.test.ts`.                                                                      |
| `npm run build`                              | Passed: Vue TSC plus Vite 7.3.7; 45 modules compiled.                                                                  |
| `npm run verify:build`                       | Passed: entry/CSS, manifest, offline shell, worker URLs, and PNG icons.                                                |

Build output includes `public/build/manifest.json`, hashed JavaScript/CSS,
`manifest.webmanifest`, `offline.html`, and `sw.js`; Workbox precaches seven entries.
The build ran before PHPUnit so the production-assets test was exercised rather than
skipped. Generated build/cache output remains ignored.

## Live HTTP checks

Read-only requests against the already-running Nginx application at `127.0.0.1:8000`:

- `GET /up`: **HTTP 200**.
- `GET /api/health`: **HTTP 200**, `Content-Type: application/json`,
  `Cache-Control: no-store, private`, body `{"status":"ok","database":"connected"}`.

These confirm liveness and a successful configured database query at audit time.
They do not demonstrate migration status, backup recovery, volume persistence across
recreation, a rendered browser session, or production offline behavior.

## Existing failures and investigation

### Production worker response assertion

`tests/Feature/ProductionAssetsTest.php:21` expects `offline.html` through
`assertSee()` on the `/sw.js` response. The route returns a Symfony
`BinaryFileResponse`. A direct diagnostic confirmed `getContent()` is false while
the built worker file does contain `offline.html`; artifact verification also passed.
The failure concerns a file-response assertion rather than evidence that the offline
shell was omitted. A follow-up should verify the file response/body delivery using
appropriate assertions without removing the coverage.

### Signed storage route status

`tests/Feature/SpaTest.php:34` expects every excluded static/framework path to return 404. Laravel registers local-file routes because `config/filesystems.php` sets
`local.serve` to true. The framework's `ServeFile` implementation checks its signature
before existence and returns **403 for unsigned private requests outside production**
(including testing). This explains `/storage/missing`; it is not the SPA entry.
A follow-up should decide the intended signed-storage behavior and align its test
with that contract while retaining namespace isolation coverage.

### Pint style baseline

Pint 1.32.1 reports existing issues in:

- `bootstrap/providers.php`: `fully_qualified_strict_types` and
  `single_line_after_imports`.
- `tests/Feature/ProductionAssetsTest.php`: `fully_qualified_strict_types` and
  `unary_operator_spaces`.

No formatter was applied to PHP, no test expectations were weakened, and no failures
were suppressed. Fixes are outside this audit's application-code scope.

## Not executed and remaining work

- No Composer/npm install or update commands, framework scaffolding, or lockfile changes.
- No migrations, resets, seeders, PostgreSQL container recreation, or persistent-volume
  operations. The separate PostgreSQL probe was inspected but not run because it
  creates/drops a database, runs migrations, and recreates the database container.
- No Playwright/browser/HMR/offline/update/device-installation tests. The HMR test
  temporarily edits application source and is not a read-only audit check.
- No deployment, paid resources, CI configuration, architecture replacement, or Git
  commit/push. The existing cloud guide remains a future reference; its provider
  assumptions and secret-handling instructions require review before use.

Fix the two test-contract mismatches and two PHP formatting issues in scoped follow-up
work. The architecture matches the integrated application requirements, but the full
backend verification suite is **not green**.
