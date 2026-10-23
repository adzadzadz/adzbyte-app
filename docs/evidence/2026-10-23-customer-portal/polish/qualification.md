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
  Queue atomicity and customer selection findings were fixed. The independent
  reviewer approved final source `bbf1ae5` and the gated production release.
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
customer API error wrapper. Delivery recovery scheduling remains enabled. The existing production Composer lock is retained after confirming identical
root requirements and package set; this preserves the newer installed PHP patches.
The portal frontend lock retains its Poppins/build dependencies. The full MySQL job now also covers the native
package tests; a redundant package-only job was removed. OpenAPI adds that legacy
integration contract without changing existing paths/schemas. Main has no generated
production build assets.

## Dependency/security gate — 2026-10-23

Fresh Composer audit (including development packages): zero advisories/abandoned
packages. Fresh npm audit: zero vulnerabilities across runtime/build dependencies.
Locked Laravel 13.38.0, Filament 5.10.3, Livewire 4.4.8, CommonMark 2.10.4
and Vite 8.2.4 retained. The compatible PHP patches match the live production
lock; nine regenerated Filament files match those packages.
Official checks:

- Laravel token-guard and relationship advisories patched before installed 13.38:
  https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6
  and https://github.com/laravel/framework/security/advisories/GHSA-5wcm-3v2w-8362.
- Filament tenant-tooltip fix is included in installed 5.10.3:
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
root public_html. Before deployment, production MariaDB 11.8.9 had seven prior migrations,
five package checkout records, five provider events and zero deliveries/jobs/
failed jobs. There were no production users, roles or permissions before bootstrap.

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

Production settings corrected in the authorized release window: .env
mode 644 → 600, encrypted/secure sessions false → true. Existing package payment/
bridge credentials and flags are retained. Generic ecommerce remains a preview;
its PayMongo adapter stays disabled unless independently configured/qualified.

Owner separately approved initializing access roles/permissions and creating the
owner super administrator for adzbite@gmail.com with a secure password setup link.
No real customer was created by verification. Final source CI, approval and
release results are recorded below.

Existing hPanel scheduler and database queue cron run every minute. The inherited
worker uses --tries=3 --timeout=90; the release set DB_QUEUE_RETRY_AFTER=180
so a reserved job cannot be reclaimed before its worker timeout. The owner setup
script was rehearsed against synthetic local data: creates only a new owner
identity, queues Filament's admin reset link atomically, and leaves email
verification required. It rejects an existing identity and never prints tokens.

The official October 21 Node advisory identified CI's cached Node24.21.0 as
below patched24.22.1. Both build/HTTP-consumer jobs now pin24.22.1 before
promotion; this is build exposure, with no Node runtime deployed for this PHP
app. The final workflow reruns all gates using the patched binary. References:
https://nodejs.org/en/blog/vulnerability/october-2026-security-releases and
https://nodejs.org/en/blog/release/v24.22.1. Local preview is stopped after QA;
release assets come only from patched CI, not the older local Node/PHP tools.


## Completed production release — 2026-10-23

- Approved source: `bbf1ae5a9f4dc6830b9f82ca3625ed7eddd6d531`.
  [Source CI 39923664446](https://github.com/adzadzadz/adzbyte-app/actions/runs/39923664446)
  and [release 39924287598](https://github.com/adzadzadz/adzbyte-app/actions/runs/39924287598)
  both succeeded. Each full SQLite/MySQL suite passes **662 tests / 8,632
  assertions** on PHP 8.3.36; build/HTTP-consumer Node is 24.22.1. Audits,
  formatting, metadata, build and production cache gates pass.
- Generated deploy: `230f2cd90d2b11ea12a03a00640fe35df66f260b`, containing
  the approved source and CI-built assets. Hostinger reports **Completed** at
  16:32 PHT; SSH HEAD matches. Hostinger uses a shallow checkout, so ancestry
  was verified in the full local repository rather than the hosting checkout.
- Reviewed environment update preserved APP_KEY and every non-allowlisted line,
  including native package/bridge credentials and flags. `.env` is mode 600;
  debug is false, sessions encrypted/Secure/HttpOnly/SameSite=Lax, database
  queue retry-after 180 with after-commit enabled. Generic commerce preview is
  true and its PayMongo adapter false; native package checkout remains enabled.
- `bash scripts/hostinger-after-pull.sh` exited 0: locked no-dev install,
  15 pending migrations, production caches and queue restart completed.
  All **22 migrations** are Ran. Five existing package checkouts and five
  provider events remain; all five encrypted contact snapshots decrypt with
  the preserved key. No package deliveries, work items or cases were added.
  Post-install untracked files are seven generated Filament font assets;
  tracked source has no runtime diff.
- The pre-migration encrypted backup and protected environment snapshot remain
  outside the web root at `private-backups/portal-20261023-072913`. The approved
  isolated restore and migration check succeeded; its local database, dump and
  key were deleted. This release proof does not define durable off-site
  retention or close the broader backup/privacy roadmap.
- Separately authorized bootstrap created exactly one new owner with only
  `super_admin`, initialized three roles/38 permissions and recorded one owner
  bootstrap audit event. No real customers or synthetic production fixtures
  were created. Filament's admin password setup notification ran successfully
  through SMTP at **08:34:30–08:34:33 UTC**; queue now has zero jobs and zero
  failures. Recipient inbox placement is not independently observed. Owner
  password choice and email verification remain user actions.
- `/up`, `/login` and `/admin/login` return 200 over HTTPS; `/`, `/projects`
  and `/support` redirect to customer login, `/admin` and `/admin/customers`
  to admin login. Production `/cart` returns 200 with **Under development**.
  Unauthenticated native package offer API retains its 401 response. `.env`
  and log paths return 403; composer.json is unavailable. Cookie security flags
  and security headers pass. The retired `/account/login` route returns 404.
- Scheduler retains native package delivery recovery and queue monitoring;
  existing scheduler/queue crons run every minute. Queue health passes after
  actual owner notification processing. Log review found one operator console
  diagnostic for an unsupported `route:list --columns` option; the corrected
  read-only command succeeded. No application/request/worker release errors
  were found. No payment, refund, entitlement or full commerce qualification
  is claimed.
- Production public login was visually checked. Authenticated customer/admin
  workflow and 320px proof use synthetic local fixtures on the released source;
  production authenticated browser verification awaits owner activation.
