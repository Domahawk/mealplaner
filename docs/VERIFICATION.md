# Verification record

## TASK-15 — Product management UI (2026-11-06)

Created `feature/task-15-product-ui` from clean local `main` at `53c4893` before
editing. The root Laravel/Vue architecture, Product API, models, Policy, schema,
lockfiles and dependencies are preserved. [Product UI](product-ui.md) documents
behavior and prerequisites.

### Implementation and changed files

The authenticated Products navigation/page displays product cards with exact API
decimal strings, kcal/macronutrient units per 100 g, brand and optional package weight.
An inline form creates/edits products with client feedback and Laravel field errors.
A styled native HTML dialog names the deletion target, focuses cancellation and
requires confirmation; Escape cancels when idle. Pending saves/deletions cannot be
submitted twice. Loading, empty, success, retryable failure and session-expiry states
are visible and accessible. Creation opens the last page; edits refresh in place;
deleting the final record on a page returns to the last available page.

Typed Product API functions reuse the extracted same-origin JSON transport from
`lib/api.ts`, retaining cookies, XSRF headers, no-store and the existing timeout.
Authentication retains its response/error contract. Page-local state avoids an
unnecessary store, ignores stale requests after navigation/account changes and
never stores products in browser storage. Session refresh after 419 never replays
mutations; success remains explicit if a subsequent list refresh fails.

Laravel protects the new `/products` entry with `auth`/private-no-store and reserves
unknown subpaths from the SPA fallback. Workbox excludes `/products` from offline
fallback, matching `/account` and `/api`. No Product API or schema change was needed.

Created:

- `resources/js/views/ProductsView.vue`
- `resources/js/components/{ProductForm,DeleteProductDialog}.vue`
- `resources/js/lib/products.ts`
- `tests/frontend/products.test.ts`
- `docs/product-ui.md`

Modified: `resources/css/app.css`, `resources/js/{App.vue,lib/api.ts,router/index.ts,
views/AccountView.vue}`, `routes/web.php`, `vite.config.ts`,
`tests/Feature/AuthorizationTest.php`, `tests/e2e/application.spec.ts`, `README.md`,
`docs/{architecture,authorization,products,product-api,testing,VERIFICATION}.md`.

### Executed checks

All language/tool checks use the existing Docker services and mapped users:
`docker compose exec -T --user app backend` for PHP,
`docker compose exec -T --user node frontend` for npm. Locked dependencies were not
installed or changed. Both mandatory quality gates were executed.

| Command/check                            | Result                                                                                                                                                             |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `composer quality`                       | Passed: Pint 57 files and PHPStan/Larastan level 8, no errors.                                                                                                     |
| `composer test:coverage`                 | Passed: 224 tests / 4,267 assertions, no skips; 99.40% app/routes line coverage (166/167). Generated PostgreSQL database and restricted role removed.              |
| `composer test:sqlite`                   | Passed: 186 tests / 2,268 assertions, memory-only tests.                                                                                                           |
| `npm run quality`                        | Passed: zero-warning ESLint, Prettier and strict Vue TSC.                                                                                                          |
| `npm run test:unit`                      | Passed: 50 tests across four files, including 18 new Product UI cases and all existing auth/connectivity/theme tests.                                              |
| `npm run test:coverage`                  | Passed: 50 tests, 93.60% frontend line coverage, HTML/LCOV/summary generated with unchanged coverage scope.                                                        |
| `npm run build` / `npm run verify:build` | Passed: 57 modules, integrated production Vite/PWA assets and artifact checks.                                                                                     |
| Production Playwright application suite  | Passed: 2 tests; 1 development-only test skipped. Includes offline denial of `/products` and `/account`, public SPA routing, same-origin readiness and PWA assets. |
| `php artisan migrate:status`             | Read-only: users/cache applied; existing products migration pending. No development migration ran.                                                                 |

New component tests use real App/router/Pinia instances with controlled fetch responses.
They verify rendering, route guarding, client and Laravel validation, preserved inputs,
optional-field clearing, exact request payloads/CSRF, duplicate prevention, native-dialog
cancellation, confirmed deletion, safe errors, pagination including an emptied final
page, 401/419 handling, malformed responses and success followed by refresh failure.
JSDOM's missing dialog methods are shimmed only in the test file; no production
fallback or browser-confirm usage was added. Laravel tests verify the private HTML
entry, JSON guest denial and unknown private subpaths.

### Actual browser and HTTP verification

Chrome used the already-installed Playwright 1.64.1 tooling copied in the earlier
verification workspace; no package or browser download was needed. A temporary
PHP development server served the built production assets using a generated,
restricted PostgreSQL database, synthetic application key and private temporary file
sessions. The existing guarded provisioning/cleanup mechanism was reused; its child
verified testing mode, actual database/role identity and restricted permissions before
applying ordinary existing migrations. Development configuration and data were never
used for these writes. Temporary server/session/public-symlink resources were cleaned
up, and the parent removed only its generated database/login after each run.

The real browser flow passed:

- Guest route protection; registration/session/XSRF through existing Vue forms.
- Empty state and client validation; create/edit using real Product API responses.
- No document reload for creation; authenticated product visibility after an explicit
  reload; correct optional-package clearing and fixed-scale kcal/macronutrient display.
- Real API pagination across 21 owned products; confirmed deletion and list refresh.
- Modal cancellation focus, Tab/Shift+Tab between actions, background controls unable
  to receive focus, Escape dismissal and focus return to the originating delete action.
- List and edit form at 1440, 390 and 320 px without horizontal overflow; light/dark
  screenshots and the mobile confirmation dialog inspected. Nine screenshots remain
  as temporary artifacts outside Git. The final pass used reduced motion for stable
  captures and verified the existing theme control.
- Logout invalidation and denial of a subsequent direct private navigation.

An additional layout-only Chrome pass with synthetic session/list responses verified
701, 720, 768, 820 and 960 px widths without horizontal overflow, using the normal
Laravel/Vite origin and no development writes. The existing production Playwright
application suite also passed (2 tests, 1 development-only skip), including the new
offline `/products` exclusion. Its copied tooling lacked an npm binary shim; invoking
the existing Playwright CLI JavaScript directly resolved that without installation.

An initial keyboard probe incorrectly assumed native modal reverse-tab traversal
could not reach browser chrome; it was corrected to test modal actions and background
inertness, preserving native dialog behavior. The complete browser flow then passed.

Read-only requests through the normal Nginx origin returned health/readiness JSON 200,
`/products` guest redirect 302, `/products/missing` 404 and `/api/products` JSON 401.
Health retained exact `{"status":"ok"}`. No authenticated browser record was created
in the development database.

### Limits and safety

The existing development products migration remains pending; follow the reviewed
migration workflow before using CRUD against it. No migration/model/API contract,
development record, environment/key, database volume or dependency was changed.
Search/filtering (TASK-16), recipes and tracking remain out of scope. HMR was not
repeated; hosted CI was not executed for uncommitted changes. No commit, push, merge
or PR creation occurred. Active branch: `feature/task-15-product-ui`.

## TASK-14 — Product CRUD API and authorization (2026-11-06)

Created `feature/task-14-product-api` from clean local `main` at `1f16cc8` before
editing. Reused the Product schema/model/factories/Policy and native web session
authentication; no schema, dependencies, frontend or infrastructure changed.

### Implementation and files

`ProductController`, `ProductRequest`, `ListProductsRequest` and `ProductResource`
implement the `/api/products` resource routes registered in `routes/web.php`.
GET listing/show and PUT/PATCH updates return 200, POST returns 201 and DELETE
returns empty 204. Lists are ordered by ascending ID, default to 20 per page and
validate page size up to 100. Individual and collection responses use Laravel
Resource data wrapping with explicit product fields and exact decimal strings;
collections include native links/meta. Successful responses are private/no-store.
See [product-api.md](product-api.md) for the complete input/output contract.

All routes retain web cookies, session locking and CSRF, require native `auth` and
invoke ProductPolicy through Gate. Scoped binding looks up item IDs only through
the authenticated user's products, before payload validation. Listing/counts and
creation also use that relationship. Missing/foreign/invalid IDs return JSON 404;
guest requests return JSON 401 subject to native CSRF rejection (419) on unsafe
requests. Client owner/ID/timestamp input is rejected, including null values.
Validation enforces text bounds, nullable optional fields, numeric ranges and decimal
precision; no silently rounded excess precision or user-selected ownership is accepted.

Created:

- `app/Http/Controllers/ProductController.php`
- `app/Http/Requests/{ProductRequest,ListProductsRequest}.php`
- `app/Http/Resources/ProductResource.php`
- `tests/Feature/ProductApiTest.php`
- `docs/product-api.md`

Modified: `routes/web.php`, `tests/Feature/AuthenticationTest.php`, `README.md`,
`docs/{architecture,authorization,products,testing,VERIFICATION}.md`.

### Executed verification

Commands ran through existing Docker services, using `--user app backend` for PHP
and `--user node frontend` for npm. No dependency install or service restart was needed.

| Command/check                                    | Result                                                                                                                                                                                      |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer quality`                               | Passed: Pint 57 files and PHPStan/Larastan level 8, no errors.                                                                                                                              |
| `composer test`                                  | Passed: initial complete Product API suite, 220 tests / 4,163 assertions.                                                                                                                   |
| `composer test:coverage`                         | Expanded suite passed: 223 tests / 4,244 assertions, no skips; 99.40% app/routes line coverage (165/166), HTML/Clover generated. Temporary PostgreSQL database and restricted role removed. |
| `composer test:sqlite`                           | Passed: 185 tests / 2,253 assertions, memory-only smoke coverage.                                                                                                                           |
| `npm run quality`                                | Passed: zero-warning ESLint, Prettier and strict Vue TSC.                                                                                                                                   |
| `npm run test:unit`                              | Passed: all 32 existing frontend tests.                                                                                                                                                     |
| `npm run build` / `npm run verify:build`         | Passed: 50 modules, integrated Vite/PWA production assets and artifact checks.                                                                                                              |
| `php artisan route:list --path=api/products -vv` | Five resource entries, including shared PUT/PATCH; web, auth and private/no-store middleware retained.                                                                                      |
| `php artisan migrate:status`                     | Read-only: existing users/cache migrations applied, existing products migration pending. No development migration ran.                                                                      |

Tests cover a complete owner CRUD lifecycle, exact response fields/decimals, optional
information, valid bounds, trimmed/ignored input, stable pagination and owner-only
counts, guests on every operation, explicit authorization denials, foreign/missing
IDs with valid and invalid payloads, malformed IDs, JSON errors for HTML requests,
read-only fields, required versus partial updates, and invalid numeric types/ranges/
precision. Every mutation additionally exercises CSRF rejection and acceptance.
The existing file-session authentication harness now verifies Product access through
real login cookies, fresh guards/session stores, encrypted XSRF headers and logout
invalidation without `actingAs`.

Initial static analysis required explicit nullable-user guards in controller queries;
these were added without suppressions. Initial tests also found that native cache
middleware applies only to cacheable methods: Resource response hooks and DELETE
now explicitly set no-store. A new unchanged-record assertion compared factory and
reloaded attribute-array insertion order; taking its baseline from persisted state
preserves strict value/type comparisons. Final review also reproduced acceptance
of embedded null bytes in product names/brands with two failing PostgreSQL cases.
Native `not_regex` validation now rejects these before persistence; both POST and
PATCH regressions pass on PostgreSQL and SQLite. Final suites pass without relaxed
rules.

Read-only HTTP requests through existing Nginx at `127.0.0.1:8000` returned 200 for
`/`, `/up`, `/api/health` and `/api/ready`. Health retained exact JSON
`{"status":"ok"}`. Guest `/api/products` and `/api/products/1` returned JSON 401.
Authenticated HTTP behavior was verified by Laravel feature/integration requests
in the generated database, not by writing to the live development database.

### Limits and safety

Apply the existing products migration only through the reviewed migration workflow
before using authenticated CRUD against the local development database. No development
migration, record write, schema change, reset, environment edit, key rotation or
volume operation occurred. SQLite remains optional; PostgreSQL is authoritative for
the existing numeric/CHECK constraints. Search, filtering, frontend integration and
recipe/food-log features remain future work. Browser/HMR and hosted GitHub Actions
were not rerun for these backend-only, uncommitted changes. No commit, push, merge
or PR creation occurred; all work remains on `feature/task-14-product-api`.

## TASK-13 — product database foundation (2026-11-04)

Started from a clean local `main` at `edaa703` and created
`feature/task-13-product-model` before editing. The root Laravel/Vue architecture,
existing authentication and routes are preserved.

### Schema and changes

Added `Product`, its migration and factory, User's products relationship and a native
User factory. Numeric columns store nutrition per 100 g: kcal `numeric(8,2)`, each
macronutrient `numeric(5,2)`; nullable package grams use `numeric(10,3)`. The schema
requires an owner, nonblank name and supplied brand, nonnegative calories, macros
between 0 and 100 and positive supplied package weight. It rejects non-finite values.
Owner deletion is restricted; a non-unique `(user_id, name)` index supports scoped
queries. Decimal casts preserve fixed-scale strings. [Product decisions](products.md)
explain rounding, limits, optional fields and future recipe/log references.

`ProductPolicy` follows AGENTS.md's real-model policy requirement. Automatic discovery,
guest denial and owner/foreign action checks are covered. The Policy and guarded
owner assignment do not replace query scoping or HTTP authorization in future CRUD.
No product endpoint, UI, recipe/log table, dependency or architectural layer was added.

Changed files: `app/Models/Product.php`, `app/Models/User.php`,
`app/Policies/ProductPolicy.php`, `database/factories/{Product,User}Factory.php`,
`database/migrations/2026_11_04_000001_create_products_table.php`, `composer.json`,
`tests/{Feature,Postgres}/ProductTest.php`, `README.md`, `docs/products.md`,
`docs/architecture.md`, `docs/authorization.md`, `docs/database.md`, `docs/testing.md`
and this record. Composer's only change registers native factory autoloading;
both dependency lockfiles are unchanged.

### Executed verification

Commands used existing Docker services with the documented users:
`docker compose exec -T --user app backend` for PHP and
`docker compose exec -T --user node frontend` for npm.

| Check                                                  | Result                                                                                                                                                              |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer dump-autoload --no-interaction --no-scripts` | Passed; registered factory classes without installing packages.                                                                                                     |
| `composer validate --strict`                           | Passed; unchanged dependency lockfile remains valid.                                                                                                                |
| `composer quality`                                     | Passed: Pint 52 files and PHPStan/Larastan level 8, no errors.                                                                                                      |
| `composer test`                                        | Initial run caught a new test's SQLSTATE mismatch; corrected and verified by the final full coverage run.                                                           |
| `composer test:coverage`                               | Passed: 126 tests, 2,100 assertions; 99.02% app/routes statement coverage (101/102), Clover/HTML generated. Test database and restricted role removed successfully. |
| `composer test:sqlite`                                 | Passed: 88 tests, 885 assertions; in-memory model smoke coverage only.                                                                                              |
| `npm run quality`                                      | Passed: ESLint with zero warnings, Prettier and strict Vue TSC.                                                                                                     |
| `npm run test:unit`                                    | Passed: 32 existing frontend tests.                                                                                                                                 |
| `npm run build` / `npm run verify:build`               | Passed: integrated Vite/PWA production assets and artifact checks.                                                                                                  |
| `php artisan migrate:status`                           | Read-only check passed: users and cache migrations applied, products pending. No development migration ran in TASK-13.                                              |

New tests verify native factory ownership, both relationship directions, nullable
package information, serialized decimal strings, duplicate-name allowance, scoped
cross-user lookup, mass-assignment protection and Policy discovery/denials. PostgreSQL
tests additionally inspect actual numeric precision/scale and index metadata, exact
stored decimals and arithmetic, rounding, bounds, direct-write rejection, foreign
keys and owner deletion restrictions. Writes use the existing guarded generated test
database and transaction/savepoint isolation; no development data is used.

The first PostgreSQL run correctly blocked owner deletion, but that new test expected
foreign-key SQLSTATE `23503`. PostgreSQL reports the more specific `23001`
(`restrict_violation`) for `ON DELETE RESTRICT`; the expectation was corrected without
changing the constraint or accepting generic errors. Final suites pass.

Read-only HTTP requests through existing Nginx returned 200 for `/`, `/up`,
`/api/health` and `/api/ready`; health retained exact JSON `{"status":"ok"}`.
Guest `/account` redirected to `/login` (302), and `/api/auth/user` returned JSON 401.
The initial sandbox HTTP attempt was denied socket access; the same checks succeeded
with approved local network access. No browser/HMR check was repeated: frontend code
and integration were unchanged.

### Remaining scope and safety

The new migration intentionally remains pending in development. Apply it later only
after reviewing and confirming the target through the normal migration workflow.
SQLite cannot establish PostgreSQL numeric/CHECK behavior. Product CRUD, validation,
recipe/log references and historical nutrition/deletion workflows belong to later tasks.
No existing development records, migration files/history, databases, volumes or local
environment files were altered or reset. No production deployment, commit, push,
merge or pull request was performed. Hosted CI was not executed for these uncommitted
changes. Active branch: `feature/task-13-product-model`.

## TASK-12 — authentication security verification (2026-11-04)

Started with a clean tree on local `main` at `3950439`. Created and switched to
**`feature/task-12-auth-security` from local `main` before editing**. Reused the
existing session, CSRF, authorization, route and Vue tests rather than adding another
framework or placeholder models. [Security verification](security-verification.md)
contains the coverage inventory, findings, fixes and remaining limits.

### Findings and changes

New regressions initially failed for hash-shaped password input and missing session
locking, plus two Vue failures: non-JSON 401 handling and another tab's 409 conflict.

- Registration now explicitly hashes all validated password input using native
  `Hash::make`. A bcrypt-looking password is treated as plaintext, not an existing hash.
- Native per-session request blocking now covers the complete web-session lifecycle,
  including reads, with the existing lock-capable cache. The lease is 120 seconds;
  acquisition waits at most five seconds. Stateless API health remains independent.
- Authentication status handling survives an empty/non-JSON response body; successful
  malformed identity responses still fail closed.
- Login/register 409 conflicts trigger a safe session read; only a server-confirmed
  user permits progress. No mutation is replayed and an unverified 409 grants no access.

Added 18 backend cases and six frontend cases. They extend existing fixtures for
throttle normalization/cookie reset/expiry/IP bounds, credential types, password
policy, session expiry, stale CSRF tokens, Secure-cookie flags, file/array session
locks and client failure/recovery. Existing duplicate registration, hash byte/null
limits, two-user access, session rotation/invalidation and 401/403/404 tests are reused.

The session-lock test temporarily disables exception rendering to observe native
`LockTimeoutException`; exception handling is restored before its final expected 401.
Its first harness revision missed that restoration and was corrected without changing
the authentication contract or weakening an assertion.

### Executed commands

PHP commands use `docker compose exec -T --user app backend`; frontend commands use
`docker compose exec -T --user node frontend` from the repository root.

| Command                                  | Actual result                                                                                                                |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `composer test`                          | Passed after fixes: 88 tests, 1,475 assertions at the first complete green run.                                              |
| `composer test:coverage`                 | Final expanded suite passed: 88 tests, 1,481 assertions; app/routes line coverage 98.81% (83/84), Clover/HTML generated.     |
| `composer test:sqlite`                   | Passed: 83 tests, 820 assertions; memory-only SQLite.                                                                        |
| `composer quality`                       | Passed: Pint, 45 files; PHPStan/Larastan, no errors.                                                                         |
| `npm run test:unit`                      | Passed: 32 tests across three files.                                                                                         |
| `npm run test:coverage`                  | Passed: 32 tests; 89.09% line coverage, HTML/LCOV/summary generated.                                                         |
| `npm run quality`                        | Passed: zero-warning ESLint, Prettier and strict Vue TSC.                                                                    |
| `npm run build` / `npm run verify:build` | Passed: type-checked Vite/PWA output and artifact validation.                                                                |
| `composer audit --locked --format=json`  | Passed: no advisories or abandoned packages reported.                                                                        |
| `npm audit --omit=dev --json`            | Passed: zero production vulnerabilities.                                                                                     |
| Full `npm audit --json`                  | Exit 1: 26 existing development findings, 2 low and 24 moderate; no high/critical. No forced upgrade or suppression applied. |

### Actual browser, concurrency and throttling probes

A temporary four-worker PHP server ran the same root application/build in the
existing backend container. Its guard verified testing mode and actual restricted
PostgreSQL database/role identity. Each run provisioned only its own random database,
login and private session/cache/storage directories; credentials stayed in process
memory. Migrations ran only there. No development account or schema was modified.

The final Chrome/HTTP probe exited successfully and verified:

- Actual registration, login, logout, invalid credentials, authenticated page reloads,
  post-logout denial, 320/390/1440-pixel layouts and no browser exceptions.
- Two tabs sharing cookies: one registers, the stale login tab receives a conflict,
  refreshes the server session and reaches its account successfully.
- A controlled read paused after loading the session but before persistence. Logout
  remained queued until that read was released, then returned 204. Both the current
  browser and a context replaying the old cookie received 401 afterward.
- Five case/whitespace variants of an unknown email returned generic 422 errors
  across fresh PHP requests. A new browser session's sixth request received 429
  with Retry-After, proving the private file cache carries the throttle across requests.
- Real cross-site mutation rejection (419) and successful health/liveness responses.

A separate negative control also exited successfully: a switch in the **temporary
guarded router only** disabled session blocking for a paused read and logout. Logout
finished while the read was pending; releasing the read restored authentication,
reproducing the pre-fix race. A normal subsequent logout removed that test session.
No disable-lock or pause hook is present in application routes or production code.

The initial concurrency fixture attempted to use Laravel's response factory before
binding an HTTP request. Its diagnostic response failed, so those initial probes
were not counted as successful. The temporary diagnostic handler was corrected,
and the final complete browser probe plus positive/negative concurrency controls passed.

The temporary worker groups were stopped before dropping only their generated
databases/logins and removing their private storage. Existing development services
and volumes were not restarted or removed. Browser-only secure-context flags were
limited to the temporary private HTTP test origin, not application configuration.

Final read-only development HTTP checks confirmed account redirect 302, protected
API 401 JSON even with HTML Accept, unknown account 404, unsigned storage 403 JSON,
and public login/register/health/liveness 200. The diagnostic test path returns 404
on the real application, and the temporary HTTP listener is closed.

### Scope and final review

No unresolved critical authentication/authorization finding remains identified in
the reviewed current flows. BL-02's account/access-control foundation is verified for
MVP feature development. Future domain Policies/ownership tests, production HTTPS,
Secure cookies, proxy trust, shared stores and bounded request lifetimes remain
requirements; existing PHP session serialization is intentionally preserved.
Password reset, email verification, remember-me and MFA remain outside this scope.
Full-load testing, production deployment and hosted CI were not claimed.

Modified implementation: `app/Http/Controllers/AuthenticationController.php`,
`config/session.php`, `resources/js/lib/api.ts`, `resources/js/stores/auth.ts`.
Tests: `tests/Feature/AuthenticationTest.php`, `tests/Feature/WebSecurityTest.php`,
`tests/frontend/authentication.test.ts`. Documentation: `README.md`,
`docs/security-verification.md`, `docs/authentication.md`, `docs/architecture.md`,
`docs/testing.md` and this record.

Both lockfiles and application architecture remain unchanged. No real local environment
values, application keys or user credentials were displayed. No Git commit, push,
merge or PR was performed. All changes remain on the requested feature branch.

## TASK-11 — authorization (2026-11-04)

Started with a clean tree on local `main` at `406643b`; created and switched to
**`feature/task-11-authorization` from local `main` before editing**. Existing
session authentication, route middleware, requests, controller, model, Gate/policy
usage, Vue state/guards and storage access were inspected.

The effective `auth` alias is Laravel's native `Authenticate`. Web middleware retains
encrypted cookies, sessions, request-forgery prevention and bindings. The API group
contains bindings only; existing account JSON routes intentionally use the web stack.
No User policy or domain resource endpoints exist. The current account uses only
server-session identity, so a policy for arbitrary-user lookup would be unused.

### Changes and regression proof

- Reserved `/account` from the public SPA fallback. The new regression test first
  failed with HTTP 200 for an unknown `/account/...` path; after the route constraint
  changed, guest and authenticated requests correctly return 404. The original
  response was an empty public shell, not a demonstrated data leak.
- Added direct HTTP tests for guest web redirects, JSON/API 401 responses, public
  routes, two-user identity scoping and unsupported private paths. Existing signed
  storage rejection tests remain intact.
- Extended actual session/CSRF tests: registration ignores forged ownership/identity
  fields without overwriting another user, login uses credentials rather than a
  supplied user ID, and logging out Alice cannot invalidate Bob's independent session.
  A guest logout with valid CSRF credentials returns 401.
- Added Vue tests for an API 401 clearing private content, forged Pinia/localStorage
  state failing private navigation, and server identity overriding a URL user selector.
- Added [authorization.md](authorization.md) and mandatory AGENTS conventions for
  user-scoped queries, server-owned relationships, action Policies, foreign-reference
  validation and 401/403/404 contracts. Policy examples are documentation only;
  products, recipes, food logs, placeholder models and migrations were not created.

### Executed checks

PHP commands used `docker compose exec -T --user app backend`; frontend commands
used `docker compose exec -T --user node frontend` in the existing containers.

| Command                                  | Result                                                                                                        |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `composer test`                          | Passed after the route fix: 70 tests, 1,104 assertions.                                                       |
| `composer test:coverage`                 | Final full suite passed: 70 tests, 1,105 assertions; PHP line coverage 98.78% (81/82). Clover/HTML generated. |
| `composer test:sqlite`                   | Passed: 65 tests, 588 assertions, SQLite memory-only.                                                         |
| `composer quality`                       | Passed: Pint checks 45 files; PHPStan/Larastan reports no errors.                                             |
| `npm run test:unit`                      | Initial expanded suite passed: 25 tests.                                                                      |
| `npm run test:coverage`                  | Final expanded suite passed: 26 tests; 88.47% line coverage, HTML/LCOV/summary generated.                     |
| `npm run quality`                        | Passed: zero-warning ESLint, Prettier and strict Vue TSC.                                                     |
| `npm run build` / `npm run verify:build` | Passed: type-checked Vite/PWA build and asset validation.                                                     |

Read-only live HTTP requests to `127.0.0.1:8000`, without automatic redirects,
confirmed `/account` gives **302 to `/login` for HTML** and **401 JSON for JSON**.
`/api/auth/user` returns **401 JSON even with Accept: text/html**. Unknown account
and account-API paths give **404**; login, registration, session bootstrap, health,
`/up` and ordinary public client deep links retain **200** responses.

Test database bootstrap, actual restricted identity checks and transaction guards
remain enabled. Test writes and ordinary migrations run only in generated isolated
databases or memory-only SQLite. Generated PostgreSQL databases/logins are cleaned
up by the existing runner; file sessions use private temporary test directories.
No development schema/data, environment values, keys, volumes, dependencies or
lockfiles were changed. No service restart or new browser/HMR/hosted CI run was needed
or claimed. No Git commit, push, merge or PR was performed.

Changed files: `routes/web.php`, `tests/Feature/AuthorizationTest.php`,
`tests/Feature/AuthenticationTest.php`, `tests/frontend/authentication.test.ts`,
`AGENTS.md`, `README.md`, `docs/authorization.md`, `docs/architecture.md`,
`docs/authentication.md`, `docs/testing.md` and `docs/VERIFICATION.md`.

### Remaining boundaries

There are no owned products, recipes or logs yet, so their Policies, scoped Eloquent
relationships and resource-specific cross-user denials cannot be tested as existing
features. Each future feature must implement and test those rules with its real
model/endpoints. The current signed storage routes enforce URL capabilities, not
account ownership; future private files require authorization before URL issuance.
These limits are explicit in the authorization guide and do not justify placeholder
production features or a new authentication system.

## TASK-10 — complete authentication (2026-11-04)

Started with a clean working tree on local `main` at `2ff0596`. Created and switched
to **`feature/task-10-authentication` from local `main` before modifying files**.
`AGENTS.md` now requires dedicated feature/fix branches, inspection before switching,
preservation of uncommitted work, explicit permission for publication/merging, checks,
and an active-branch/changed-file report for every future development task.

### Implementation

Completed the missing native session authentication using Laravel's existing guard,
Eloquent, hashing, web middleware, validation and throttling. Added the User model,
users migration, account Form Requests and controller, five same-origin JSON routes,
Vue login/register/account views, session-backed Pinia state and navigation checks.
Login/registration rotate session IDs and CSRF tokens. Logout invalidates session
data and regenerates the token. Errors, password limits and safe API serialization
are covered. No dependency, architecture or local-environment replacement was needed.
See [authentication.md](authentication.md) for the full contract and boundaries.

### Commands and results

All PHP checks used `docker compose exec -T --user app backend`; frontend checks
used `docker compose exec -T --user node frontend` from the root checkout.

| Command                                       | Result                                                                                                 |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `composer quality`                            | Passed: Pint checks 44 PHP files; PHPStan/Larastan reports no errors.                                  |
| `composer test`                               | Passed: 58 tests, 879 assertions on generated restricted PostgreSQL storage.                           |
| `composer test:coverage`                      | Passed: 58 tests, 879 assertions; PHP line coverage 98.78% (81/82), Clover/HTML reports generated.     |
| `composer test:sqlite`                        | Passed: 53 tests, 458 assertions, memory-only SQLite.                                                  |
| `npm run quality`                             | Passed: ESLint with zero warnings, Prettier and strict Vue TSC.                                        |
| `npm run test:unit` / `npm run test:coverage` | Passed: 23 tests in three files. Final coverage run reports 87.55% lines; HTML/LCOV/summary generated. |
| `npm run build`                               | Passed: Vue TSC, Vite and production PWA generation.                                                   |
| `npm run verify:build`                        | Passed: entry, CSS, offline shell, worker, manifest and icons.                                         |
| `php artisan route:list --path=auth -v`       | Passed: five auth routes use `web`; current-user/logout also use `auth`; login/register are throttled. |
| `php artisan migrate:status`                  | Users migration ran in local development; unrelated cache migration remains pending.                   |

Backend tests enable actual request-forgery validation without disabling testing mode
or the database guards. File sessions use private temporary test directories. Fresh
guards/session instances recover identity from cookies and persisted files. Tests
verify hashed account storage, validation, generic invalid credentials, throttling,
rotation, CSRF rejection on real endpoints, protected HTTP routes and failure to
reuse an old session after logout. Vue tests use real router/Pinia instances and
mock only HTTP; they cover forms, validation, network errors, expired CSRF recovery,
session restoration, logout and expired-session navigation.

### Browser and live HTTP verification

A temporary PHP server served built assets and real Laravel routes from a fresh
restricted PostgreSQL test database. Its guard checked effective testing configuration
and actual database/role identity before serving account requests. Only synthetic
users were created. Host Chrome/Playwright verified:

- Guest denial, real registration, private account navigation, authenticated browser
  reloads, logout and post-logout denial.
- Invalid credentials with field feedback/password clearing, then successful login
  and another authenticated reload.
- Mobile/desktop widths of 320, 390 and 1440 pixels without horizontal overflow.
  Mobile screenshots were inspected; no browser exceptions occurred.
- A real cross-site mutation without a CSRF header returns HTTP 419; health and
  framework liveness remain HTTP 200.
- Production application browser suite: **2 passed, 1 development-only skip**.
  The worker installs and serves public routes offline, caches no API responses,
  and refuses offline `/account` navigation. Chrome's temporary test-origin flag
  provided a secure context for that private-network HTTP test origin; this does
  not verify production HTTPS configuration.

The production test was copied into an existing temporary Playwright runner because
host dependency directories are Docker volume mount points. One initial copy used
the runner's working directory rather than the repository; absolute source paths
corrected it, and the current test including the private-route assertion then passed.
No application configuration was changed to accommodate the browser runner.

The public application/development-asset browser suite against the existing
`http://localhost:8000` stack also reports **2 passed, 1 production-only skip**.
The source-editing HMR test was not rerun. Read-only live HTTP checks confirm login
and registration entries, guest account redirect to login, session bootstrap 200,
current-user 401 for guests, `/api/health` 200 and `/up` 200.

### Local persistence and safety

Before applying a development migration, verified local mode, PostgreSQL, the
dedicated Mealplaner database and **zero public tables**. Applied only the new users
migration through ordinary `migrate --path=... --force`, creating migration history
and users. No reset or unrelated cache migration ran. Final local metadata shows
only `migrations` and `users`, with **zero development users**.

Both temporary browser test databases/logins/session directories were removed after
their servers stopped. Backend test runners also removed only their own generated
resources. Existing data/volumes, local environment values and application keys were
preserved. No secrets were displayed. No packages were installed, and both lockfiles
remain unchanged. No Git commit, push, merge or PR was performed for TASK-10.

### Changed files and remaining boundaries

- Policy/docs: `AGENTS.md`, `README.md`, `docs/authentication.md`,
  `docs/authentication-audit.md`, `docs/architecture.md`, `docs/database.md`,
  `docs/DEVELOPMENT.md`, `docs/testing.md`, `docs/VERIFICATION.md`.
- Backend: `app/Http/Controllers/AuthenticationController.php`,
  `app/Http/Requests/{LoginRequest,RegisterRequest}.php`, `app/Models/User.php`,
  `app/Rules/BcryptPasswordLimit.php`, `app/Providers/AppServiceProvider.php`,
  `database/migrations/2026_11_04_000000_create_users_table.php`, `routes/web.php`.
- Frontend: `resources/js/{app.ts,App.vue}`, `resources/js/lib/api.ts`,
  `resources/js/router/index.ts`, `resources/js/stores/auth.ts`,
  `resources/js/views/{AuthenticationView,AccountView}.vue`,
  `resources/css/app.css`, `vite.config.ts`.
- Tests: `tests/Feature/AuthenticationTest.php`,
  `tests/frontend/{app,authentication}.test.ts`, `tests/e2e/application.spec.ts`.

Password reset, email verification, remember-me, account editing and domain ownership
remain future work. Production TLS/Secure cookies, shared session/cache storage and
deployment are not verified here. Hosted CI for these uncommitted changes was not run.

## TASK-09 — authentication audit (2026-11-04)

The [authentication audit](authentication-audit.md) inspected registration/login/logout
capabilities, routes and middleware, framework auth/hashing defaults, session/CSRF
behavior, and Vue routing/state. **Account authentication is absent**, including
the User model/schema and account endpoints/UI. Native framework services are
available; their availability is not evidence of implemented account flows.

Added 12 test cases: synthetic password hashing/checks, anonymous file-session
persistence across a fresh application instance, cookie attributes and tampering,
CSRF rejection/acceptance, guest JSON rejection on a test-only protected route,
and unsigned private upload rejection without a file write. Test routes exist only
inside PHPUnit. File sessions use private temporary test directories, never the
development session directory. The CSRF tests disable only Laravel's testing
shortcut; testing mode and database guards remain enabled.

All commands below ran in the existing Docker containers. Backend commands use
`docker compose exec -T --user app backend`; frontend commands use
`docker compose exec -T --user node frontend`.

| Command                  | Actual result                                                                                     |
| ------------------------ | ------------------------------------------------------------------------------------------------- |
| `composer quality`       | Passed: Pint, 37 files; PHPStan/Larastan, no errors.                                              |
| `composer test`          | Passed: 46 tests, 608 assertions on a generated restricted PostgreSQL test database.              |
| `composer test:coverage` | Passed: 46 tests, 608 assertions; Clover/HTML generated, app/routes line coverage 96.77% (30/31). |
| `composer test:sqlite`   | Passed: 41 tests, 283 assertions; SQLite memory-only.                                             |
| `npm run quality`        | Passed: zero-warning ESLint, Prettier check, strict Vue TSC.                                      |
| `npm run test:unit`      | Passed: 12 tests across two files, including existing public component/routing behavior.          |
| `npm run build`          | Passed: Vue TSC and Vite production build; PWA output generated.                                  |
| `npm run verify:build`   | Passed: production entry, CSS, offline shell, manifest, worker and icons.                         |

The initial session-persistence test failed because Laravel's JSON test client
omits cookies by default. Enabling credentials in that test harness fixed the
failure; no application setting was changed. The full suite then passed on both
database configurations. PostgreSQL runners removed only their generated databases
and logins; no development tables, credentials, migrations or records were changed.

Read-only live HTTP checks against `127.0.0.1:8000` verified:

- `/`, `/about` and `/login`: HTTP 200 with the SPA entry. `/login` is an unknown
  client route, not an implemented login form.
- `/api/health`: HTTP 200, JSON `{"status":"ok"}`.
- `/api/ready`: HTTP 200, JSON `{"status":"ok","database":"connected"}`.
- `/up`: HTTP 200.
- `/api/_audit/session` and `/api/_audit/protected`: HTTP 404 JSON, confirming the
  test-only probe routes are unavailable in the running application.

Authenticated reload persistence, successful account flows and protected Vue
navigation could not be verified because they do not exist. No new browser/HMR
test, hosted CI run, production assessment, dependency installation, environment
change, application-code change, Git commit or push was performed for TASK-09.
The final diff contains tests and documentation only.

## Hosted CI and approved cleanup follow-up (2026-11-04)

**Current status:** the first GitHub-hosted foundation run is verified, and the
TASK-05 empty-table cleanup is complete. The implementation and foundation checks
are verified within the recorded scope; enforcing SELinux, backup/volume-loss recovery,
and real device installation remain outside this follow-up's verified results.

The user supplied `git@github-dmwhk.com:Domahawk/mealplaner.git` and explicitly
authorized new-branch publishing and removal of the two accidentally created tables
only if they remained empty. SSH access works through that configured alias; no key,
token, local environment value, or credential was inspected or displayed.

### Hosted evidence

- Repository: [Domahawk/mealplaner](https://github.com/Domahawk/mealplaner).
- [Project Foundation CI run 41769545670](https://github.com/Domahawk/mealplaner/actions/runs/41769545670) completed successfully for main
  commit `8069f928733dfc904194c088ff6753607d57767d`, triggered by a push.
- The hosted workflow file matches that committed workflow byte for byte.
- **Verify foundation** and every required setup, locked install, quality, production
  build, backend/frontend coverage, and upload step succeeded. Both post-cache save
  steps succeeded too.
- GitHub cache metadata contains two nonempty caches: npm **40,729,760 bytes** and
  Composer downloads **41,515,884 bytes**, associated with the main branch.
- `foundation-reports` artifact **13294272657** exists, is not expired, and contains
  **426,054 bytes**. This verifies hosted report upload in addition to local generation.

### Hosted failure-handling verification

On the authorized branch, temporary commit
`04e3916dcfdd37ce160e4a36d16fec47fd9e9375` contained one intentionally failing
PHPUnit test. [Run 41771544971](https://github.com/Domahawk/mealplaner/actions/runs/41771544971)
verified that installation, PHP/frontend quality, and production build succeeded,
then **Backend tests and coverage** and **Verify foundation** failed. The later
frontend test step was skipped and report upload still succeeded, proving upload did
not mask the required failure. The probe is removed in this follow-up; no application
or workflow rule was weakened. Final branch verification follows publication of the
clean source, and its latest status is available in
[branch runs](https://github.com/Domahawk/mealplaner/actions?query=branch%3Aci%2Fverify-project-foundation).

A new branch, `ci/verify-project-foundation`, carries these documentation updates.
Its push-triggered run is checked after publication; the evidence above identifies
the already verified main run and does not predict the new branch's result. No merge,
production deployment, or repository-settings change is authorized or performed.

### Approved database cleanup

The pre-cleanup inspection found exactly the two original tables and their primary-key
indexes; both table row counts were zero and no migration repository existed. Cleanup
ran with `ACCESS EXCLUSIVE` locks and `NOWAIT`, rechecked the exact table/column
structure and zero counts, then dropped only `public.cache` and `public.cache_locks`
without CASCADE in one transaction. Post-cleanup inspection found **zero user relations**.
No existing records, unrelated tables, database, role, volume, or migration history
were deleted/reset. No development migrations were run.

An initial catalog-expression type check aborted before any changes; explicit catalog
type casting corrected that check, and the locked cleanup then completed. The operation
was scoped to the user-approved tables and could not proceed if their state changed.

Post-cleanup local checks passed: both Docker quality gates, **34 backend tests /
420 assertions**, and actual HTTP `/up`, `/api/health`, and `/api/ready` returned 200.
Fresh metadata checks confirmed `cache`, `cache_locks`, and `migrations` remain absent.
The existing migration history was preserved rather than initialized incidentally.

The historical sections below record the original unavailable-remote and approval
holds. Those holds are resolved by this dated follow-up; current setup guides and
README now link the verified hosted status and completed cleanup.

## TASK-08 — CI and foundation verification (2026-11-03)

**Status at the end of the initial 2026-11-03 session (superseded by the follow-up above):
locally verified; GitHub-hosted execution was then unverified.** No GitHub remote is configured in this checkout and no authenticated
GitHub integration/CLI is available. The workflow has not been committed/pushed or
observed running on GitHub. The independent TASK-05 development-table cleanup also
remains pending explicit approval.

Added `.github/workflows/ci.yml`, a single Ubuntu 24.04 job for pushes, pull requests,
and manual dispatch. It selects PHP 8.5/Composer 2.10.3 and Node 24, provisions an
isolated PostgreSQL 18.6 service with a job-scoped synthetic bootstrap credential,
installs from both lockfiles, caches only dependency downloads, runs the existing
quality/build/coverage scripts, and uploads JUnit/coverage artifacts. Action releases
were resolved against their official GitHub metadata and pinned to commit SHAs.
No development/production credentials, deployment steps, ignored failures, or weaker
quality rules were introduced.

### Actual local checks

- **Workflow validation:** checksum-verified actionlint **1.7.12** passed. Each Bash
  run block passed `bash -n`; all action SHAs matched official release commits.
  An initial numeric dynamic-port key was corrected to the string key
  `job.services.postgres.ports['5432']` after actionlint caught its type mismatch.
- **Cold local checkout:** cloned the existing local Git repository into a private
  temporary directory and included the uncommitted workflow for validation. No ignored
  environment or dependency directories were copied. A synthetic private environment,
  unique Compose project, and five previously absent named volumes were used.
- **Docker startup:** the existing configuration built/started all four services
  successfully with fresh dependency/database volumes. Entrypoints installed locked
  Composer/npm dependencies and initialized only the new checkout's empty key.
- **SELinux boundary:** this TASK-08 host reports **Disabled**. Shared `:z` mounts
  remain configured, and no host policy was changed. Enforcing-mode operation was
  not reverified in this run; the earlier TASK-05 enforcing result is historical.
- **Migration setup:** verified the new database had zero non-system relations and
  no migration repository, then applied ordinary pending migrations only to that
  confirmed-new verification database. The existing development schema was not used.
- **Declared CI commands:** extracted the install, quality, build, and coverage run
  commands from the workflow and executed them through the new project's Docker
  services with `CI=true`. Strict Composer validation, `composer install`, platform
  checks, `npm ci`, both quality gates, build/artifact validation, and both coverage
  suites passed. This executes the same scripts; it does not emulate hosted actions,
  cache restoration, event delivery, or artifact upload.
- **Backend:** **34 tests, 420 assertions**, generated restricted-role PostgreSQL
  database, no failed tests; temporary test database/login removed. PHP coverage
  remained **96.77% lines (30/31)**, with HTML/Clover reports.
- **Frontend:** **12 tests** passed; **81.44% line coverage**. JUnit, HTML, LCOV, and
  JSON reports were generated and checked. Report XML contained no failures/errors.
- **HTTP:** `/`, `/about`, `/up`, `/api/health`, `/api/ready`, and the Vite client
  returned 200, with the exact health/readiness JSON contracts.
- **Browser/HMR:** **3 development tests passed, 1 production-only case skipped**.
  Real Chrome verified rendering, navigation/refresh, same-origin readiness, Vue
  template HMR, and CSS HMR without a full navigation. Edits were restored in the
  verification checkout; application source in the working checkout was untouched.
- **Persistence:** inserted one dedicated cache probe in the confirmed-new verification
  database. Its committed value survived Laravel restart and ordinary PostgreSQL
  container recreation. The container ID changed while the image and named volume
  stayed the same. Only that exact probe key/value was deleted afterward. No reset,
  table drop, truncation, or volume deletion was used for this check.
- **Production browser:** **2 tests passed, 2 development-only cases skipped** after
  stopping only the verification Vite service. Production assets, root worker delivery,
  precaching, offline route reopening, and connectivity failure feedback passed.
- **Fail-fast behavior:** the declared frontend quality command returned nonzero for
  a controlled temporary type error. Its fixture was removed and the clean gate
  passed again. Static workflow review confirms explicit Bash failure handling and
  no `continue-on-error`/exit-status bypass; hosted failure behavior remains unobserved.
- **Final working checkout:** both mandated Docker quality gates passed with the new
  workflow and documentation. Dependency lockfiles and application code are unchanged.

The first browser attempt encountered Vite **504 Outdated Optimize Dep** because
verification explicitly ran `npm ci` underneath a running Vite process. Restarting
Vite in the same isolated project fixed it; rerun browser/HMR and production checks
passed. Setup documentation now explains that normal entrypoints install before
launching Vite, and manual reinstalls require a restart. No CORS, CSRF, authentication,
application, or framework change was used to resolve this tooling state.

The temporary project was `mealplaner-foundation-a23e1b4443`, rooted at
`/tmp/mealplaner-task08-ejmv4cqt/checkout`. It was stopped with ordinary Compose `down`.
Its five named volumes and private verification checkout were retained; no volume
removal was performed. The original development stack stayed running and its data,
credentials, key, and existing volumes were untouched. Browser verification used the
already installed host Chrome/Node with copied locked Playwright tooling; application
setup and all declared CI commands used Docker-only runtimes.

### BL-01 acceptance review

| Area                           | Local evidence                                                                                                                                           | Remaining boundary                                                                      |
| ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Architecture/repository        | Root Laravel and integrated Vue/TypeScript/Vite preserved; no extra application roots or framework initialization.                                       | Domain/auth features remain later MVP work by design.                                   |
| Backend routing/environment    | Existing CLI/test boot, health/readiness JSON, SPA/backend boundaries, and production assets passed.                                                     | No deployment claimed.                                                                  |
| Frontend foundation            | Strict typing, real stores/router, component tests, actual development/production browser checks and HMR passed.                                         | Real device installation/update prompts are not covered by these checks.                |
| PostgreSQL/isolation           | New database migration workflow, restricted-role full suite, transaction rollback, and safe committed-record persistence passed.                         | Original TASK-05 schema cleanup is still pending; no existing schema change authorized. |
| Reproducible local environment | Clean checkout, new dependency/data volumes, same Compose setup, HTTP/browser integration, restart/recreation durability.                                | Backups, host reboot, and volume-loss recovery are separate/unverified.                 |
| Quality/testing/build          | Lockfile installs, both quality gates, 34 backend / 12 frontend tests, coverage artifacts and production build passed.                                   | Native GitHub setup/caching/upload have not executed here.                              |
| GitHub Actions                 | Push/PR/manual triggers, supported runtimes/service, immutable action refs, caches, gates, report upload, and fail-fast configuration validated locally. | First passing hosted run for the published commit is required to finish TASK-08/BL-01.  |

No commit, push, repository-setting change, production deployment, or existing-data
cleanup was performed. [ci.md](ci.md) documents status, exact workflow behavior,
local reproduction, and troubleshooting; [DEVELOPMENT.md](DEVELOPMENT.md) is the
complete fresh local setup procedure.

## TASK-07 — automated testing infrastructure (2026-11-03)

The existing PHPUnit 12, Vitest 4.1.12, Vue Test Utils, guarded PostgreSQL provisioner,
and Docker services were reused. No application framework, model, migration, database
service, or authentication implementation was added. The only new npm development
package is the matching `@vitest/coverage-v8` 4.1.12 provider and its locked transitive
dependencies. The PHP development image now includes pinned Xdebug 3.5.3, off by
default; coverage enables it only in the PHPUnit child. Composer dependencies and
`composer.lock` are unchanged.

`composer test` / `test:postgres` now run **Unit, Feature, and PostgreSQL** suites in
one fresh generated database with a restricted login. `test:sqlite` preserves the
optional SQLite suite. `test:coverage` adds PHP HTML/Clover reporting; frontend
`test:coverage` adds V8 HTML/LCOV/summary reporting. Both configurations write JUnit
results for later GitHub Actions artifact collection. No workflow was introduced.

The shared Laravel test base validates the effective/actual database, matching role,
ownership, and restricted privileges before Laravel transaction traits. Ordinary
migrations run once for that generated database. Database-writing tests use native
`DatabaseTransactions`, with two cases reusing one key to prove teardown rollback.
A regression re-enters trait setup after changing to an unsafe synthetic connection
and verifies rejection before a database could be opened. Bootstrap regressions now
also reject unsupported drivers and missing PostgreSQL test passwords. No reset or
fresh/refresh/wipe command was run anywhere.

Baseline additions cover the health endpoint's JSON 405 for writes, continued liveness
after readiness failure, accessible theme-button state, and theme switching when
browser storage is blocked. Existing route/SPA/PWA-asset, component/router/store,
key-initialization, real PostgreSQL readiness, migration, and reconnect tests remain.

| Executed check                           | Result                                                                                                                                                                                                               |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP image build and backend activation   | Passed; Xdebug 3.5.3 loads with mode off for normal commands. PostgreSQL and its existing volume were not restarted/recreated.                                                                                       |
| `composer test`                          | **34 tests, 420 assertions**, full suite on generated PostgreSQL; temporary database/login removed.                                                                                                                  |
| `composer test:coverage`                 | **34 tests, 420 assertions**; HTML and Clover generated. **96.77% lines (30/31)** in `app` and `routes`.                                                                                                             |
| `composer test:sqlite -- --compact`      | **29 tests, 191 assertions**, memory-only compatibility suite.                                                                                                                                                       |
| `npm run test:coverage`                  | **12 tests** passed; JUnit, HTML, LCOV, and summary emitted. **81.44% lines**, 80.37% statements, 81.51% branches, 77.77% functions across application TypeScript/Vue.                                               |
| `composer quality` / `npm run quality`   | Passed: Pint, PHPStan/Larastan, ESLint, Prettier, and strict Vue TSC.                                                                                                                                                |
| `composer validate --strict`             | Passed; no Composer lockfile regeneration needed.                                                                                                                                                                    |
| `npm run build` / `npm run verify:build` | Passed; type-checked production build and artifact checks.                                                                                                                                                           |
| Actual HTTP/service checks               | `/`, `/about`, `/up`, `/api/health`, `/api/ready`, and Vite client returned 200; all four services healthy.                                                                                                          |
| Controlled failing backend test          | Nonzero status propagated; generated database/role counts returned to their original values. Only the temporary probe file was removed, then the real suite was rerun successfully to restore passing JUnit results. |

Both normal test commands also passed with `CI=true` and no TTY. Report XML/JSON
files were parsed and HTML/LCOV files verified to exist, be nonempty, and belong to
the mapped host user. Reports are under ignored `coverage/` and `test-results/`, not committed.
Frontend coverage intentionally includes the untested entry point and partially
covered production PWA composable; no exclusions or percentage gates hide those gaps.

[testing.md](testing.md) documents setup, exact commands, transaction/cleanup rules,
coverage scope, report paths, and the serial CI command sequence. README, development,
database, architecture, and AGENTS instructions now distinguish canonical PostgreSQL
runs from optional direct-Artisan/SQLite runs. Parallel PHPUnit workers are not configured.
The production service-worker/device browser checks were not rerun for this task.

No development or production schema/records, credentials, local environment file,
application key, or existing database volume was changed or removed. The outstanding
TASK-05 cleanup was not performed. No Git commit or push occurred.

## TASK-06 — quality and static analysis (2026-11-03)

Reused the installed Pint, PHPStan/Larastan, ESLint, Prettier, and Vue TSC versions
without installing dependencies or changing either lockfile. The integrated Laravel/Vue
architecture, Pint Laravel preset, strict TypeScript settings, test isolation, and
mandatory type checking during the production build remain intact.

Composer now provides `format`, `format:check`, and aggregate `quality`; existing
`lint` remains a compatible check alias and `analyse` retains level 8 analysis with
concise output. PHPStan coverage includes application code, both maintained bootstrap
files, configuration, database migrations/seeders, routes, and PHP helper scripts.
That expansion found two nullable administrative-PDO calls in test-resource cleanup;
an explicit connection guard fixes them without a suppression or baseline. Cleanup
still targets only resources successfully created by that test invocation.

npm now provides aggregate `quality` and explicit `lint:fix`; `lint` treats warnings
as failures. Existing formatting/type/build/test scripts remain compatible. Generated
coverage and PHPUnit cache output are excluded from ESLint/Prettier. No application
source exclusion or error suppression was added. README documents the command matrix,
Docker invocation, tool coverage, and check/write distinctions; AGENTS requires the
two quality gates and relevant tests/builds for future implementation tasks.

All commands below ran in the existing Docker services as `app` / `node`:

| Command                         | Result                                                                                                                                         |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer quality`              | Passed: Pint **33 PHP files** and expanded PHPStan/Larastan level 8 analysis, no errors.                                                       |
| `composer lint`                 | Passed; existing check alias remains functional.                                                                                               |
| `composer validate --strict`    | Passed; scripts-only manifest change needs no lockfile regeneration.                                                                           |
| `composer check-platform-reqs`  | Passed with the installed PHP runtime/extensions.                                                                                              |
| `npm run quality`               | Passed: zero-warning ESLint, non-mutating Prettier check, and strict Vue TSC.                                                                  |
| `composer test`                 | **25 tests, 101 assertions**, using guarded SQLite memory storage.                                                                             |
| `sh scripts/verify-postgres.sh` | **3 tests, 49 assertions**, using a generated restricted role/database; temporary resources removed. Docker executes the PHP runner and tests. |
| `npm run test:unit`             | **10 tests** passed.                                                                                                                           |
| `npm run build`                 | Passed; Vue TSC ran before Vite emitted the production/PWA assets.                                                                             |
| `npm run verify:build`          | Passed; entry, CSS, offline shell, manifests, worker, and icons validated.                                                                     |

Six controlled negative checks used temporary, unimported source files: PHP style,
PHP return-type analysis, a Vue lint warning, frontend formatting, frontend typing,
and build-blocking typing. Each expected diagnostic produced a nonzero exit without
modifying its fixture or tracked source. A separate lint check confirmed the warning
fixture had **zero errors and one warning** and still failed. All fixtures were
removed in cleanup; no probe code was executed as application logic and no testing
framework/dependency was added for these checks.

No development schema/records, real local environment, infrastructure, application
features, or authentication were modified. The TASK-05 cleanup request remains
pending and was not carried out as part of TASK-06. No services were restarted, no
volumes were removed, and no Git commit/push occurred. Git hooks/CI enforcement are
not introduced by this task; the documented commands are ready for later automation.

## TASK-05 — Docker development environment (2026-11-02)

Reused the four existing services and the original `mealplaner_postgres_data` volume.
No application, service, package, migration, database credential, or application key
was replaced. PHP/Node Dockerfiles and PostgreSQL/Nginx references now pin verified
official image tags plus immutable registry digests: PHP 8.5.12, Composer 2.10.3,
Node 24.22.2/npm 11.19.1, PostgreSQL 18.6, and Nginx 1.30.6. The PHP extensions,
FPM/Nginx integration, shared SELinux labels, dependency/cache volumes, and loopback
ports remain in use. Vite gains an init process; PostgreSQL has a 30-second stop window.

<a id="verification-incident--cleanup-awaiting-approval"></a>

### Verification incident and approved cleanup

**Resolved on 2026-11-04:** the user approved removal only while both tables were
empty. Exclusive locks and fresh structure/row-count checks confirmed that condition;
only `cache` and `cache_locks` were dropped without CASCADE in a transaction. Zero
user relations remain, matching the state before the incident. The paragraphs below
retain the original incident and approval hold; the dated follow-up above is current.

The first temporary lifecycle probe booted Laravel with development credentials and
attempted a migration on a separately named test connection. This did not isolate
all schema operations: it mistakenly created **two empty development tables**, `cache`
and `cache_locks`, with their primary-key indexes. Initial development inspection had
zero user relations. Subsequent inspection confirmed both new tables contain zero
rows and no migration repository was created in development. No existing record was
modified or deleted. The failed probe's generated test database/login were removed.

The probe was stopped and its unsafe temporary script removed. Approval was requested
to restore the original development schema by removing only those two empty tables,
after locking and rechecking them, without CASCADE or any reset command. **At that time cleanup was awaiting approval.** It was completed under the conditions
described above on 2026-11-04. Existing migration initialization still requires a
separate decision after inspecting the target.

The corrected probe provisioned a new random database and restricted login, then
booted a separate process through `tests/bootstrap.php` with forced testing/array
settings and only that login's credentials. It checked the actual database/user and
restricted permissions before ordinary migrations or writes. No connection-switching
inside a development-credential application was used for test writes. This requirement
is now explicit in `AGENTS.md`.

### Executed checks

| Check                                            | Result                                                                                                                                                                                                                                               |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docker compose config --quiet`                  | Passed without printing resolved credentials.                                                                                                                                                                                                        |
| PHP/Node image builds                            | Passed with the pinned base images and existing lockfiles.                                                                                                                                                                                           |
| `docker compose up -d --wait --wait-timeout 240` | Passed; all four services healthy, zero unexpected restarts.                                                                                                                                                                                         |
| Empty dependency/source-copy startup             | Passed in an isolated temporary checkout with a synthetic environment and no database service. Composer installed locked dependencies; Vite ran after `npm ci`. The temporary source/container were removed; no database volume was created/deleted. |
| Missing lockfiles                                | Both built entrypoints failed with nonzero status, without generating replacement lockfiles.                                                                                                                                                         |
| Key and ownership                                | Fresh empty-key initialization passed; second startup preserved environment bytes/mtime and reused dependencies. Dependency stamps and Vite hot file belonged to host UID 1000. Actual checkout `.env` size/mtime remained unchanged.                |
| Fedora/SELinux                                   | Host SELinux enforcing; shared `:z` mounts worked during startup, source watching, builds, and HMR.                                                                                                                                                  |
| Laravel CLI/routes                               | Laravel 13.39.0; all seven existing routes registered.                                                                                                                                                                                               |
| HTTP                                             | `/`, `/about`, unknown frontend route, `/up`, `/api/health`, `/api/ready`, and Vite `/@vite/client` returned 200; health/readiness returned their exact JSON contracts. Unknown API paths retained JSON 404 in browser tests.                        |
| Backend tests                                    | **25 tests, 101 assertions**, including missing-environment rejection without creating a partial `.env`.                                                                                                                                             |
| Restricted PostgreSQL tests                      | **3 tests, 49 assertions** after container recreation; temporary database/login removed.                                                                                                                                                             |
| Pint / Composer / PHPStan                        | Passed: Pint 33 files, strict Composer validation, platform requirements, configured PHPStan/Larastan analysis.                                                                                                                                      |
| Frontend tests/types/lint/format                 | **10 tests** passed; strict Vue TSC, ESLint, and Prettier passed.                                                                                                                                                                                    |
| Production build/artifacts                       | Passed; type-checked Vite build and `verify:build`, including PWA/static assets.                                                                                                                                                                     |
| Browser development suite                        | **3 passed, 1 production-only test skipped**. Installed Chrome verified Laravel/Vite integration, direct navigation/refresh, API readiness, and real Vue/CSS HMR without navigation. Source edits were restored.                                     |
| Lifecycle persistence                            | Corrected restricted-role probe's committed cache record survived Laravel restart and PostgreSQL recreation. Container ID changed; PostgreSQL image and original named volume stayed the same. Only the probe's own database/login were removed.     |
| Startup logs / image contents                    | No critical startup matches in current container logs. Runtime images contain no copied application/environment files.                                                                                                                               |

Fresh-start checks used temporary containers/source with the same built images and
existing package-cache volumes; they never connected to development PostgreSQL. Only
Docker executed PHP, Composer, npm, tests, and builds. Browser verification used
installed host Chrome with the locked Playwright tooling copied into a temporary
runner; the optional all-Docker browser-download recipe was not executed.

No database reset, development record deletion, volume removal, duplicate application,
CI workflow, production deployment, Git commit, or push occurred. Existing privileged
local database-role settings remain unchanged. Production offline/update/device PWA
acceptance, host reboot, backup restore, volume-loss recovery, and unrelated locked
development-tool advisories remain outside these verified claims. The development
schema discrepancy above is an unresolved limitation until cleanup is approved.

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
