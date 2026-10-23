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
- Fresh protected encrypted database/environment backup with decryption/hash
  round-trip. No plaintext credential/dump or backup material reaches GitHub.
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

Production remains deploy `3bcc292e59313c27a965510f51a085a06945f8e8`. No code
release has run for this work. The first full encrypted live backup/deployment
run requires the separate explicit release command; do not claim that end-to-end
production qualification yet. Backup round-trip failure paths are covered with
synthetic SQL/real OpenSSL locally. Backups remain on-host; offsite retention and
an isolated restore exercise are separate operational work.
