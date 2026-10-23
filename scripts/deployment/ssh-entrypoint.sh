#!/usr/bin/env bash
# Install outside public_html; authorized_keys must force this entrypoint.
set -euo pipefail
umask 077
app_root='/home/u463458285/domains/app.adzbyte.com/public_html'
operations_root='/home/u463458285/.local/share/adzbyte-app-deploy'
repository='https://github.com/adzadzadz/adzbyte-app.git'
command=${SSH_ORIGINAL_COMMAND:-}

if [[ "$command" != preflight && ! "$command" =~ ^deploy\ ([a-f0-9]{40})\ ([a-f0-9]{40})$ ]]; then
    echo 'Rejected: only preflight or deploy with two full commit SHAs is allowed.' >&2
    exit 64
fi
if [[ "$command" != preflight ]]; then
    source_sha=${BASH_REMATCH[1]}
    deploy_sha=${BASH_REMATCH[2]}
fi
[[ -d "$app_root/.git" && -f "$app_root/.env" && ! -L "$app_root/.env" ]] || exit 1
[[ -d "$operations_root" && ! -L "$operations_root" ]] || exit 1
for binary in git flock timeout /usr/bin/php /usr/local/bin/composer2 /usr/bin/mysqldump /usr/bin/openssl curl; do
    command -v "$binary" >/dev/null
done
# PHP8.3.35 is accepted only with Hostinger's qualified Oct21+ security backport.
/usr/bin/php -r '
$patched = PHP_VERSION_ID >= 80336;
if (PHP_VERSION_ID === 80335) {
    ob_start();
    phpinfo(INFO_GENERAL);
    $information = ob_get_clean();
    preg_match("/^Build Date => (.+)$/m", $information, $match);
    $built = isset($match[1]) ? strtotime($match[1]." UTC") : false;
    $patched = $built !== false && $built >= strtotime("2026-10-21 UTC");
}
exit($patched && PHP_VERSION_ID < 80400 ? 0 : 1);'
[[ "$(stat -c %a "$app_root/.env")" == 600 ]] || exit 1
[[ -f "$operations_root/runtime-lock-enabled" ]] || {
    echo 'The scheduler and worker must use the shared runtime lock before enabling CI deployment.' >&2
    exit 1
}
git -C "$app_root" diff --quiet
git -C "$app_root" diff --cached --quiet
if [[ "$command" == preflight ]]; then
    echo "Protocol 1 ready; current deploy $(git -C "$app_root" rev-parse HEAD)."
    exit 0
fi

exec 9>"$operations_root/deploy.lock"
flock -n 9 || { echo 'A deployment is already running.' >&2; exit 75; }
# Fixed public origin: no GitHub token or hosting OAuth credential reaches Git.
export GIT_TERMINAL_PROMPT=0
git -C "$app_root" fetch --quiet --no-tags "$repository" \
    refs/heads/main:refs/adzbyte-ci/main refs/heads/deploy:refs/adzbyte-ci/deploy
[[ "$(git -C "$app_root" rev-parse refs/adzbyte-ci/deploy)" == "$deploy_sha" ]] || exit 1
git -C "$app_root" merge-base --is-ancestor "$source_sha" refs/adzbyte-ci/main
git -C "$app_root" merge-base --is-ancestor "$source_sha" "$deploy_sha"
git -C "$app_root" merge-base --is-ancestor HEAD "$deploy_sha"
[[ "$(git -C "$app_root" log -1 --format=%s "$deploy_sha")" == "Deploy ${source_sha:0:12}" ]] || exit 1

release_scripts=$(mktemp -d "$operations_root/scripts.XXXXXXXX")
trap 'rm -rf -- "$release_scripts"' EXIT
for file in hostinger-deploy.sh backup.php verify.php; do
    git -C "$app_root" show "$deploy_sha:scripts/deployment/$file" > "$release_scripts/$file"
done
# The reviewed target supplies versioned release logic; the forced command stays fixed.
timeout --signal=TERM --kill-after=30 900 bash "$release_scripts/hostinger-deploy.sh" \
    "$app_root" "$operations_root" "$source_sha" "$deploy_sha"
