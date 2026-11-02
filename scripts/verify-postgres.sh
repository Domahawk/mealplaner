#!/bin/sh
set -eu

# Reuse the running service. Never migrate/reset development data or restart PostgreSQL.
docker compose exec -T --user app backend php scripts/verify-postgres.php
