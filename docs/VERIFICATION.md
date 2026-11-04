# Verification record

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
