#!/bin/sh
set -eu

cd /var/www/html
mkdir -p vendor /tmp/composer-cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown app:app vendor /tmp/composer-cache
# Only Laravel's writable directories need adjusted ownership.
chown -R app:app storage bootstrap/cache

if [ "$1" = "php-fpm" ]; then
    if [ ! -f composer.lock ]; then
        echo 'composer.lock is required. Restore the tracked lockfile before starting.' >&2
        exit 1
    fi
    if [ -f vendor/.starter-dependencies ]; then
        chown app:app vendor/.starter-dependencies
    fi
    fingerprint=$(cat composer.json composer.lock | sha256sum | cut -d ' ' -f 1)
    if [ ! -f vendor/autoload.php ] || [ ! -f vendor/.starter-dependencies ] || [ "$(cat vendor/.starter-dependencies)" != "$fingerprint" ]; then
        gosu app composer install --no-interaction --prefer-dist
        printf '%s' "$fingerprint" | gosu app tee vendor/.starter-dependencies > /dev/null
    fi
    gosu app php scripts/ensure-app-key.php
    exec "$@"
fi

exec gosu app "$@"
