# Start an independent project

1. On GitHub, mark this repository as a **Template repository** under repository settings. Select **Use this template → Create a new repository**. This creates an independent repository with a new history. Clone that new repository into your own project directory. GitHub setup is a manual step.
2. Alternatively, download the source archive and extract it into a new directory. Run `git init` there and add your own remote. A normal clone carries this repository's history; use GitHub's template action or an archive when you want fresh history. Do not reuse a copied `.git` directory. This starter's setup scripts never initialize, commit, or push Git.
3. Copy `.env.example` to `.env`. Set `APP_NAME="Your Application"` and update the example defaults that future developers should receive. This controls Laravel application metadata; Mealplaner's visible header and route titles are product copy in the existing Vue shell/router and must be renamed explicitly when reusing it for a different project.
4. Set `VITE_APP_NAME="${APP_NAME}"` and `VITE_PWA_SHORT_NAME="Your App"`. These are public build-time values for the offline shell and manifest. Replace the PNG icons in `public/icons/`; `source.svg` is the editable source. Keep 192×192, 512×512, and a full-background maskable 512×512 icon. Preserve the central maskable safe area.
5. Rename the metadata in `composer.json` and `package.json`. After changing package metadata, refresh both lockfiles using Composer and npm inside the containers. Do not copy a starter `.env`, application key, build directory, database volume, or other secrets into a new application.
6. Choose local database values in `.env`. Leave `DB_HOST=postgres` for Compose. Docker scopes named volumes to the directory/project name; use a different project directory or `COMPOSE_PROJECT_NAME` for an independent database. Existing volumes retain their original database/user/password; changing environment variables does not reinitialize them.
7. Set `LOCAL_UID` and `LOCAL_GID` to `id -u` and `id -g` if your host user is not 1000:1000. Then run:

    ```bash
    docker compose up -d --build
    docker compose ps
    ```

8. Open http://localhost:8000. Check navigation and theme switching, then click **Check server connection**. Visit http://localhost:8000/api/health for API liveness or http://localhost:8000/api/ready for database connectivity. Edit `resources/js/views/WelcomeView.vue` to check HMR. Run the quality checks in the README and the PostgreSQL integration probe.
9. Add your own database migrations under `database/migrations`, controllers under `app/Http/Controllers`, and API routes under `routes/api.php`. Run `docker compose exec backend php artisan migrate` when ready. Only framework cache tables are provided.
10. Add Vue views under `resources/js/views` and routes in `resources/js/router/index.ts`. Reuse `resources/js/lib/api.ts` for ordinary same-origin requests. Register explicit Laravel web routes before the SPA route; reserve their namespace in both the Laravel route expression and Workbox navigation denylist.
11. Replace Mealplaner's welcome/about copy and route titles when adapting this template to a different application. Keep API calls explicit until your product requires other behavior.

Use [DEVELOPMENT.md](DEVELOPMENT.md) for daily commands and [LARAVEL_CLOUD.md](LARAVEL_CLOUD.md) when you are ready to deploy.
