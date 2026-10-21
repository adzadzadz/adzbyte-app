#!/usr/bin/env bash
set -euo pipefail
baseline=eda1d21cc641da7c8d3256c108da3c9d7a74bb8a
source_sha=${1:?Expected reviewed source SHA}
[[ "$source_sha" =~ ^[0-9a-f]{40}$ ]]
[[ "$(git rev-parse HEAD)" == "$source_sha" ]]
git merge-base --is-ancestor "$baseline" "$source_sha"
# An exact allowlist excludes the unreleased commerce work on main.
while IFS= read -r file; do
  case "$file" in
    composer.lock|package-lock.json|.env.example|.github/workflows/ci.yml|.github/workflows/deploy.yml|app/Providers/AppServiceProvider.php|bootstrap/app.php|routes/api.php|app/Filament/Resources/PackageCheckouts/PackageCheckoutResource.php|app/Filament/Resources/PackageCheckouts/Pages/ListPackageCheckouts.php|app/Filament/Resources/PackageCheckouts/Pages/ViewPackageCheckout.php|app/Http/Controllers/Api/V1/PackageCheckoutController.php|app/Http/Controllers/Api/V1/PackageQrphWebhookController.php|app/Http/Middleware/PackageCheckoutAccess.php|app/Http/Requests/PackageCheckoutRequest.php|app/Http/Resources/PackageCheckoutResource.php|app/Http/Responses/PackageCheckoutErrors.php|app/Models/PackageCheckout.php|app/Policies/PackageCheckoutPolicy.php|app/Services/PackageCheckout/PackageCheckouts.php|app/Services/PackageCheckout/PackageOffers.php|app/Services/PackageCheckout/QrphClient.php|app/Services/PackageCheckout/QrphFailure.php|app/Services/PackageCheckout/QrphSignature.php|app/Services/PackageCheckout/QrphWebhooks.php|config/package_checkout.php|database/migrations/2026_10_22_000000_create_package_checkouts.php|docs/STATUS.md|docs/reviews/2026-10-22-native-qrph-package-checkout.md|scripts/verify-package-release.sh|tests/Feature/PackageCheckoutTest.php|tests/Support/package-qrph-http-router.php) ;;
    *) printf 'Unreviewed release path: %s\n' "$file" >&2; exit 1 ;;
  esac
done < <(git diff --name-only "$baseline" "$source_sha")
if git diff --name-status "$baseline" "$source_sha" | awk '$1 == "D" || $1 ~ /^R/ { found=1 } END { exit !found }'; then
  echo "Package releases cannot delete or rename baseline files." >&2
  exit 1
fi
