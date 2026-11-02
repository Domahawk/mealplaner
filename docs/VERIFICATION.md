# Verification record

Status on 2026-11-02: source implementation is present, but the full acceptance criteria are **not complete**. This execution sandbox denies Docker socket access, outbound package-registry DNS, and local TCP listeners. No container runtime, PostgreSQL, real HTTP delivery, HMR, or browser offline behavior has been verified.

## Checks actually completed

| Check                          | Result                                                                                                                                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Compose configuration          | `docker compose config --quiet` passes with the example environment                                                                                                                        |
| PHP source syntax              | All project PHP source files pass `php -l`                                                                                                                                                 |
| Composer schema                | `composer validate --no-check-lock` passes                                                                                                                                                 |
| PHP formatting                 | Cached Laravel Pint 1.24 passes; 29 files                                                                                                                                                  |
| Frontend lint                  | ESLint 9.31 passes, including the committed test source                                                                                                                                    |
| Frontend formatting            | Prettier 3.6.2 passes                                                                                                                                                                      |
| Application type checking      | Vue TS compiler 3.0.3 / TypeScript 5.8.3 passes for application source, Vite configuration, Playwright configuration, and browser-test source                                              |
| Supplementary component checks | 8 tests pass against the actual Vue app in JSDOM using native Vue mounting                                                                                                                 |
| Application-key initialization | Actual script passes empty-key, absent-key, existing quoted-key, and repeat-start checks in isolated temporary files                                                                       |
| Production compilation         | Vite 7.2.2 compiles 44 modules and emits Laravel's asset manifest, hashed JavaScript/CSS, and static offline shell                                                                         |
| PWA generation                 | vite-plugin-pwa 2.0.0 generates the manifest and root-served worker with 7 precache entries                                                                                                |
| Build artifact inspection      | `npm run verify:build` passes: entry assets exist, worker precache URLs are absolute and resolve to files, PNG dimensions match the manifest, and shell URLs have no development hostnames |

The supplementary component checks covered mounting/welcome content with no automatic API request, Vue Router navigation, explicit theme switching and persistence, restored preferences, API success, safe database failure, network failure, and malformed API data. They used a temporary native Vue mount harness because Vue Test Utils was unavailable. They do **not** constitute a passing run of the committed Vue Test Utils suite.

Dependency-cache validation used temporary files outside the repository and a temporary node_modules link. The temporary harness, alternate TypeScript configuration, and node_modules link were removed afterward. Public build artifacts remain in `public/build/` and are Git-ignored. No host packages were installed.

## Blocked checks and missing artifacts

- `docker compose up -d --build` fails with permission denied for `/var/run/docker.sock`. No project containers could be started.
- Composer installation fails resolving `repo.packagist.org`. The available cache has Laravel 12 and no Larastan; it cannot produce the required Laravel 13 installation. **composer.lock and vendor are absent.**
- The complete npm graph cannot be installed offline: Vue Test Utils and Chai declaration dependencies are unavailable in the package cache. **package-lock.json is absent.** A reduced temporary install was sufficient for compilation/linting and supplementary checks.
- The committed `npm run type-check`, `npm run test`, and `npm run build` workflow remains unverified with its complete dependencies. Application-only strict checking and direct Vite production compilation passed separately.
- `php artisan test` and PHPStan cannot run without the missing Composer installation. Pint was run from its real cached distribution, rather than vendor/bin.
- A temporary HTTP artifact server fails to bind with `listen EPERM`. Production worker activation, offline reopening, update acceptance, real Laravel asset delivery, and HMR remain unverified.
- PostgreSQL connectivity, migrations, and persistence require the running Compose environment. The isolated verification script is implemented but has not run.

The template is not ready to label fully tested or reproducible until **both lockfiles are generated, retained, and checked**. They have not been fabricated or replaced with lockfiles for a different dependency graph.

## Completing verification in an unrestricted environment

The normal startup command is still:

```bash
cp .env.example .env  # Only if .env does not already exist
docker compose up -d --build
```

For this unbootstrapped snapshot, the development entrypoints resolve missing lockfiles on the first successful startup. Later starts install from the lockfiles and reuse matching dependency volumes. Review and retain both generated lockfiles before using this repository as a template or deploying it.

Then run the README quality commands, `sh scripts/verify-postgres.sh`, and the development/production Playwright procedures in [DEVELOPMENT.md](DEVELOPMENT.md). Build before rerunning PHP tests to exercise the production-asset test instead of its explicit missing-build skip. Record actual outcomes and fix any newly exposed failures.

Manual checks still required:

1. Enable GitHub's template setting and create a fresh repository to check history isolation.
2. Test Fedora bind-mount permissions and SELinux behavior with Docker running.
3. Check real browser HMR, SPA reloads, service-worker installation, offline reopening, and the prompted update after a second build.
4. Test standalone PWA installation on target devices over HTTPS.
5. When separately authorized in the future, follow [LARAVEL_CLOUD.md](LARAVEL_CLOUD.md) to verify an actual Cloud deployment and managed PostgreSQL.

No cloud deployment, paid resource creation, Git commits/pushes, database resets, host firewall changes, or unrelated-project modifications were performed.
