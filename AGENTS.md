# Mealplaner — mandatory Codex instructions

These instructions apply throughout this repository. Follow the user's authorized
task scope, inspect existing work, and preserve unrelated changes.

## Git Branching Policy

- Never implement changes directly on `main`.
- Before starting a development task, inspect the current branch and working tree.
- Create and switch to a dedicated branch named `feature/task-XX-short-description`,
  or `fix/task-XX-short-description` for bug fixes.
- Branch from local `main` unless the task explicitly specifies another base.
- Never discard, overwrite, or stash uncommitted changes without explicit approval.
- If creating or switching branches could affect existing work, stop and report the issue.
- Do not automatically commit, push, merge, or create pull requests unless explicitly instructed.
- Run all relevant tests and quality checks before declaring a task complete.
- Report the active branch and modified files upon completion.

This policy applies to every future Codex development task.

## Preserve the application architecture

- Read `README.md`, `docs/architecture.md`, and relevant implementation/configuration
  before making changes. Treat the code and lockfiles as evidence; do not assume a
  package, feature, or runtime is present.
- Mealplaner is **one Laravel application at the repository root**, with an integrated
  Vue 3/TypeScript SPA in `resources/js`. Blade supplies the HTML entry and Laravel's
  Vite plugin supplies development assets or compiled production assets.
- Extend this application. Never initialize an additional application, a second
  Laravel project, or a second Vue project without explicit approval. Do not create
  `backend/` or `frontend/` application directories.
- Compose services named `backend` and `frontend` are PHP/Vite processes for this
  same root application. They do not represent independent application projects.
- Respect Laravel HTTP/API, Vue Router, and database boundaries. Preserve one
  application deployment and the current same-origin integration.
- Never change the established architecture or introduce Inertia, Livewire, SSR,
  microservices, or replacement frontend integration without approval. If task
  requirements conflict with the architecture, document the conflict and stop before
  making architectural changes.
- Prefer extending existing working functionality over replacing it. Preserve existing
  theme, connectivity, routing, and PWA behavior unless the task explicitly changes it.
- PostgreSQL is the persistent database. Use Laravel's native Eloquent/database
  capabilities as domain features are implemented. Do not invent a separate service
  architecture, full schema, or unnecessary layers in advance.

## Clean code

- Write clean, modern, maintainable code using official framework conventions,
  descriptive names, clear responsibilities, and strong typing where supported.
- Keep functions, classes, and components appropriately focused. Apply SOLID, DRY,
  KISS, and separation of concerns pragmatically.
- Prefer simple solutions over unnecessary dependencies, interfaces, abstractions,
  service/repository layers, or speculative architectural patterns.
- Avoid deeply nested logic, oversized files, duplicated business logic, and compressed
  or minified application source. Generated build artifacts are not application source.
- Use comments to explain non-obvious reasoning rather than restating the code.
- Keep environment-specific configuration outside application logic. Never hardcode
  credentials, tokens, or production environment values.

## Laravel standards

- Follow Laravel's documented conventions and prefer framework-native functionality.
- Use modern PHP features and appropriate parameter, property, and return types.
- Keep controllers focused on HTTP responsibilities. Use native validation, including
  Form Requests for nontrivial validation, authorization policies, and database tools.
- Extract business logic only when complexity warrants it; avoid unnecessary service
  or repository layers. Use Eloquent relationships and appropriate query patterns.
- Enforce authentication, authorization, ownership, and referenced-resource access on
  the server when those features are implemented. Browser checks are not authorization.
- Preserve native session authentication and the integrated Vue account flows.
  Inspect the implementation before extending it; no Sanctum or token system is used.
- Keep new backend routes above the SPA fallback and reserve their namespaces in both
  Laravel routing and the PWA navigation fallback when applicable. API errors must
  retain JSON responses and Laravel's standard HTTP status conventions.

## Vue standards

- Use Vue 3 Composition API and `<script setup lang="ts">` for component logic.
  Template-only components do not need an empty script block.
- Use TypeScript, meaningful types, and safe narrowing. Avoid unsafe assertions and
  unjustified `any`.
- Prefer focused, reusable components and composables for reusable logic. Use Pinia
  only for genuinely shared state; keep local state in components.
- Follow semantic HTML, keyboard accessibility, responsive layout, readable contrast,
  focus visibility, and reduced-motion practices.
- Handle loading, empty, success, and error states where relevant.
- Extend the existing same-origin HTTP integration in `resources/js/lib/` when needed;
  do not introduce a second API client or custom auth flow without justification.
- Treat all `VITE_*` configuration as public browser/build data, never secret storage.

## Testing, verification, and data safety

- Every meaningful functional change requires appropriate automated tests. Bug fixes
  require regression tests where feasible.
- Inspect package scripts and test configuration before executing commands. Use the
  existing tooling and locked versions; do not replace or reinstall the application
  to work around a failed check.
- Run relevant tests, linters, formatters, static analysis, type checks, and builds.
  The verified command locations and known baseline failures are in `README.md` and
  `docs/VERIFICATION.md`.
- For implementation tasks, run both standardized non-mutating quality gates from
  the repository root through Docker before reporting completion:
  `docker compose exec -T --user app backend composer quality` and
  `docker compose exec -T --user node frontend npm run quality`.
  The PHP gate runs Pint and PHPStan/Larastan; the frontend gate runs zero-warning
  ESLint, Prettier checking, and strict Vue TSC. Preserve compatibility of the
  individual scripts documented in `README.md`.
- Run relevant automated tests with `composer test` in the `backend` service and
  `npm run test:unit` in the `frontend` service; verify frontend changes with
  `npm run build` and `npm run verify:build` in that service, using the mapped users above.
  The build must retain type checking. Keep database tests isolated.
- `composer format`, `npm run format`, and `npm run lint:fix` write files. Use them
  deliberately, review the affected scope, then finish with non-mutating checks.
  Quality failures must propagate nonzero status; do not allow warning-only success,
  generate broad ignore baselines, or weaken rules to hide findings.
- Investigate failures rather than suppressing them. Never weaken tests, broadly
  disable diagnostics, bypass platform requirements, or claim success for a command
  that did not complete successfully.
- Fix identified issues within the authorized scope. An audit must report pre-existing
  failures without expanding into unrequested application changes. Distinguish passed,
  failed, blocked, and unexecuted checks explicitly.
- Use isolated testing databases. Confirm the test environment and database before any
  database-writing test. Canonical `composer test` runs all backend suites on a generated
  PostgreSQL database; `composer test:sqlite` / direct Artisan tests retain SQLite
  `:memory:`. Both force testing/array drivers. The PostgreSQL suite receives a fresh
  database and restricted login from
  `scripts/verify-postgres.php`; never pass development credentials to that suite or
  bypass `tests/bootstrap.php`. Never fall back to a development database.
- Extend the shared `Tests\TestCase` for Laravel tests. Its database/role checks must
  run before transaction/migration traits. Use `DatabaseTransactions` for database
  writes; the guarded base applies ordinary migrations to the generated test database.
  Do not introduce `RefreshDatabase`, `DatabaseMigrations`, or resets incidentally.
  Committed-record tests require narrowly targeted cleanup in their generated database.
- Use `composer test:coverage` in the `backend` service and `npm run test:coverage`
  in the `frontend` service for coverage verification. Preserve the app/routes and
  TypeScript/Vue coverage scope, including uncovered executable files. Do not hide
  gaps with exclusions or arbitrary lowered thresholds. Reports belong in ignored
  `coverage/` and `test-results/`, never Git. PHP coverage requires the provided
  development Xdebug extension; keep it off for ordinary runtime requests.
- Keep Vitest and its V8 coverage provider compatible, tests non-interactive by default,
  and failure/cleanup exit codes intact. Build frontend assets before the backend
  production-asset test. Follow `docs/testing.md` for commands and isolation conventions;
  never pass development credentials to test processes or invent parallel-worker setup.
- Database-writing verification must boot the guarded testing environment with its
  restricted credentials before Laravel starts. Switching a named connection inside
  an application already booted with development credentials is insufficient isolation.
- Never reset the development database. Do not run `migrate:fresh`, database resets,
  destructive SQL, or database-volume removal against development or production data.
  Run migrations only when the task authorizes them, after confirming the target.
- The PostgreSQL verification runner creates and removes only its own fresh test
  database/login, and verifies reconnect persistence without restarting PostgreSQL.
  The HMR browser test temporarily edits source files. These are not read-only audit
  commands; inspect their effects and task scope before running them.
- During audits, record missing dependencies rather than installing them automatically.
  Prefer already-running containers when host dependency volumes are unavailable;
  do not start/restart services or run dependency-installing entrypoints incidentally.
- Never inspect, print, or expose secrets. Do not open real local environment files,
  dump resolved Compose configuration or application environment, display application
  keys, or include credentials in logs, commands, documentation, or responses. Use
  safe metadata, examples, and managed secret workflows. Synthetic test fixtures
  must not contain real credentials.
- Keep `.github/workflows/ci.yml` aligned with the documented quality/test/build
  scripts. Preserve lockfile installs, ephemeral CI database credentials, action SHA
  pins, read-only permissions, and nonzero failure propagation. Do not claim a hosted
  run passed based only on local checks or add deployment steps incidentally.
- Never commit or push without explicit approval.

## Task discipline

For every future task:

1. Inspect the existing implementation, instructions, dependencies, and configuration.
2. Identify the smallest necessary changes and any architectural conflict.
3. Implement within the authorized scope using established conventions.
4. Add or update relevant tests for functional changes.
5. Run appropriate verification commands with isolated test data.
6. Investigate failures and fix issues within scope; accurately document limitations.
7. Review the final diff for unrelated changes, secrets, and unnecessary complexity.
8. Report changes, test results, failed or unexecuted checks, and remaining limitations.

Do not perform unrelated refactoring or expand scope without justification. Documentation
tasks require consistency, link, formatting, and diff checks; they do not justify new
features, application scaffolds, dependency updates, or infrastructure changes.
