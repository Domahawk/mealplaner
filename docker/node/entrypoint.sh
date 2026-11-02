#!/bin/sh
set -eu

cd /var/www/html
mkdir -p node_modules /home/node/.npm
chown node:node node_modules /home/node/.npm

if [ "$1" = "npm" ] && [ "${2:-}" = "run" ] && [ "${3:-}" = "dev" ]; then
    # Recovery for an unbootstrapped source snapshot; normal template checkouts retain the lockfile.
    if [ ! -f package-lock.json ]; then
        gosu node npm install --package-lock-only --ignore-scripts --no-audit --no-fund
    fi
    fingerprint=$(cat package.json package-lock.json | sha256sum | cut -d ' ' -f 1)
    if [ ! -f node_modules/.starter-dependencies ] || [ "$(cat node_modules/.starter-dependencies)" != "$fingerprint" ]; then
        gosu node npm ci --no-audit --no-fund
        printf '%s' "$fingerprint" > node_modules/.starter-dependencies
    fi
fi

exec gosu node "$@"
