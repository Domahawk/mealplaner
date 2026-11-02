# Mealplaner — mandatory Codex instructions

These instructions apply throughout this repository. Follow the user's authorized
task scope, inspect existing work, and preserve unrelated changes.

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
- Authentication is currently absent. Inspect the actual capabilities before adding
  native authentication; do not assume Sanctum, a user model, or auth routes exist.
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
- Investigate failures rather than suppressing them. Never weaken tests, broadly
  disable diagnostics, bypass platform requirements, or claim success for a command
  that did not complete successfully.
- Fix identified issues within the authorized scope. An audit must report pre-existing
  failures without expanding into unrequested application changes. Distinguish passed,
  failed, blocked, and unexecuted checks explicitly.
- Use isolated testing databases. Confirm the test environment and database before any
  database-writing test. Current PHPUnit configuration uses SQLite `:memory:` and
  array session/cache drivers. Never fall back to a development database.
- Never reset the development database. Do not run `migrate:fresh`, database resets,
  destructive SQL, or database-volume removal against development or production data.
  Run migrations only when the task authorizes them, after confirming the target.
- The existing PostgreSQL verification script creates/drops a test database and
  recreates the PostgreSQL container; the HMR browser test temporarily edits source
  files. They are not read-only audit commands. Inspect their effects and task scope
  before running them.
- During audits, record missing dependencies rather than installing them automatically.
  Prefer already-running containers when host dependency volumes are unavailable;
  do not start/restart services or run dependency-installing entrypoints incidentally.
- Never inspect, print, or expose secrets. Do not open real local environment files,
  dump resolved Compose configuration or application environment, display application
  keys, or include credentials in logs, commands, documentation, or responses. Use
  safe metadata, examples, and managed secret workflows. Synthetic test fixtures
  must not contain real credentials.
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
