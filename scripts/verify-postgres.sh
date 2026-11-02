#!/bin/sh
set -eu

# Creates a unique test database. Never migrates or resets the development database.
test_database="starter_test_$(date +%s)_$$"
docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" "$1"' sh "$test_database"

cleanup() {
    docker compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" "$1"' sh "$test_database"
}
trap cleanup EXIT

docker compose exec -T -e TEST_DATABASE="$test_database" backend php scripts/verify-postgres.php write
docker compose up -d --force-recreate --no-deps --wait postgres
docker compose exec -T -e TEST_DATABASE="$test_database" backend php scripts/verify-postgres.php read
echo "PostgreSQL retained the isolated test row after container recreation."
