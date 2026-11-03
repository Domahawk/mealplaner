# Automated testing

Mealplaner uses the existing PHPUnit 12 and Vitest/Vue Test Utils suites. Tests run
against the single root Laravel application and integrated Vue frontend. No second
application, database service, or test framework is needed.

## Setup and commands

Prepare the local environment using [DEVELOPMENT.md](DEVELOPMENT.md). After updating
this checkout, rebuild the PHP development image for Xdebug and install the locked
frontend dependencies as needed:

```bash
docker compose up -d --build --wait --wait-timeout 300
docker compose exec -T --user node frontend npm ci --no-audit --no-fund
```

Fresh startup installs missing dependencies automatically. Preserve an existing `.env`
and key. The existing PostgreSQL connection must work, configuration must be uncached,
and the local provisioning role must be permitted to create an isolated database/login.
The Compose environment uses `APP_ENV=local`; the test runner forces `APP_ENV=testing`
only in its child. Do not point the provisioner at production or cache configuration
before testing. Do not print environment values or test passwords.

Run these commands from the root. All test processes execute non-interactively in
Docker as the mapped users, without host PHP/Composer/Node/PostgreSQL requirements:

| Purpose                                       | Command                                                             |
| --------------------------------------------- | ------------------------------------------------------------------- |
| Full backend suite on PostgreSQL              | `docker compose exec -T --user app backend composer test`           |
| Explicit PostgreSQL alias                     | `docker compose exec -T --user app backend composer test:postgres`  |
| Optional fast SQLite suite                    | `docker compose exec -T --user app backend composer test:sqlite`    |
| Backend coverage on PostgreSQL                | `docker compose exec -T --user app backend composer test:coverage`  |
| Frontend components/application               | `docker compose exec -T --user node frontend npm run test:unit`     |
| Frontend coverage                             | `docker compose exec -T --user node frontend npm run test:coverage` |
| Frontend watch mode (interactive development) | `docker compose exec --user node frontend npm run test:watch`       |

`composer test` and `test:postgres` reuse `scripts/verify-postgres.php`. The older
`sh scripts/verify-postgres.sh` remains compatible and now runs that same full suite.
The PHP runner accepts only the optional `--coverage` flag. `php artisan test` still
uses `phpunit.xml` and SQLite memory storage; it is not the canonical PostgreSQL run.
Vitest's `test`/`test:unit` scripts use `vitest run`, never watch mode.

Build the frontend before the backend production-asset test:

```bash
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

Without a build, that specific backend test reports a skip. Backend liveness and
other unit/feature tests remain runnable without compiled assets. Existing Playwright
browser/HMR checks are documented separately in [DEVELOPMENT.md](DEVELOPMENT.md).
They temporarily edit source and are not part of the unattended default suites.

## Database isolation and Laravel utilities

The canonical backend command runs **Feature, Unit, and PostgreSQL** suites in one
fresh PostgreSQL database per invocation:

1. The existing local connection is used only to provision a random
   `mealplaner_test_<16 hex characters>` database and matching login. The provisioner
   explicitly rejects a name matching development. Credentials are generated in
   memory and never written to `.env.testing` or passed from development to tests.
2. The login owns only the new database and has no superuser, database-creation, or
   role-creation privileges. Public access to that database is revoked. The child
   receives explicit testing/array drivers, an empty database URL, and a synthetic key.
3. `tests/bootstrap.php` rejects unsafe effective environment values and cached config
   before Laravel boots. It allows only this generated PostgreSQL identity or SQLite
   `:memory:`; `APP_ENV=testing` alone is insufficient.
4. `Tests\TestCase::setUpTraits()` validates the effective connection, actual server
   database/user, restricted permissions, and ownership **before Laravel database
   traits run**. It applies ordinary pending migrations once per generated database,
   before transactions begin. No reset/fresh/refresh/wipe command is used.
5. Database-writing feature tests use Laravel's `DatabaseTransactions` trait. Changes
   roll back when the application is torn down. Two PostgreSQL cases deliberately
   reuse one cache key and verify the previous transaction left no row. A separate
   regression changes the connection after boot and verifies rejection before a
   transaction could connect to an unsafe database.
6. The explicit commit/reconnect probe runs outside a wrapping transaction and deletes
   only its own row in `finally`. When the PHPUnit process exits, the runner removes
   only the database/login it successfully created, including after test failures.
   It never force-disconnects sessions or cleans up pre-existing resources.

Use the shared `Tests\TestCase` for Laravel feature tests so these guards apply.
Add `DatabaseTransactions` for tests that write database records. Do not use
`RefreshDatabase`/`DatabaseMigrations` or add reset commands incidentally: this setup
uses ordinary migrations plus transactions. Tests that specifically need committed
records must use narrowly scoped cleanup in their exclusively generated database.
Do not run `phpunit.postgres.xml` manually with development credentials or bypass
bootstrap checks. Default SQLite tests retain the same bootstrap and memory-only guard.

Independent runner invocations generate different identities. Parallel PHPUnit worker
setup is not configured; do not add parallel flags or suffix databases until each
worker has equivalent provisioning and guards. Provisioning or cleanup failures return
nonzero status; fix permissions/connectivity rather than falling back to development.
The pending TASK-05 development-table cleanup is separate and is not authorized by tests.

## Coverage and reports

The PHP development image includes pinned **Xdebug 3.5.3**, with `xdebug.mode=off`
by default. Only `composer test:coverage` enables `XDEBUG_MODE=coverage` in the child
PHPUnit process. Missing Xdebug fails explicitly before a database is provisioned.
No debugger listener or tracing is enabled. See [Xdebug coverage](https://xdebug.org/docs/code_coverage).

Frontend coverage uses the explicit **@vitest/coverage-v8 4.1.12** provider matching
installed Vitest 4.1.12. Keep those versions aligned when upgrading. The provider is
installed in the lockfile, so unattended runs do not prompt to install it. See
[Vitest coverage](https://vitest.dev/guide/coverage).

| Artifact                  | Location                                  |
| ------------------------- | ----------------------------------------- |
| Full backend JUnit        | `test-results/backend.xml`                |
| Optional SQLite JUnit     | `test-results/backend-sqlite.xml`         |
| Frontend JUnit            | `test-results/frontend.xml`               |
| PHP Clover coverage       | `coverage/php/clover.xml`                 |
| PHP HTML coverage         | `coverage/php/html/index.html`            |
| Frontend LCOV coverage    | `coverage/frontend/lcov.info`             |
| Frontend HTML coverage    | `coverage/frontend/index.html`            |
| Frontend coverage summary | `coverage/frontend/coverage-summary.json` |

PHP coverage includes `app` and `routes`. Frontend coverage includes all application
TypeScript and Vue files under `resources/js`, including uncovered files; only
non-executable declaration files are excluded. Dependencies, test code, and generated
bundles are not coverage targets. No arbitrary percentage gate or coverage exclusions
were added to hide gaps. Current measured results are in [VERIFICATION.md](VERIFICATION.md).
In particular, unit coverage does not establish production service-worker/device support.

Reports are generated in the shared checkout with host-user ownership and are already
Git-ignored and excluded from image build contexts/quality scans. Coverage contains
source code: treat uploaded artifacts according to repository visibility. Do not commit
reports or put secrets in fixtures.

## GitHub Actions preparation

No workflow is introduced here. After preparing the existing Docker environment,
a serial unattended check can use:

```bash
docker compose exec -T --user node frontend npm run quality
docker compose exec -T --user app backend composer quality
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
docker compose exec -T --user app backend composer test:coverage
docker compose exec -T --user node frontend npm run test:coverage
```

Keep command exit statuses intact. The PostgreSQL runner fails on PHPUnit warnings/risky tests; Vitest run
returns failures, and the database runner propagates the child status and cleanup
failures. Upload `test-results/*.xml`, `coverage/php/`, and `coverage/frontend/` as
artifacts in the later CI task. Normal `composer test` / `npm run test:unit` omit coverage
collection when reports are unnecessary. This recipe uses existing dev-only services;
production deployment configuration remains separate.

## Test conventions

- Test observable behavior with descriptive names. Use feature tests for HTTP and
  application/database integration; use plain PHPUnit unit tests for independent logic.
- Keep Vue component tests under `tests/frontend`, with real Pinia/router instances
  when relevant. Mock only unavailable browser capabilities and external HTTP calls.
- Reuse existing migrations and Query Builder until real domain models/factories exist.
  Do not add production models, schema, or frameworks solely for test fixtures.
- Keep tests independent of order. Restore browser globals/storage and unmount wrappers.
  Clean up only data/files the test created; never reset development data.
- Include regressions for bug fixes. Run both quality gates, relevant tests, and the
  production build when appropriate. Document skipped/unverified behavior honestly.
