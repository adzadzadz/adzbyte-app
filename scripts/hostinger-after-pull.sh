#!/usr/bin/env bash

set -euo pipefail

composer_binary="${COMPOSER_BINARY:-composer2}"
php_binary="${PHP_BINARY:-php}"

# Hostinger pulls can reset this mode; never leave production credentials readable.
[[ -f .env && ! -L .env ]] || exit 1
chmod 600 .env

umask 022
"$composer_binary" install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Compiled configuration contains the environment's credentials and app key.
umask 077
if [[ -f bootstrap/cache/config.php ]]; then chmod 600 bootstrap/cache/config.php; fi
"$php_binary" artisan migrate --force
"$php_binary" artisan optimize
if [[ -f bootstrap/cache/config.php ]]; then chmod 600 bootstrap/cache/config.php; fi
"$php_binary" artisan queue:restart
