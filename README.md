# Mealplaner

Mealplaner will manage nutritional food products, recipes, and daily calorie and
macronutrient intake. Its first milestone is **MVP v0.1.0**.

The repository currently contains an integrated Laravel + Vue 3 PWA starter, not the
completed Mealplaner product. It is **one root Laravel application**, with Vue and
TypeScript under `resources/js`, a Blade HTML entry, and Laravel's Vite plugin.
The browser uses same-origin REST endpoints. Production is one application deployment;
Node/Vite provide development assets and build the frontend bundle.

## Current capabilities and MVP scope

Implemented starter capabilities include welcome/about/not-found views, Vue Router
navigation, Pinia-backed light/dark/system preferences, a user-triggered API/database
connection check, Laravel liveness, and production PWA build support with an offline
shell and prompted updates. The branding and default application name are still generic.
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
Set local UID/GID appropriately for host ownership. Never print environment contents,
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

| Default URL                        | Purpose                                                                                              |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `http://localhost:8000`            | Application entry: Laravel serves the Vue SPA.                                                       |
| `http://localhost:5173`            | Vite development modules/HMR, not a standalone application.                                          |
| `http://localhost:8000/up`         | Laravel liveness.                                                                                    |
| `http://localhost:8000/api/health` | Database-backed readiness: HTTP 200 with `{"status":"ok","database":"connected"}`, or safe JSON 503. |

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
docker compose exec -T --user node frontend npm run test
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

Current audit results: **10 frontend tests pass**, type checking, lint/format checks,
production build/artifact verification, PHPStan, Composer checks, and live health
requests pass. **PHPUnit has 10 passing and 2 failing tests; Pint reports 2 style
issues.** These existing failures were investigated and documented without modifying
application code. See [VERIFICATION.md](docs/VERIFICATION.md) for diagnoses and commands.
The starter is not fully green.

PHPUnit forces an isolated in-memory SQLite connection and array drivers. Never reset
the development database or run migrations as an incidental verification step.
The separate PostgreSQL probe creates/drops a test database and recreates its service;
it was inspected but not run. Browser/HMR/offline checks were not executed in this audit.

`npm run dev` starts Vite; `npm run format` modifies files and should be used only
within the intended change scope. `npm run test:e2e` requires deliberate browser/runtime
setup, and its HMR test temporarily edits source. Do not remove database volumes.

## Documentation and next steps

- [AGENTS.md](AGENTS.md): permanent architecture, coding, verification, and safety rules.
- [Architecture](docs/architecture.md): integration, routing, ownership direction,
  infrastructure, detected versions, decisions, and technical debt.
- [Verification record](docs/VERIFICATION.md): results from this audit and known failures.
- [Development guide](docs/DEVELOPMENT.md): containers, HMR, persistence, and existing
  verification procedures; inspect operations before executing them.
- [Laravel Cloud guide](docs/LARAVEL_CLOUD.md): future single-application deployment
  reference, not an executed deployment. Review provider and secret-handling steps
  against AGENTS.md before any deployment task.
- [Template reuse guide](docs/NEW_PROJECT.md): reference for creating a different
  project from the starter; do not use it to scaffold another application here.

Recommended follow-up work is to address the two existing backend test mismatches and
Pint issues, then implement branding/auth/domain features within this integrated
application. No database schema change, CI workflow, cloud deployment, or application
restructuring is part of this audit.
