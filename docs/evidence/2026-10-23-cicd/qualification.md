# Unattended Hostinger release qualification — 2026-10-23

Owner requested proper CI/CD so releases no longer require visiting Hostinger.
Keep the current explicit GitHub release trigger; automate server deployment and
verification. Source setup does not by itself authorize the first production
release under the project's separate release gate.

## Prepared implementation

- Manual main-only release, optional exact-source pin, existing full CI/audits and
  asset artifact, readiness/SSH preflight before branch promotion, stale main
  rejection, forward-only deploy SHA and ancestry verification.
- Dedicated forced-command SSH entrypoint outside web root; only preflight or
  deploy with two exact SHAs. Fixed public repository and app root. New key stays
  in the main-only GitHub production environment; operator key is retained locally.
- One shared lock across HTTP request lifetime (including later shutdown work),
  scheduler and worker; exclusive bounded drain before backup/maintenance/checkout.
- Fresh encrypted database backup with decryption/hash round-trip and a separate
  protected environment snapshot. No credential/dump/backup material reaches GitHub.
- No-dev locked install, forward migrations, caches, worker restart, environment
  hash/permissions preservation, production config/schema/queue validation,
  on-server and runner HTTPS checks including compiled CSS/JS. Public files use
  ordinary permissions while secrets remain `600`.
- Failure records and phase metadata, no blind deployment retry, no automatic
  customer-data rollback. Maintenance/availability behavior documented by phase.

## Verification so far

- Deployment safety integration: **16 tests**, including real disposable Git
  histories, data/environment preservation, real PHP request/shutdown locking,
  real encrypted backup round-trip and temporary credential/plaintext cleanup.
  Initial local failures exposed absent macOS flock and Bash3.2 conditional
  errexit behavior; tests use actual OS flock through a local shim and guards now
  explicitly exit. Linux CI exercises native util-linux flock.
- Followup coverage verifies fixed cron modes and shared lock arguments, plus
  an existing compiled-secret cache `0644` becomes `0600`. Composer/public files use `022`;
  Laravel migration/cache steps use `077`. The verifier enforces cached config `600`.
- Full local application suite: **678 tests / 8,930 assertions**, exit0.
  PHP8.5.11 SQLite. Formatting, Composer metadata, YAML parse and whitespace pass.
- New non-mutating production config/migration verifier passes against the live
  PHP8.3 app. Hostinger8.3.35 lacks PHP_BUILD_DATE; guarded buffered phpinfo parsing
  checks its specifically qualified Oct21 security build instead.
- Independent reviewer requested active-work drain and a later-shutdown lock fix;
  both implemented and regression tested. Reviewer independently passes all15
  deployment tests and **approves revised implementation/cutover plan**. Final
  bounded review also approves isolated identity/access workflow/cron wrapper and
  the compiled-secret permission correction, with independent regression proof.
- Initial source `1e5899b` CI 39970852391 passes both 678 / 8,930 PHP8.3.36 database
  suites, all15 then-current deployment tests, audits, build, cache and formatting.
  Followup CI 39972885165 was superseded by the final documented source.
- Final source `7928aa9f5e031ea1db7aab52a6d2b7bde8e75141` passes
  [CI 39973252724](https://github.com/adzadzadz/adzbyte-app/actions/runs/39973252724):
  **678 tests / 8,930 assertions each SQLite/MySQL**, **16 deployment tests**, PHP/npm
  audits report no vulnerabilities, formatting/assets/caches all pass. Exit 0.

## Activation state

- Production environment allows only branch `main`. Dedicated deployment key and
  trusted known-hosts are stored as environment secrets. Existing operator key
  is preserved. Arbitrary `id` with the dedicated identity and no SSH config
  is rejected exit64; forced `preflight` succeeds. No credentials are committed.
- Scheduler runs each minute with direct shared/nonblocking flock; worker runs
  each minute through the reviewed outside-webroot wrapper, accommodating
  Hostinger's 255-character command limit. Old unlocked entries removed only
  after replacements existed. Actual scheduler output 14:46 UTC shows all three
  scheduled tasks DONE; actual worker output 14:46 UTC is STOPPED / Queue empty.
  Both use the exact same storage/framework/deployment.lock. Ready marker set.
- Hostinger auto-deployment is off, confirmed after reload by visible tooltip
  and screenshot. No Redeploy action was used. The existing website is retained.
- [GitHub access check 39972909499](https://github.com/adzadzadz/adzbyte-app/actions/runs/39972909499)
  succeeds from GitHub's runner using environment secrets and strict host pin:
  `Protocol 1 ready; current deploy 3bcc292e…`. It performs no code promotion.
- Readiness variable `HOSTINGER_SSH_AUTOMATION_READY` is `true` only after these checks.
  Dedicated local temporary private-key material is removed after connection proof.
- Existing compiled production configuration was hardened to `600`; `.env` remains `600`.
  The final read-only production verifier passes config/schema/queue/permissions.
  Live health/customer/admin/reset pages return 200, root redirects correctly,
  environment/log probes return 403, tracked source is clean and HEAD remains `3bcc292e`.

At setup completion, production remained deploy `3bcc292e59313c27a965510f51a085a06945f8e8`
and no code release had run. The following owner-authorized execution supersedes
that pending state. Backups remain on-host; offsite retention and a new isolated
restore exercise remain separate operational work.

## First production run — passed

Owner command: "deploy and make sure it's fully tested before saying the task is
completed". Exactly one deploy workflow was dispatched, with main scope and exact
expected-source pin. No manual deploy-branch push, hPanel redeploy, duplicate
post-pull, seed, account creation, credential rotation or data rollback ran.

| Field | Verified result |
| --- | --- |
| Workflow | [39975941196](https://github.com/adzadzadz/adzbyte-app/actions/runs/39975941196), success / watch exit 0 |
| Source | `d6f6df447987d63c909c9037f6de58e68f06bb9b` |
| Deploy / live HEAD | `621e2c47763c3f49583c536c8c781cbdaf87ddee` |
| Previous production | `3bcc292e59313c27a965510f51a085a06945f8e8` |
| SQLite | 678 tests / 8,930 assertions, pass |
| MySQL including independent-process races | 678 tests / 8,930 assertions, pass |
| Deployment failure/safety suite | 16 tests, pass |
| Fresh Composer / npm audits | No advisories / 0 vulnerabilities, no suppression |
| Metadata / formatting / build / cache generation | Pass |
| Restricted SSH preflight / source-tip verification | Pass before branch promotion |
| Server deployment and independent runner HTTPS/asset checks | Pass, production job 58 seconds |

### Actual backup, data and runtime checks

- Protected release record says success / complete at 2026-10-23T15:19:35Z and
  matches both published source/deploy SHAs. Server and origin/deploy agree;
  source is an ancestor, tracked files are clean and maintenance is absent.
- A real production MySQL dump was encrypted and stream decrypted back to its
  original hash before mutation. A separate plaintext environment snapshot stays
  protected at `0600` inside the private backup directory. Independent post-release
  metadata and file checks confirm the exact target/previous versions, backup
  directory 700, encrypted dump/key/environment/metadata 600, and no temporary
  plaintext SQL or client credential file. The backup path is recorded only in
  the protected server release record outside the web root.
- Before/after comparison emits only aggregate counts, approved QA existence
  booleans and environment/key fingerprints; no customer rows or messages are
  exported. Environment bytes and application key match. Counts are unchanged:
  users 2, work items 2, support cases 1, public messages 1, notes 0; milestones/updates 0.
  QA user 2 remains verified, owns both expected public work IDs and the same
  project-linked report.
- All22 migrations remain Ran; the automated post-pull reports Nothing to migrate.
  Production configuration/schema/queue verifier passes. `.env` and compiled
  configuration are 600. Queue health is good, jobs 0 / failed_jobs 0.
- Daily-log tail after the pre-release byte offset contains no new application
  ERROR/CRITICAL/ALERT/EMERGENCY entries.
- Actual automatic scheduler output 15:22 UTC shows package delivery, approval
  expiry and queue monitor DONE. Worker output 15:22:05 UTC says STOPPED / Queue empty.
  Both occur after release completion, proving the deployment lock was released
  and normal scheduled execution resumed. hPanel was used only to read outputs;
  no hosting setting or deployment was changed during this release.
- Real Chrome reload preserves the QA customer's authenticated, verified session.
  Dashboard shows one active project, one active service and one conversation.
  Both assignment details open correctly. The same existing public admin reply
  is visible after reload on Requests and expanded inline on the project. The
  service correctly has no unrelated project conversation. No new test records
  were created. The customer remains on Dashboard for manual testing.

### Fresh official security review

Locked Laravel 13.38.0, Filament 5.10.3, Livewire 4.4.8, CommonMark 2.10.4,
Vite 8.2.4 and Node 24.22.1 remain unchanged. Full runtime/development dependency
audits run again inside this release, before promotion, with zero findings.
Official advisory review confirms the installed Laravel token-guard fix,
Filament scope/tooltip fixes, Livewire authorization fix and Vite middleware fix:

- https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6
- https://github.com/filamentphp/filament/security/advisories/GHSA-6p72-cx74-63f5
- https://github.com/filamentphp/filament/security/advisories/GHSA-9f9v-vm6w-3wx6
- https://github.com/livewire/livewire/security/advisories/GHSA-mhf7-xp6r-6qrc
- https://github.com/vitejs/vite/security/advisories/GHSA-9jrq-w75r-8gcw
- https://nodejs.org/en/blog/release/v24.22.1

Production remains managed Hostinger PHP 8.3.35 built October 21, matching its
published patched-build criterion. Hostinger's October 23 update also confirms
managed plans are protected against the third disclosed php2shell vulnerability:
https://www.hostinger.com/blog/protecting-php2shell/.
Upstream fixed-release reference: https://www.php.net/ChangeLog-8.php#8.3.36.
Official runtime/advisory review remains the documented manual release check;
Composer/npm audits and the qualified PHP build guard are automated. No material
high/critical finding is deferred, and no dependency update was required.

The CI/CD implementation was independently approved before this release. A final
independent assurance review confirms source/deploy ancestry, release evidence
and updated guidance, with no remaining release blockers. Its wording correction
clarifies that the SQL dump is encrypted while the environment snapshot is protected at `600`.
Canonical agent release instructions are aligned so future releases verify the
workflow-owned post-pull and never repeat it manually.
