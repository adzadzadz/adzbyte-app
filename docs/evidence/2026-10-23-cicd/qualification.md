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
  ordinary permissions while secrets remain600.
- Failure records and phase metadata, no blind deployment retry, no automatic
  customer-data rollback. Maintenance/availability behavior documented by phase.

## Verification so far

- Deployment safety integration: **15 tests**, including real disposable Git
  histories, data/environment preservation, real PHP request/shutdown locking,
  real encrypted backup round-trip and temporary credential/plaintext cleanup.
  Initial local failures exposed absent macOS flock and Bash3.2 conditional
  errexit behavior; tests use actual OS flock through a local shim and guards now
  explicitly exit. Linux CI exercises native util-linux flock.
- Full local application suite: **678 tests / 8,930 assertions**, exit0.
  PHP8.5.11 SQLite. Formatting, Composer metadata, YAML parse and whitespace pass.
- New non-mutating production config/migration verifier passes against the live
  PHP8.3 app. Hostinger8.3.35 lacks PHP_BUILD_DATE; guarded buffered phpinfo parsing
  checks its specifically qualified Oct21 security build instead.
- Independent reviewer requested active-work drain and a later-shutdown lock fix;
  both implemented and regression tested. Reviewer independently passes all15
  deployment tests and **approves revised implementation/cutover plan**, subject
  to actual key/pin, cron locks, main-only environment and auto-pull-off checks.

## Activation state

One-time access/cron/Hostinger cutover and exact-source CI are pending. Readiness
must remain false until each setup check is recorded. Production remains deploy
`3bcc292e59313c27a965510f51a085a06945f8e8`. No code release has run for this work.
