# PostgreSQL persistence

## Initial TASK-04 inspection — 2026-11-02

Before any changes, the root application already used Laravel's native `pgsql`
connection, configured through standard `DB_*` environment variables. The existing
Compose PostgreSQL service runs **18.6**. Laravel **13.39.0** on PHP **8.5.12** connects
successfully, and `pdo_pgsql` is loaded. The separate `pgsql` PHP extension is not
loaded and is not required by Laravel's PDO connection.

The pre-existing development database has zero non-system relations and no extra
user schemas. `php artisan migrate:status` reports **Migration table not found**.
Only the existing cache/cache-lock migration and an empty seeder exist; there are
no models, factories, user/auth tables, or authentication database requirements yet.
The local connection role has development DDL permissions and superuser privileges;
this task will not change that existing role or its credentials.

PostgreSQL's data directory is `/var/lib/postgresql/18/docker`, within the existing
writable named-volume mount at `/var/lib/postgresql`. `fsync` and
`synchronous_commit` are both on. This was configuration evidence at TASK-04. TASK-05 separately verified container
recreation on the same volume; backup recovery remains untested.

The development database was not proven newly created/dedicated, so no pending
migrations will be run there. All existing data, role settings, migration files,
history, and actual local environment values must be preserved. Migration and
record/reconnection checks will use a newly created isolated test database instead.

## Requirements and connection configuration

This project targets the existing **PostgreSQL 18.x** runtime; TASK-04 verified
**18.6**. Laravel uses PDO, so PHP **`pdo_pgsql`** is required. The separate PHP
`pgsql` extension is optional. Existing Dockerfiles already provide the required PDO
extension; no packages or services were installed or reconfigured.

| Variable        | Purpose / example                                                                                                                            |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `DB_CONNECTION` | `pgsql` for persistent application data.                                                                                                     |
| `DB_HOST`       | `postgres` inside the existing Compose network; `127.0.0.1` for PHP connecting to a host-installed PostgreSQL server.                        |
| `DB_PORT`       | Normally `5432`.                                                                                                                             |
| `DB_DATABASE`   | Example placeholder `mealplaner`; preserve existing local values.                                                                            |
| `DB_USERNAME`   | Example placeholder `mealplaner`; preserve existing local roles.                                                                             |
| `DB_PASSWORD`   | Blank in the example; supply a private local password before starting Compose.                                                               |
| `DB_SSLMODE`    | Existing local default `prefer`; configure appropriate TLS for a future remote/production service.                                           |
| `DB_URL`        | Optional Laravel connection URL; leave unset unless intentionally used. It can override individual settings and is always disabled in tests. |

`.env.example` now contains no password value. The default database/role fallbacks in
`config/database.php` are `mealplaner`, with no hardcoded password. Actual `.env`
values, application keys, and credentials were preserved without opening the file.
Changing examples or fallbacks does not rename existing databases, users, or volumes.

The existing database container does **not** publish port 5432 to the host. A host PHP
process cannot reach that private service merely by setting `DB_HOST=127.0.0.1`.
Use the existing PHP container for this setup; host PostgreSQL and any future host
port mapping are separate runtime choices. TASK-05 preserves this private Compose network and Laravel's environment-based
connection. The Docker-only setup and lifecycle results are in
[DEVELOPMENT.md](DEVELOPMENT.md) and [VERIFICATION.md](VERIFICATION.md).

## Development database and migrations

Inspect the configured target privately and begin with the read-only status command:

```bash
docker compose exec -T --user app backend php artisan migrate:status
```

Development result: **Migration table not found**. TASK-04 found no user relations.
The initial TASK-05 lifecycle probe mistakenly created two empty cache tables; their
targeted removal awaits approval. See the
[incident record](VERIFICATION.md#verification-incident--cleanup-awaiting-approval)
before development schema work. This is reported as uninitialized migration history, not as a failed
connection. The existing cache migration uses Laravel Schema Builder and passed on
both SQLite and PostgreSQL; its bytes were not modified.

Only if the target has been confirmed **new, empty, dedicated to Mealplaner, and safe
to initialize**, apply its reviewed pending migrations:

```bash
docker compose exec -T --user app backend php artisan migrate --force
```

For a pre-existing or uncertain database, stop at status inspection and obtain the
required scope/approval for schema changes. Do not infer safety from `APP_ENV=local`
or an empty public schema alone. TASK-04 deliberately left development unmodified.
Never use `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `db:wipe`, truncation,
or volume removal against development or production data. No migrations run at startup.

## Default isolated tests

```bash
docker compose exec -T --user app backend php artisan test
```

The existing default `phpunit.xml` still forces testing mode, SQLite `:memory:`, an
empty `DB_URL`, array session/cache/mail drivers, and synchronous queues. New tests
run ordinary migrations and insert/retrieve a cache record only in the memory database.
No production model, test-only production table, reset trait, or domain migration is
needed. Authentication remains absent, so there are no authentication database tests
or tables to preserve beyond the unchanged starter files.

Both test configurations now load `tests/bootstrap.php`. Before Laravel boots, it
rejects cached/custom cached configuration and checks the **effective** environment
using Laravel's server/environment precedence. It permits only SQLite memory storage
or the exact generated PostgreSQL test identity with an empty database URL. Tests
cover non-testing mode, inherited local settings despite `putenv`, file-backed SQLite,
URL overrides, ordinary PostgreSQL names, wrong roles, and custom config-cache paths.
`APP_ENV=testing` alone is insufficient.

## PostgreSQL integration suite

Reuse the already-running development infrastructure:

```bash
sh scripts/verify-postgres.sh
```

With an existing compatible host PHP/dependency installation, the same runner is
`php scripts/verify-postgres.php` from the root. Host PHP 8.4.1 is incompatible here;
use the PHP 8.5 container. Do not invoke `phpunit.postgres.xml` manually with development
credentials: its guarded bootstrap rejects ordinary database identities.

The runner performs these scoped steps:

1. Require uncached local `pgsql` configuration and use the existing connection only
   for administrative provisioning. The role needs permission to create a test role
   and database; missing permissions fail safely rather than changing existing roles.
2. Generate a unique `mealplaner_test_<16 hex characters>` database and matching
   login, plus a random password held only in memory. The test role is not a superuser
   and cannot create databases or roles. It owns only the newly created database;
   public access to that new database is revoked.
3. Launch the existing PHPUnit with `phpunit.postgres.xml`, explicit testing/array
   settings, and **only the generated database credentials** for the database connection.
   A development application key is not inherited. No `.env.testing` file or custom
   `TEST_DB_*` configuration is needed.
4. Verify the effective database/user, ownership, restricted privileges, and driver
   before migrations. Apply the existing migrations only to the fresh test database.
   Test readiness, migration history/cache tables, and a committed cache record.
5. Purge/reconnect Laravel's PDO connection, verify a new PostgreSQL backend session,
   and retrieve the committed record. Delete only that explicitly created probe row.
6. In `finally`, remove only the database and role successfully created by this run.
   Never force-disconnect sessions or clean up pre-existing databases/roles. Cleanup
   failures are reported, not hidden. Passwords and raw connection exceptions are not
   printed or written to application logs by this runner.

The old `write`/`read` modes, `TEST_DATABASE` variable, and PostgreSQL container
recreation have been removed. Parallel runs receive distinct identities. PostgreSQL
integration tests need the local server, but never any production service; default
SQLite tests need no database server.

## Results, persistence bounds, and TASK-05

TASK-04 verification passed:

- **24 default tests, 98 assertions**; **3 PostgreSQL tests, 49 assertions**.
- Existing migrations applied successfully in a fresh PostgreSQL database.
- A committed record survived disconnect/reconnect, with a different backend session.
- All generated test resources were removed; development still had zero relations
  and no migration repository afterward.
- Pint checked 33 PHP files; configured PHPStan/Larastan passed. The 10 frontend unit
  tests passed, and HTTP `/`, `/api/health`, and `/api/ready` returned 200.

The configured named volume and durability settings were inspected, and ordinary
connection/application-process lifetimes do not store records in memory. The tested
claim is **commit/reconnect persistence on the existing PostgreSQL runtime**. No
PostgreSQL restart, container recreation, host reboot, backup restore, or volume-loss
recovery was performed. TASK-05 separately verified a committed cache record in a fresh restricted-role test
database across Laravel restart and ordinary PostgreSQL container recreation, retaining
the same original volume and image. See [VERIFICATION.md](VERIFICATION.md) for that
verification and its initial probe incident/cleanup status. Host reboot, volume loss,
and backup recovery remain untested.

The existing development role is privileged and was not altered. Plan least-privilege
application/provisioning roles before deployment. Initial development migrations,
domain schema, backup/restore acceptance, and production settings remain separate,
authorized work. No existing database data was deleted or reset.
