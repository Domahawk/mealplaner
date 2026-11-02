# Laravel Cloud

This is a future deployment procedure. No deployment or paid resource creation is part of the starter setup.

The repository is a single Laravel application. Its public document root is `public/`, Vue source lives in `resources/js/`, and Vite builds into `public/build/`. Cloud needs neither Compose nor these development Dockerfiles. Node is needed only during the build.

## Application and runtime

1. Create a Laravel Cloud application and connect the independent application's Git repository and deployment branch. The application root is this repository root; do not select a frontend/backend subdirectory.
2. Select Cloud's PHP runtime and PHP 8.5 in environment General Settings. Cloud currently lists PHP 8.2–8.5 and defaults new environments to 8.5. This repository requires 8.5. Use a supported Node build version satisfying `package.json` (Node 24 is the local baseline); confirm the build log reports that version.
3. Configure the app cluster to serve Laravel through `public/index.php`. Keep Cloud's normal Laravel server configuration. Do not configure Vite, a Node production process, SSR, or a second frontend application.
4. Connect a managed Serverless Postgres database in the same region. Cloud injects database connection variables when attached; inspect those values and redeploy. Remove local values such as `DB_HOST=postgres`. Confirm `DB_CONNECTION=pgsql` and the managed database's required TLS settings, normally `DB_SSLMODE=require`.

These steps follow the official [runtimes](https://laravel.com/cloud/docs/runtimes), [application setup](https://laravel.com/cloud/docs/applications), and [managed PostgreSQL](https://laravel.com/cloud/docs/resources/databases/postgres) documentation.

## Environment and secrets

Set these per environment before building:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=<your HTTPS application URL>
APP_NAME="<your application name>"
APP_KEY=<a securely generated persistent Laravel application key>
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_SSLMODE=require
CACHE_STORE=database
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
VITE_APP_NAME="<your application name>"
VITE_PWA_SHORT_NAME="<short install name>"
```

Generate a new production key once with `php artisan key:generate --show` in a trusted environment; store it in Cloud's environment settings or secret management. Never copy the local development key, put it in Git, or regenerate it during deployment. The local key initialization script is only a Docker entrypoint step.

Cloud supplies `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`; do not override those with local defaults. Remove a stale `DB_URL` if one would override the managed settings. Never set secrets using `VITE_*`: those variables are public and embedded in JavaScript. Leave `ASSET_URL` unset to keep assets same-origin. Do not copy `.env` or `public/hot` into a deployment.

File sessions are sufficient for this unauthenticated API starter, which stores no session data. When adding authentication or multiple replicas that require shared sessions, add the appropriate session migration and shared driver. The provided cache migrations support `CACHE_STORE=database`. Persistent application files require managed object storage; Cloud's app filesystem is ephemeral.

See official [environment settings](https://laravel.com/cloud/docs/environments) and [secrets](https://laravel.com/cloud/docs/secrets).

## Build and deploy commands

Use these build commands from the repository root:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
npm run verify:build
php artisan optimize
```

Do not omit npm development dependencies during the build: Vite, TypeScript, and the PWA plugin are build tools. The build runs strict type checking before compilation. `APP_NAME` and public Vite name variables must be available at build time so the manifest and static offline shell match the online application.

Use this deploy command for reviewed migrations:

```bash
php artisan migrate --force
```

Only migrations need the connected database during deployment. Do not use `migrate:fresh`, reset databases, regenerate the application key, or execute Docker commands. Cloud's guide recommends optimization in the build stage and migrations in the deploy stage; see the official [Laravel deploy guide](https://laravel.com/cloud/docs/deploy-guides/laravel) and [deployments](https://laravel.com/cloud/docs/deployments).

Commit both generated lockfiles in your application repository before deployment. The build must install locked dependencies rather than resolve a new dependency graph.

## Verification after a future deployment

- Check `/up` and `/api/health` for application/API liveness. Check `/api/ready` for the actual managed database query; readiness returns only connectivity status and a safe 503 on failure.
- Open `/`, click the connection button, navigate to `/about`, and reload it directly. Laravel must return the entry template on client routes; unknown `/api/*` routes must remain JSON 404s.
- Inspect asset URLs: they must be relative to this application under `/build/`. No request should reference `localhost`, `frontend`, `backend`, port 5173, or a Docker network hostname.
- Check `/build/manifest.webmanifest`, all three icons, and `/sw.js`. The worker is served by an explicit Laravel route with JavaScript content type, no-store caching, and root scope. Blade references the build manifest only when the Vite hot file is absent.
- Over HTTPS, wait for worker installation and control, test offline reopening and client routing, then check the browser's install UI. Test installation on your target devices manually.
- Deploy an updated build while an older tab is open. Reload online, accept the application's update notice, and confirm the new frontend appears without clearing storage. API responses must not appear in Cache Storage.
- Confirm `APP_DEBUG=false`, production logging, and stable `APP_KEY`. Use `/up` for basic health checks; choose whether managed database availability should affect readiness.

Cloud account actions, GitHub template settings, HTTPS device installation, and a real deployment remain manual. This document does not authorize creating resources.
