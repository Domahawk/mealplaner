# Continuous integration and foundation verification

## Workflow and hosted status

[Project Foundation CI](../.github/workflows/ci.yml) runs on every push, pull request,
and manual `workflow_dispatch`. The single check is named **Verify foundation**.
It does not deploy, publish a release, or modify repository settings. All tool commands
reuse the scripts documented in [testing.md](testing.md) and [README.md](../README.md).

**Hosted status:** workflow implementation and local validation are recorded in
[VERIFICATION.md](VERIFICATION.md). A passing GitHub-hosted run has not yet been
verified. This checkout has no GitHub origin or authenticated GitHub integration;
there is no verified repository/run URL to link or use for a status badge. Do not
interpret local command results as GitHub Actions execution or mark BL-01 complete
until the uploaded workflow passes for the actual commit on GitHub.

Publishing requires a GitHub repository and explicit commit/push authorization under
`AGENTS.md`. Once published, inspect **Actions → Project Foundation CI** for the exact
commit, required check result, and report artifact. Configure branch protection to
require **Verify foundation** separately if wanted; this task does not change settings.

## What the job runs

One Ubuntu 24.04 job uses PHP **8.5**, Composer **2.10.3**, Node **24**, and an
independent PostgreSQL **18.6** service. These PHP/Node majors satisfy the root
manifests and match the local Docker runtime families. Hosted patch resolution and
setup-action execution require the first real GitHub run; local container versions
are recorded separately.

The job performs these serial steps:

1. Check out source and prepare PHP/extensions/Xdebug and Node.
2. Restore npm's download cache and Composer's package-download cache, keyed by the
   existing lockfiles. Dependency directories, environment/auth files, configuration
   caches, database storage, and credentials are not cached.
3. Run strict Composer validation, `composer install` from `composer.lock`, platform
   checks, and `npm ci` from `package-lock.json`. No dependency updates or platform
   requirement bypasses are used.
4. Run `composer quality` (Pint and PHPStan/Larastan) and `npm run quality` (ESLint,
   Prettier checks, strict Vue TSC).
5. Run `npm run build` and `npm run verify:build` before the backend asset smoke test.
6. Run `composer test:coverage` and `npm run test:coverage`, which include all required
   backend/frontend tests and emit coverage/JUnit reports.
7. Upload `test-results/` and `coverage/` as **foundation-reports**, including on
   failure when reports exist, with seven-day retention.

Actions are pinned to verified release commit SHAs. The workflow has read-only
repository contents permissions, does not persist checkout credentials, and does not
use `pull_request_target` or repository/deployment secrets. A 15-minute timeout bounds
the job, and newer runs cancel older runs for the same branch/pull request.

No required step uses `continue-on-error`, ignored exit status, rule suppression, or
an ignore baseline. Normal step failure stops subsequent checks and fails the job;
`always()` applies only to report upload and cannot turn an earlier failure into success.
Tests also propagate child/cleanup failures and PHP warnings/risky-test failures.

## Database and environment separation

GitHub creates a new PostgreSQL service per job, with no development volume. Its
`mealplaner_ci` bootstrap database/login uses a public synthetic credential derived
from that run ID/attempt; it is valid only for this disposable service. It is not a
repository secret or a credential to reuse in any persistent environment. Authentication
is enabled. The service publishes an assigned port to the runner, and the workflow
sets `DB_PORT` from `job.services.postgres.ports['5432']` after readiness checks.

No local `.env` is copied into CI. Job environment settings supply the bootstrap
connection and the same synthetic key used by PHPUnit fixtures. `APP_ENV=local` is
intentional for the existing guarded provisioner; the test child explicitly forces
`APP_ENV=testing`, array drivers, an empty database URL, its synthetic key, and a fresh
restricted database/login. Only that generated database receives test migrations/data.
The service bootstrap credentials are never used as test credentials. See
[testing.md](testing.md#database-isolation-and-laravel-utilities) for the safeguards.

Local development continues to use its existing private `.env` and named database
volume. Local secrets are neither workflow inputs nor cached/uploaded artifacts.
Do not cache Laravel configuration before testing or change the provisioner to use
production settings to get a green run. This is CI only, with no deployment credentials.

## Reproduce the checks locally

Follow the complete fresh-checkout procedure in
[DEVELOPMENT.md](DEVELOPMENT.md#docker-setup-from-a-fresh-clone), preserving existing
configuration and data. Entrypoints install the locked dependencies automatically.
Docker Engine/Compose are the only application runtime prerequisites on the host.
Then run:

```bash
docker compose exec -T --user app backend composer validate --strict
docker compose exec -T --user app backend composer check-platform-reqs
docker compose exec -T --user app backend composer quality
docker compose exec -T --user node frontend npm run quality
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
docker compose exec -T --user app backend composer test:coverage
docker compose exec -T --user node frontend npm run test:coverage
```

CI runs these root scripts on its own configured runner; local commands use the mapped
users in the existing Docker services. No second PHP/Vite application is introduced.
The fresh-clone verification uses a separate temporary Compose project with new volumes
and synthetic configuration, never the active development database. Its verification
record distinguishes actual local execution from hosted setup/cache/artifact behavior.

## Troubleshooting

- **No workflow run:** confirm `.github/workflows/ci.yml` was committed/pushed to the
  intended GitHub repository, Actions is enabled, and the event is push/PR/manual.
  A file present only in a local checkout cannot start GitHub Actions. Check the exact
  commit, not just another branch's green badge.
- **PostgreSQL connection/provisioning failure:** inspect service health and the assigned
  job port, plus the synthetic bootstrap identity. Keep `APP_ENV=local` in the parent;
  the runner configures the testing child. Never substitute development credentials,
  reuse development data, or reset a database. Missing provisioning permissions fail.
- **PHP/Composer/platform failure:** confirm PHP 8.5 and required PDO/XML/etc. extensions
  are installed by setup-php, and Xdebug exists for coverage. Inspect dependency errors;
  do not use `--ignore-platform-reqs`, remove the lockfile, or suppress checks.
- **npm install/build failure:** use Node 24 and `npm ci`. Keep Vitest and its V8 provider
  aligned. Review the actual failing command rather than forcing unrelated upgrades.
- **Quality/type/test failure:** use the same script locally, fix the code, add a
  regression when appropriate, and rerun it. Formatter checks do not modify files.
- **Missing production-asset test/report:** build first. The smoke test otherwise skips;
  this workflow always builds before backend tests. Reports may be absent if installation
  or quality failed earlier; artifact warnings do not mask the original failed step.
- **Local blank page / Vite 504 “Outdated Optimize Dep”:** an explicit `npm ci` replaces
  dependency/optimizer files beneath a running Vite process. Restart `frontend`, wait
  for readiness, and reload. Normal fresh startup installs before launching Vite.
  Keep browser host/port and `APP_URL` consistent; see the development guide.

## Foundation acceptance boundaries

[VERIFICATION.md](VERIFICATION.md) records the local foundation checks, workflow
validator result, clean checkout setup, real HTTP/browser/HMR results, safe persistence
probe, and unresolved items. TASK-08 reports SELinux disabled on the verification
host, so enforcing-mode operation was not reverified; shared `:z` labels remain
configured and no host security policy was changed. Named-volume persistence across ordinary recreation is
not a backup/restore or volume-loss guarantee. Never use database resets or volume
removal to validate durability.

A first passing hosted workflow remains required for TASK-08's Definition of Done.
The pending TASK-05 cleanup of accidentally created development tables also remains
separate; validation here does not authorize that schema change. Authentication and
Mealplaner domain features are later MVP work, not BL-01 foundation implementations.

Primary references: [GitHub PostgreSQL services](https://docs.github.com/en/actions/tutorials/use-containerized-services/create-postgresql-service-containers),
[setup-php](https://github.com/shivammathur/setup-php),
[setup-node](https://github.com/actions/setup-node), and
[actionlint](https://github.com/rhysd/actionlint).
