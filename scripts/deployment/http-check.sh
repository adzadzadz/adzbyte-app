#!/usr/bin/env bash
set -euo pipefail
[[ $# == 1 && "$1" =~ ^https://[a-zA-Z0-9.-]+$ ]] || exit 64
base_url=$1
for path in /up /login /admin/login /password-reset/request /admin/password-reset/request; do
    code=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 10 --max-time 30 --retry 2 --retry-delay 2 --retry-all-errors "$base_url$path")
    [[ "$code" == 200 ]] || { echo "Health check failed: $path returned $code." >&2; exit 1; }
done
redirect=$(curl --silent --show-error --output /dev/null --write-out '%{http_code} %{redirect_url}' \
    --connect-timeout 10 --max-time 30 "$base_url/")
[[ "$redirect" == "302 $base_url/login" ]] || { echo 'Customer authentication redirect failed.' >&2; exit 1; }
for path in /.env /storage/logs/laravel.log; do
    code=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 10 --max-time 30 "$base_url$path")
    [[ "$code" == 403 || "$code" == 404 ]] || { echo "Protected path check failed: $path returned $code." >&2; exit 1; }
done
asset_checks=0
for asset in public/build/assets/*.css public/build/assets/*.js; do
    [[ -f "$asset" ]] || continue
    code=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 10 --max-time 30 "$base_url/${asset#public/}")
    [[ "$code" == 200 ]] || { echo "Built asset check failed: ${asset#public/} returned $code." >&2; exit 1; }
    asset_checks=$((asset_checks + 1))
done
(( asset_checks > 0 )) || { echo 'No verified production assets found for HTTP checks.' >&2; exit 1; }
echo 'HTTPS health, authentication pages, redirects, protected paths and built assets pass.'
