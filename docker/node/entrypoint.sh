#!/bin/sh
set -eu

cd /var/www/html
mkdir -p node_modules /home/node/.npm
chown node:node node_modules /home/node/.npm

if [ "$1" = "npm" ] && [ "${2:-}" = "run" ] && [ "${3:-}" = "dev" ]; then
    if [ ! -f package-lock.json ]; then
        echo 'package-lock.json is required. Restore the tracked lockfile before starting.' >&2
        exit 1
    fi
    if [ -f node_modules/.starter-dependencies ]; then
        chown node:node node_modules/.starter-dependencies
    fi
    fingerprint=$(cat package.json package-lock.json | sha256sum | cut -d ' ' -f 1)
    if [ ! -f node_modules/.starter-dependencies ] || [ "$(cat node_modules/.starter-dependencies)" != "$fingerprint" ]; then
        gosu node npm ci --no-audit --no-fund
        printf '%s' "$fingerprint" | gosu node tee node_modules/.starter-dependencies > /dev/null
    fi
fi

exec gosu node "$@"
