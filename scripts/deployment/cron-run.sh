#!/usr/bin/env bash
# Stable server-installed wrapper keeps Hostinger's command field below 255 chars.
set -euo pipefail
app_root='/home/u463458285/domains/app.adzbyte.com/public_html'
[[ $# == 1 ]] || exit 64
case "$1" in
    scheduler) arguments=(schedule:run) ;;
    worker) arguments=(queue:work database --stop-when-empty --tries=3 --backoff=10 --timeout=60 --max-time=50) ;;
    *) echo 'Expected scheduler or worker.' >&2; exit 64 ;;
esac
exec /usr/bin/flock --shared --nonblock "$app_root/storage/framework/deployment.lock" \
    /usr/bin/php "$app_root/artisan" "${arguments[@]}"
