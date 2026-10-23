#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ $# == 4 ]] || exit 64
app_root=$1
operations_root=$2
source_sha=$3
deploy_sha=$4
[[ "$source_sha" =~ ^[a-f0-9]{40}$ && "$deploy_sha" =~ ^[a-f0-9]{40}$ ]] || exit 1
script_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
cd -- "$app_root"
[[ -f .env && ! -L .env && -d .git && -d "$operations_root" ]] || exit 1
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor HEAD "$deploy_sha"
[[ "$(git rev-parse refs/adzbyte-ci/deploy)" == "$deploy_sha" ]] || exit 1
before_sha=$(git rev-parse HEAD)
environment_hash=$(sha256sum .env | cut -d' ' -f1)
phase=preflight
maintenance=0
mutating=0
backup_directory=''
write_status() {
    # Every interpolated field is a SHA, generated path, or an internal constant.
    printf '{"state":"%s","phase":"%s","source":"%s","deploy":"%s","previous":"%s","backup":"%s","time":"%s"}\n' \
        "$1" "$phase" "$source_sha" "$deploy_sha" "$before_sha" "$backup_directory" "$(date -u +%FT%TZ)" \
        > "$operations_root/last-release.json.tmp"
    mv -- "$operations_root/last-release.json.tmp" "$operations_root/last-release.json"
}
finish() {
    result=$?
    trap - EXIT HUP INT TERM
    if (( result != 0 )); then
        # Never roll back customer data or migrations automatically.
        if (( maintenance && ! mutating )); then /usr/bin/php artisan up || true; fi
        chmod 600 .env 2>/dev/null || true
        write_status failed || true
        echo "Deployment failed during $phase. Inspect this run and the protected server release record; do not auto-retry." >&2
    fi
    exit "$result"
}
trap finish EXIT
trap 'exit 143' HUP INT TERM
write_status running

phase=drain
exec 8>"$app_root/storage/framework/deployment.lock"
# HTTP requests and both Hostinger crons hold this lock shared for their entire lifetime.
# Wait for active scheduler/worker work, then block new cron runs until completion.
flock --exclusive --timeout 150 8 || { echo 'Active scheduled work did not drain; deployment stopped before mutation.' >&2; exit 75; }
phase=backup
backup_directory=$(mktemp -d "$operations_root/backup-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
/usr/bin/php "$script_root/backup.php" "$app_root" "$backup_directory" "$before_sha" "$deploy_sha"
[[ "$environment_hash" == "$(sha256sum .env | cut -d' ' -f1)" ]] || exit 1
phase=maintenance
/usr/bin/php artisan down --retry=60
maintenance=1
phase=checkout
mutating=1
# Public assets retain normal web-readable permissions; secrets are pre-existing 600.
umask 022
git merge --ff-only "$deploy_sha"
[[ "$(git rev-parse HEAD)" == "$deploy_sha" ]] || exit 1
chmod 600 .env
phase=laravel
umask 077
COMPOSER_BINARY=/usr/local/bin/composer2 PHP_BINARY=/usr/bin/php bash scripts/hostinger-after-pull.sh
umask 077
phase=verify
[[ "$environment_hash" == "$(sha256sum .env | cut -d' ' -f1)" ]] || exit 1
/usr/bin/php "$script_root/verify.php" "$app_root"
/usr/bin/php artisan operations:queue-health
/usr/bin/php artisan up
maintenance=0
# The verified application can accept new requests and new-code cron work now.
flock --unlock 8
exec 8>&-
phase=http
bash scripts/deployment/http-check.sh https://app.adzbyte.com
phase=complete
write_status success
echo "Verified source $source_sha at deploy $deploy_sha."
