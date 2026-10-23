#!/usr/bin/env bash
set -euo pipefail
baseline=a5d9e3c3d7a214768f129e5c93fbd3705db94b9b
source_sha=${1:?Expected reviewed source SHA}
[[ "$source_sha" =~ ^[0-9a-f]{40}$ ]]
[[ "$(git rev-parse HEAD)" == "$source_sha" ]]
git merge-base --is-ancestor "$baseline" "$source_sha"
# An exact allowlist excludes the unreleased commerce work on main.
while IFS= read -r file; do
  case "$file" in
    app/Notifications/CustomerAccountActivation.php|.github/workflows/deploy.yml|scripts/verify-package-release.sh) ;;
    *) printf 'Unreviewed release path: %s\n' "$file" >&2; exit 1 ;;
  esac
done < <(git diff --name-only "$baseline" "$source_sha")
if git diff --name-status "$baseline" "$source_sha" | awk '$1 == "D" || $1 ~ /^R/ { found=1 } END { exit !found }'; then
  echo "Package releases cannot delete or rename baseline files." >&2
  exit 1
fi
