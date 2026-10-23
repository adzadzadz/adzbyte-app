#!/usr/bin/env bash

set -euo pipefail

composer_binary="${COMPOSER_BINARY:-composer2}"
php_binary="${PHP_BINARY:-php}"

# Hostinger pulls can reset this mode; never leave production credentials readable.
[[ -f .env && ! -L .env ]] || exit 1
chmod 600 .env

"$composer_binary" install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

"$php_binary" artisan migrate --force
"$php_binary" artisan optimize
"$php_binary" artisan queue:restart
