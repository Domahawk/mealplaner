# Mealplaner

Mealplaner will manage nutritional food products, recipes, and daily calorie and
macronutrient intake. Its first milestone is **MVP v0.1.0**.

The repository currently contains an integrated Laravel + Vue 3 PWA starter, not the
completed Mealplaner product. It is **one root Laravel application**, with Vue and
TypeScript under `resources/js`, a Blade HTML entry, and Laravel's Vite plugin.
The browser uses same-origin REST endpoints. Production is one application deployment;
Node/Vite provide development assets and build the frontend bundle.

## Current capabilities and MVP scope

The integrated frontend now displays the Mealplaner header, Home welcome message,
About page, and Not Found page using the existing responsive styling. Route-specific
titles, a skip link, and main-content focus after navigation support keyboard use.
Existing Pinia-backed light/dark/system preferences, the user-triggered API/database
connection check, Laravel liveness, and production PWA build/update support are preserved.
No Inertia, Livewire, or authentication package is installed.

Planned MVP capabilities, **not yet implemented**:

1. Register, log in, and log out.
2. Create, view, edit, and delete user-owned products with nutritional information.
3. Create recipes from existing products and specified quantities.
4. Calculate recipe calories, protein, carbohydrates, and fat automatically.
5. Record consumption by date.
6. View daily calorie and macronutrient totals.

There are no user/domain models or domain migrations yet. PostgreSQL 18.6
connectivity, existing migration compatibility, and committed-record persistence
across reconnects are verified in an isolated test database. Development migration
history remains uninitialized and untouched. Docker lifecycle verification is recorded in [VERIFICATION.md](docs/VERIFICATION.md);
Mealplaner domain workflows remain future work. See [database.md](docs/database.md).

## Detected stack

Versions verified in the existing running containers on 2026-11-02:

| Area                                    | Detected version         |
| --------------------------------------- | ------------------------ |
| PHP / Laravel                           | 8.5.12 / 13.39.0         |
| Composer                                | 2.10.3                   |
| Vue / Vue Router / Pinia                | 3.5.44 / 4.6.4 / 3.0.4   |
| TypeScript / Vite / Laravel Vite plugin | 5.8.3 / 7.3.7 / 2.1.0    |
| Node.js / npm                           | 24.22.2 / 11.19.1        |
| PostgreSQL / Nginx                      | 18.6 / 1.30.6            |
| PHPUnit / Pint                          | 12.5.43 / 1.32.1         |
| PHPStan / Larastan                      | 2.3.4 / 3.13.2           |
| Vitest / Vue Test Utils                 | 4.1.12 / 2.5.1           |
| ESLint / Prettier / Vue TSC             | 9.39.5 / 3.9.10 / 3.3.14 |
| Playwright / vite-plugin-pwa            | 1.64.1 / 2.0.0           |

Both `composer.lock` and `package-lock.json` are present. Installed Composer packages
and direct npm packages match the lockfiles. Do not reinstall or scaffold the application
to work around missing host tools. The root PHP requirement is `^8.5`; host PHP 8.4.1
is incompatible. Host dependency mount directories do not expose the container volumes'
installed packages. Use the existing container workflow.

## Repository structure

```text
app/                       Laravel controllers and application provider
bootstrap/                 Laravel startup and route registration
config/                    Laravel configuration
database/                  Cache/cache-lock migration and empty seeder
resources/js/              Integrated Vue SPA: views, components, router, stores,
                           composables, and same-origin API helper
resources/css/app.css      Responsive application and theme styling
resources/views/app.blade.php  Laravel-served HTML entry using @vite
routes/{web,api,console}.php   HTTP, API, and console routes
public/                    Web root, icons, generated public/build assets
tests/{Feature,Unit,Postgres}/  PHPUnit suites; generated PostgreSQL database, optional SQLite
tests/{frontend,e2e}/       Vitest and Playwright tests
docker/{php,node,nginx}/    Existing local runtime configuration
compose.yaml               PostgreSQL, PHP, Vite, and Nginx services
scripts/                   Key initialization and verification helpers
docs/                      Architecture, development, deployment, verification
AGENTS.md                  Mandatory instructions for future Codex work
```

There are no separate backend/frontend application directories. Compose's `backend`
and `frontend` service names identify PHP and Vite processes for this same application.

## Integrated frontend

Laravel serves `resources/views/app.blade.php`, whose `@vite` directive loads
`resources/js/app.ts`. That entry imports `resources/css/app.css`, registers the
existing Pinia instance and Vue Router, and mounts `App.vue` into `#app`.
There is one root package manifest, lockfile, Vite configuration, and deployment.

The existing HTML5-history routes are preserved: `/` (`welcome` route name), `/about`,
and the Not Found catch-all. Their titles are `Mealplaner`, `About | Mealplaner`, and
`Page not found | Mealplaner`. Laravel continues handling API, health, storage, and
worker namespaces separately. The two real shared stores remain `theme` and
`connection`; no demo/global stores or authentication guards were added.

TypeScript remains strict, with Vue TSC checking application components, tests, and
tool configuration. Vitest and Vue Test Utils live under `tests/frontend`; Playwright
checks live under `tests/e2e`. `npm run test:unit` now aliases the existing `npm run test`
command, preserving both names.

Requests continue using relative `/api/ready`; no `VITE_API_BASE_URL` or new API client
is needed. Existing public `VITE_APP_NAME` and `VITE_PWA_SHORT_NAME` control build-time
PWA/offline metadata. The product header and route titles display Mealplaner. Local
metadata overrides are preserved; no actual environment file was overwritten.
All `VITE_*` values are public: never put credentials or private tokens there.

See [DEVELOPMENT.md](docs/DEVELOPMENT.md) for commands, HMR configuration, production
builds, and frontend testing conventions.

## Local setup and development

The existing setup uses Docker Engine with Compose v2, Git, and default loopback
ports 8000 and 5173. No host PHP, Composer, Node/npm, or PostgreSQL installation is required. Runtime
images are pinned to compatible versions and immutable digests. Fedora bind mounts
use shared SELinux labels and generated files use the configured host UID/GID.
For a checkout that has no local environment file, create it once:

```bash
test -f .env || cp .env.example .env
```

Supply local database credentials privately in the ignored environment. Preserve any
existing environment and application key. Keep the example's file-session/file-cache
and synchronous-queue settings until persistence requirements are deliberately changed.
Set `LOCAL_UID`/`LOCAL_GID` to `id -u` / `id -g` before building for host ownership. `APP_NAME` remains configurable;
existing local overrides are preserved rather than overwritten. The current public
name override is `MealPlaner`, while the example/default spelling is `Mealplaner`.
Logging uses the existing stack/single-file channel under `storage/logs/laravel.log`;
never log credentials or request secrets. Never print environment contents,
keys, or resolved configuration containing credentials.

Normal development startup, when needed:

```bash
docker compose up -d --build
docker compose ps
```

The existing entrypoints install locked dependencies into named volumes when needed
and initialize only an empty application key. They never run migrations. Startup fails if a tracked lockfile or the prepared `.env` is missing.
The full [fresh-clone workflow](docs/DEVELOPMENT.md#docker-setup-from-a-fresh-clone)
covers readiness, private configuration, dependency installation, conditional key
initialization, database inspection, safe migration decisions, and testing.
For readiness waiting, add `--wait --wait-timeout 300` to `up`.

| Default URL                        | Purpose                                                                                                       |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `http://localhost:8000`            | Application entry: Laravel serves the Vue SPA.                                                                |
| `http://localhost:5173`            | Vite development modules/HMR, not a standalone application.                                                   |
| `http://localhost:8000/up`         | Laravel liveness.                                                                                             |
| `http://localhost:8000/api/health` | Public, database-independent API liveness: HTTP 200 with `{"status":"ok"}`.                                   |
| `http://localhost:8000/api/ready`  | Existing database-backed readiness: HTTP 200 with `{"status":"ok","database":"connected"}`, or safe JSON 503. |

Vue Router handles `/`, `/about`, and unknown client routes without full reloads.
Laravel excludes API/framework/static namespaces from its SPA fallback. The existing
local filesystem also registers signed GET/PUT `/storage/{path}` routes.

## Code quality

Run the standardized, non-mutating quality gates from the repository root using the
existing containers and mapped users:

```bash
docker compose exec -T --user app backend composer quality
docker compose exec -T --user node frontend npm run quality
```

Both commands fail with a nonzero exit status if any included check fails. They do
not reformat or fix source files; tools may update ignored caches. No host PHP,
Composer, or Node installation is required. Use the existing lockfiles and dev
packages; no additional application or quality-tool installation is needed.

| Capability                      | PHP: `composer <script>`                     | Frontend: `npm run <script>`                                 |
| ------------------------------- | -------------------------------------------- | ------------------------------------------------------------ |
| All quality checks              | `quality`: Pint check, then PHPStan/Larastan | `quality`: ESLint, Prettier check, then Vue TSC              |
| Formatting check                | `format:check`: Pint Laravel preset          | `format:check`: Prettier                                     |
| Lint                            | `lint`: compatible alias for `format:check`  | `lint`: ESLint, including Vue/TypeScript; warnings also fail |
| Static analysis                 | `analyse`: PHPStan level 8 with Larastan     | `type-check`: strict TypeScript/Vue TSC                      |
| Apply formatting (writes files) | `format`: Pint                               | `format`: Prettier                                           |
| Apply lint fixes (writes files) | Use Pint's `format` for PHP style            | `lint:fix`: ESLint fixes; remaining warnings/errors fail     |

PHPStan covers `app`, the two maintained bootstrap files, `config`, `database`,
`routes`, and PHP `scripts`; it does not execute migrations or database verification
scripts. Pint checks maintained PHP source with its Laravel preset. ESLint checks
JavaScript, TypeScript, and Vue using the existing recommended flat configurations;
Prettier handles supported frontend/configuration/documentation files, with PHP and
Blade left to their existing conventions. Generated output, dependencies, runtime
storage, and test coverage are excluded from frontend checks. Vue TSC retains strict
checking, unchecked-index and exact-optional-property checks, and unused-symbol checks
for application code, Vue components, TypeScript tests, and tool configuration.

Formatting/lint-fix commands intentionally modify files: review their scope and the
diff, then rerun both quality gates. Never add broad suppressions or generated ignore
baselines just to pass a check. This task adds no CI workflow or Git hook.

Tests and production builds remain separate checks:

```bash
docker compose exec -T --user app backend composer test
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

The canonical backend command provisions a fresh guarded PostgreSQL test database
and restricted login. It never migrates or resets the development database; the
optional `composer test:sqlite` retains memory-only checks. Build still includes mandatory
Vue TSC checking. See [VERIFICATION.md](docs/VERIFICATION.md) for executed results and
[DEVELOPMENT.md](docs/DEVELOPMENT.md) for container setup and optional integration tests.

## Automated testing

The full backend suite now runs on an isolated PostgreSQL database, using the existing
PHPUnit setup. Vitest and Vue Test Utils test the integrated Vue components/application.
Run unattended commands through the existing containers:

```bash
docker compose exec -T --user app backend composer test
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user app backend composer test:coverage
docker compose exec -T --user node frontend npm run test:coverage
```

Build the frontend first with `npm run build` so the backend production-asset smoke
test executes rather than reporting a skip. After updating this checkout, rebuild the
PHP image for Xdebug and install changed locked npm dependencies through Docker;
see [testing.md](docs/testing.md#setup-and-commands). No host language runtime is needed.

Each canonical backend run creates a random database/login, boots guarded testing
configuration, verifies the actual database identity/permissions before Laravel's
transaction traits, applies ordinary migrations only to that new database, and removes
only its own resources afterward. Database-writing tests use `DatabaseTransactions`;
no database resets are used. `composer test:sqlite` remains available for fast isolated
memory checks. Never run the PostgreSQL XML manually with development credentials.

Coverage uses development-only Xdebug **3.5.3** (off unless requested) and the matching
Vitest V8 provider **4.1.12**. PHP HTML/Clover reports appear under `coverage/php/`;
frontend HTML/LCOV/summary reports under `coverage/frontend/`. JUnit files are written
to `test-results/backend.xml`, `test-results/backend-sqlite.xml`, and
`test-results/frontend.xml`. These generated artifacts are ignored by Git.

The [testing guide](docs/testing.md) documents isolation, migrations, transaction
conventions, report paths, and the serial GitHub Actions command sequence. Current
results and coverage measurements are in [VERIFICATION.md](docs/VERIFICATION.md).
No CI workflow or unrelated application feature is introduced here.

## Verification commands and current results

Run existing tools from the root application through running containers:

```bash
docker compose exec -T --user app backend php artisan --version
docker compose exec -T --user app backend php artisan route:list -v
docker compose exec -T --user app backend composer validate --strict
docker compose exec -T --user app backend composer check-platform-reqs
docker compose exec -T --user app backend composer test
docker compose exec -T --user app backend composer quality
docker compose exec -T --user node frontend npm run quality
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

TASK-03 verification passes: lockfile installation with `npm ci`, **10 frontend
tests**, strict type checking, ESLint, Prettier, production build/artifact verification,
and **3 development browser tests**, including real Vue/CSS HMR without reloading.
One production-offline browser test was skipped in development mode. Chrome also
verified keyboard navigation, titles, direct-route refresh, theme switching, and
responsive layouts at 1440, 768, 390, and 320 pixels. All **15 backend tests / 76
assertions** and Pint still pass; API health/readiness and Laravel routes remain intact.
See [VERIFICATION.md](docs/VERIFICATION.md) for results and remaining dependency advisories.

TASK-04 adds **24 default tests / 98 assertions** and **3 PostgreSQL tests / 49
assertions**, all passing. Default tests use guarded SQLite memory storage; the
PostgreSQL runner provisions a fresh database and restricted login, verifies existing
migrations and commit/reconnect persistence, and removes only those test resources.
It no longer recreates PostgreSQL. `pdo_pgsql` is required and available.

TASK-04 preserved the development database and local environment. During TASK-05,
an initial verification probe mistakenly created two empty cache tables; targeted
cleanup is awaiting approval. No existing records were changed or deleted. See the
[incident record](docs/VERIFICATION.md#verification-incident--cleanup-awaiting-approval)
before any development schema work. Migration history remains uninitialized. `.env.example` now uses nonsecret Mealplaner database
placeholders with a blank password. Keep `DB_HOST=postgres` inside the existing Compose
network; use `127.0.0.1` only for an appropriate host-reachable database. The current
container does not publish a database port. See [database.md](docs/database.md) for
variables, permissions, safe migrations, isolation, and persistence limits.

`/api/ready` remains the database probe; `/api/health` is database-independent.
TASK-05 also passes **25 backend tests / 101 assertions**, all existing PHP/frontend
quality checks, the production build, and **3 development browser checks**. It verifies Docker startup and restart/recreation persistence using an isolated
restricted-role test database on the existing volume. See the verification record for
the initial probe issue and cleanup status. Development browser/HMR checks are rerun; production offline/update/device installation remains
separate verification work.

`npm run dev` starts Vite; `npm run format` modifies files and should be used only
within the intended change scope. `npm run test:e2e` requires deliberate browser/runtime
setup, and its HMR test temporarily edits source. Do not remove database volumes.

## Documentation and next steps

- [AGENTS.md](AGENTS.md): permanent architecture, coding, verification, and safety rules.
- [Architecture](docs/architecture.md): integration, routing, ownership direction,
  infrastructure, detected versions, decisions, and technical debt.
- [Testing guide](docs/testing.md): canonical PostgreSQL tests, safe Laravel utilities,
  component tests, coverage, JUnit artifacts, and unattended CI commands.
- [Verification record](docs/VERIFICATION.md): current stabilization results and the
  historical architecture-audit baseline.
- [Database guide](docs/database.md): PostgreSQL requirements, safe migration workflow,
  restricted integration testing, and persistence verification bounds.
- [Development guide](docs/DEVELOPMENT.md): Docker-only fresh setup, pinned runtimes,
  commands, HMR, safe persistence, backup, Fedora/SELinux, and troubleshooting.
- [Laravel Cloud guide](docs/LARAVEL_CLOUD.md): future single-application deployment
  reference, not an executed deployment. Review provider and secret-handling steps
  against AGENTS.md before any deployment task.
- [Template reuse guide](docs/NEW_PROJECT.md): reference for creating a different
  project from the starter; do not use it to scaffold another application here.

Next steps are authentication and domain features within this integrated application,
plus deliberate dependency/security maintenance. The locked dependency audit reports
26 low/moderate development-graph findings; the production-only audit reports zero.
No forced upgrades, database schema changes, CI workflows, cloud deployments, or
application restructuring were introduced by frontend integration.
