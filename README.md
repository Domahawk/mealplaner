# Laravel + Vue 3 PWA Starter

A single Laravel application serving a Vue 3 + TypeScript SPA and a same-origin REST API. PostgreSQL, Vue Router, Pinia, Vite HMR, a production offline shell, and quality tools are included. There is no authentication or domain logic.

**Verification is incomplete:** this sandbox blocked Docker execution and complete dependency installation. Both lockfiles still need generation; the first successful development startup resolves them. See the [verification record](docs/VERIFICATION.md) before treating this snapshot as a tested template.

## Requirements

Docker Engine with Compose v2 or later, Git, and free local ports 8000 and 5173. No host PHP, Composer, Node, npm, certificates, or TLS configuration are needed. On Fedora, start Docker and ensure your user can access its socket through your normal Docker installation.

## Start

```bash
git clone <repository-url> my-project
cd my-project
cp .env.example .env
docker compose up -d --build
docker compose ps
docker compose logs -f
```

The first startup downloads dependencies into named volumes and generates an application key only when the key is empty. Allow a few minutes and wait for all four services to become healthy. Later startups reuse dependencies unless a dependency file changed. Startup never runs migrations.

| URL                              | Purpose                              |
| -------------------------------- | ------------------------------------ |
| http://localhost:8000            | Open this: Laravel hosts the Vue SPA |
| http://localhost:5173            | Vite assets and HMR only             |
| http://localhost:8000/api/health | API and actual database connectivity |
| http://localhost:8000/up         | Laravel liveness                     |

Click **Check server connection** to request the health endpoint. The interface makes no automatic API connectivity checks.

## Common commands

```bash
docker compose down                 # Keeps database and dependency volumes
docker compose up -d
docker compose logs -f backend frontend
docker compose exec backend php artisan route:list
docker compose exec backend php artisan migrate
docker compose exec --user app backend composer install
docker compose exec --user node frontend npm ci
```

Do not use `docker compose down -v` unless you intend to delete the local database and dependency volumes.

## Quality checks

```bash
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
docker compose exec backend ./vendor/bin/phpstan analyse
docker compose exec --user node frontend npm run test
docker compose exec --user node frontend npm run lint
docker compose exec --user node frontend npm run format:check
docker compose exec --user node frontend npm run type-check
docker compose exec --user node frontend npm run build
docker compose exec --user node frontend npm run verify:build
sh scripts/verify-postgres.sh
```

HTTP tests force an in-memory SQLite connection. The PostgreSQL integration script creates its own temporary database and removes only that database; it also recreates the PostgreSQL container to test its persistent volume. It briefly interrupts database connections.

## Layout

```text
app/Http/Controllers/       Health API controller
bootstrap/                 Laravel application bootstrap
config/                    Laravel configuration
database/migrations/       Framework cache tables; add your migrations here
docker/{php,node,nginx}/    Local development images and configuration
resources/js/              Vue SPA: views, components, router, stores, composables, lib
resources/css/             Application styles
resources/views/app.blade.php  Only HTML entry template
routes/{api,web}.php        API, worker, and reserved SPA routing
tests/{Feature,Unit}/       PHPUnit tests
tests/{frontend,e2e}/       Vitest and Playwright tests
scripts/                   Safe startup and verification helpers
docs/                      New-project, development, deployment, verification guides
```

## Documentation

- [Create a new application](docs/NEW_PROJECT.md)
- [Development, backups, HMR, and production PWA testing](docs/DEVELOPMENT.md)
- [Laravel Cloud deployment](docs/LARAVEL_CLOUD.md)
- [Verification record and remaining checks](docs/VERIFICATION.md)

Production is one Laravel host serving compiled assets, the SPA, and the API. Node is a build tool; it is not a production server. No cloud resources have been created.
