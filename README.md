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

There are no user/domain models or domain migrations yet. PostgreSQL connectivity
works in the current development environment; schema, persistence recovery, and
Mealplaner workflows have not been implemented or validated by this audit.

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
tests/{Feature,Unit}/       PHPUnit tests with isolated SQLite memory storage
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
ports 8000 and 5173. No host PHP/Node installation is required for this workflow.
For a checkout that has no local environment file, create it once:

```bash
test -f .env || cp .env.example .env
```

Supply local database credentials privately in the ignored environment. Preserve any
existing environment and application key. Keep the example's file-session/file-cache
and synchronous-queue settings until persistence requirements are deliberately changed.
Set local UID/GID appropriately for host ownership. `APP_NAME` remains configurable;
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
and initialize only an empty application key. They never run migrations. Startup is
not an audit command: this audit reused already-running containers and installed nothing.
See [DEVELOPMENT.md](docs/DEVELOPMENT.md) for the existing detailed workflow.

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

## Verification commands and current results

Run existing tools from the root application through running containers:

```bash
docker compose exec -T --user app backend php artisan --version
docker compose exec -T --user app backend php artisan route:list -v
docker compose exec -T --user app backend composer validate --strict
docker compose exec -T --user app backend composer check-platform-reqs
docker compose exec -T --user app backend php artisan test
docker compose exec -T --user app backend ./vendor/bin/pint --test
docker compose exec -T --user app backend ./vendor/bin/phpstan analyse --no-progress
docker compose exec -T --user node frontend npm run type-check
docker compose exec -T --user node frontend npm run lint
docker compose exec -T --user node frontend npm run format:check
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

PHPUnit forces an isolated in-memory SQLite connection and array drivers. Never reset
the development database or run migrations as an incidental verification step.
The existing connection-check UI now calls `/api/ready`; `/api/health` deliberately
does not report database availability. PostgreSQL settings and the running service
remain unchanged. Domain schema, migrations, and persistence/recovery validation
belong to later tasks. The separate PostgreSQL probe creates/drops a test database
and recreates its service; it was not executed. Development browser/HMR checks
passed in TASK-03; production offline/update/device-installation behavior remains
separate verification work.

`npm run dev` starts Vite; `npm run format` modifies files and should be used only
within the intended change scope. `npm run test:e2e` requires deliberate browser/runtime
setup, and its HMR test temporarily edits source. Do not remove database volumes.

## Documentation and next steps

- [AGENTS.md](AGENTS.md): permanent architecture, coding, verification, and safety rules.
- [Architecture](docs/architecture.md): integration, routing, ownership direction,
  infrastructure, detected versions, decisions, and technical debt.
- [Verification record](docs/VERIFICATION.md): current stabilization results and the
  historical architecture-audit baseline.
- [Development guide](docs/DEVELOPMENT.md): containers, HMR, persistence, and existing
  verification procedures; inspect operations before executing them.
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
