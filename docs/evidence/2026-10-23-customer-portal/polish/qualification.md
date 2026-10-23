# Portal workflow polish and production integration

Owner scope: inline linked requests/reports and replies; expandable complete inbox;
customer Dashboard; manual admin customer/work creation; independent approval and
production deployment. Ecommerce development remains paused.

## Source and local verification

- Portal changes start at `4f745af`; integrated production ancestry is
  `1acfad028e818fc93c0ad1568878879b261a5875` (source `30c8cf369cc4`).
- Full integrated local suite: **662 tests / 8,632 assertions**, PHP 8.5.11,
  SQLite. A stale activation-email subject expectation was updated to the
  existing production Adzbyte Solutions branding; the actual queue/SMTP test
  then passed. Full suite subsequently passed. Formatting, Composer metadata,
  production Vite build and whitespace checks pass.
- Independent reviewer reproduced 7 focused workflow tests / 68 assertions.
  Queue atomicity and customer selection findings were fixed. Final release
  approval awaits exact source/PHP 8.3 CI evidence.
- Real database queue checks prove customer, audit and invitation commit together;
  a later audit failure rolls back both identity and queued job. Runtime rejects
  a database queue using a different database connection.
- Customer/account/closed-case/revoked-staff boundaries remain enforced. Linked
  inbox cannot expand a case from another customer or a different work record.
  Read acknowledgement is revision-bound. Thread and inbox pagination are separate.
- Native browser: customer project expand → reply → create stays on project URL;
  dashboard has the full expandable inbox; manual customer → Assign work retains
  the selected customer through an actual Livewire POST and creates the correct
  owner; staff resolves and reopens a case in project context with reply state
  updating correctly. Staff-only notes remain separate.
- 320px admin project/thread and customer management checks: document width 320px,
  viewport 320px. Evidence screenshots contain synthetic local records only.

## Preserved production integration

Native package checkout controllers, models, services, policies, resource, jobs,
migrations and tests are retained from production. URLs and response envelopes
remain compatible. Package routes explicitly exclude stateful Sanctum middleware,
retain dedicated token/proof/raw-signature protection, and bypass the unrelated
customer API error wrapper. Delivery recovery scheduling remains enabled. Current
main dependency locks are retained. The full MySQL job now also covers the native
package tests; a redundant package-only job was removed. OpenAPI adds that legacy
integration contract without changing existing paths/schemas. Main has no generated
production build assets.

## Dependency/security gate — 2026-10-23

Fresh Composer audit (including development packages): zero advisories/abandoned
packages. Fresh npm audit: zero vulnerabilities across runtime/build dependencies.
Locked Laravel 13.37.0, Filament 5.10.2, Livewire 4.4.7 and Vite 8.2.4 retained.
Official checks:

- Laravel token-guard and relationship advisories patched before installed 13.37:
  https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6
  and https://github.com/laravel/framework/security/advisories/GHSA-5wcm-3v2w-8362.
- Filament tenant-tooltip fix is included in installed 5.10.2:
  https://github.com/filamentphp/filament/security/advisories/GHSA-9f9v-vm6w-3wx6.
- Vite custom middleware fix is included in 8.2.4; October 21 v6 issue does not
  affect v8: https://github.com/vitejs/vite/security/advisories/GHSA-9jrq-w75r-8gcw
  and https://github.com/vitejs/vite/security/advisories/GHSA-8g5g-mxqh-grgj.
- Hostinger reports its PHP security fix applied. Production CLI reports 8.3.35
  built October 21; Hostinger documents backporting the fix without changing the
  version string and identifies October 21+ build date as patched:
  https://www.hostinger.com/blog/protecting-php2shell/.
  Upstream reference: https://www.php.net/releases/8_3_36.php.

Audits are CI gates. Official-advisory/runtime verification remains a manual
pre-release step; no high/critical finding is deferred.

## Preflight and restore proof

GitHub production environment exists and workflow content writes are enabled.
hPanel points to this repository's deploy branch, automatic deployment enabled,
root public_html. Production database MariaDB 11.8.9 has seven prior migrations,
five package checkout records, five provider events and zero deliveries/jobs/
failed jobs. There are no production users, roles or permissions yet.

Encrypted pre-migration backup retained outside the web root in the owner-only
private-backups directory. A metadata helper encountered the host-disabled
shell_exec function after creating the encrypted dump; its file-only replacement
completed the metadata without changing production data. Automatic approval
initially rejected transferring a backup plus key to the Mac; the owner explicitly
approved the temporary restore check. The restored backup applied every pending
migration on an isolated local MariaDB Unix socket: **22 migrations; five package
checkouts and five events retained; zero deliveries/work items/cases**. Local dump,
key and restored database are removed after the check. Production APP_KEY is
preserved and compared by hash only, never printed.

Production settings needing correction in the authorized release window: .env
mode 644 → 600, encrypted/secure sessions false → true. Existing package payment/
bridge credentials and flags are retained. Generic ecommerce remains a preview;
its PayMongo adapter stays disabled unless independently configured/qualified.

Owner separately approved initializing access roles/permissions and creating the
owner super administrator for adzbite@gmail.com with a secure password setup link.
No real customer is created by verification. Source CI, final approval and actual
release results are recorded below when available.
