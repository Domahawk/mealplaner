# Verification record

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
