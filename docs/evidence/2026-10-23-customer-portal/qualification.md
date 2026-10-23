# Customer portal qualification — 2026-10-23

## Scope and state

Owner-approved independent portal increment: manually assign projects/services
to customer accounts, track progress/milestones/next steps, and provide linked
support requests/reports and customer–staff conversations. Existing ecommerce
screens remain accessible, with production Under development notices and
server-side customer write protection. Webhooks and staff operations are not
intercepted. Implementation source `d93b6b402b9af90e4cb692470a13bc3eeb0e4d78` is pushed to
`main` and fully source-qualified. No production change occurred.

This does not qualify commerce O2.3c, purchased entitlements O3/S2, subscriptions,
the broader R1 notification/entitlement integration, or launch Q. Those remain
paused. Native attachments remain deferred in favor of customer-shared Drive
links. This increment provides persistent in-app activity/read indicators;
outbound portal email notifications remain a separate qualification item.

## Acceptance evidence

- `CustomerPortalTest`: owned work/service reads, manual creation, milestone
  progress, updates, public messages, private notes, optional owned linkage,
  no-purchase reports, active categories, audit retention, permission/role
  revocation, retries, conflict and strict input denial.
- `CustomerPortalPanelTest`: real Filament create/update/milestone/publish and
  request/reply/note/triage actions, live search, guest/owner denial, stale forms,
  read-only staff and production ecommerce visibility/write denial. Locked
  operation UUIDs exist in the pre-submit browser snapshot for lost-response
  retries; successful commands rotate them. Production test host state is reset.
- `CustomerPortalConcurrencyTest`: four independent PHP processes deduplicate a
  repeated create command; competing staff updates have one winner and three
  revision conflicts. Run on each CI database engine.
- `CustomerPortalMigrationTest`: apply after prior tables with an existing user,
  empty reverse/reapply and retention of prior application records. Populated
  reversal fails closed rather than dropping customer history. Test schemas only.
- `CustomerPortalTransportTest`: independent Node HTTP/OpenAPI consumer with
  actual encrypted sessions, real CSRF, Origin and ownership denial, strict
  payloads, retry/conflict/read/list/detail contracts, private no-store headers
  and persistent rate limiting. Each customer uses their own session CSRF token.
- Real in-app Chromium, synthetic accounts and disposable SQLite: customer login,
  desktop project details, linked request form with project preselected, request
  submission, 320px conversation/reply, staff login and actual staff response,
  manual project assignment via name lookup, and customer visibility of that work.
  Keyboard Enter navigation works; browser warning/error logs are empty.
  Customer DOM excludes the synthetic private note. At 320px both document
  width and scroll width are 320px for Home and support. Screenshots and the
  structured browser result are stored beside this record. Viewport override reset.

## Commands/results

- Initial full local run exceeded PHP CLI's 128 MiB memory limit. The complete
  suite with 512 MiB passed **556 tests / 7,803 assertions** on PHP 8.5.11 SQLite.
  Test-only memory limits are explicit in both PHPUnit configurations; runtime
  application memory configuration is unchanged.
- Final focused portal/Home follow-up after strict revision/UUID normalization
  and staff display polish: **16 tests / 230 assertions**, passed. UUID casing
  retries and native-integer revisions are covered.
- [CI 39911846713](https://github.com/adzadzadz/adzbyte-app/actions/runs/39911846713)
  verifies implementation source `d93b6b402b9af90e4cb692470a13bc3eeb0e4d78`.
  PHP **8.3.36 SQLite: 556 tests / 7,804 assertions**, passed; both audits,
  formatting, metadata, build and production caches passed. The independent
  PHP **8.3.36 / MySQL 8.0.46** job also passed **556 tests / 7,804 assertions**,
  including migration/rollback safeguards and actual process contention.
  Both CI jobs completed successfully. Laravel 13.37.0, Filament 5.10.2,
  Livewire 4.4.7 and Vite 8.2.4 are the tested locked versions.
- Formatting, Composer metadata and production asset build pass locally.
- Composer audit: zero advisories. Initial npm audit found Vite 8.2.0 high-severity
  development/build-tool advisories. Installed compatible Vite **8.2.4**, with
  install scripts disabled, then rebuilt successfully; npm audit reports zero.
  See the official [cross-origin advisory](https://github.com/vitejs/vite/security/advisories/GHSA-9jrq-w75r-8gcw)
  and [filesystem advisory](https://github.com/vitejs/vite/security/advisories/GHSA-vfpm-58rq-9qcg).
- Audit egress was initially rejected by automatic review. Inspection established
  that all 150 Composer dependencies have public GitHub sources, all resolved npm
  packages use the public npm registry, and no private repositories are configured.
  Automatic review then allowed the read-only audits. No secrets, application
  source, or customer records were sent to audit registries.

## Cleanup and release boundary

Configuration/events/routes/views/Filament caches compile and clear successfully
with isolated test settings. Formatting, metadata and whitespace checks pass.
The temporary browser tab is closed, the QA server is stopped, and its
synthetic database, fixture scripts and key file are removed. All applicable
source checks passed for the implementation revision. No ongoing local process or fixture is needed to use the saved screenshots. No production release, existing database
migration, real customer records, provider operation or external email occurred.

Staff usage after a separately authorized release: grant the explicit portal
capabilities in Roles (or use the existing super administrator), open
**Customer portal → Projects & services → Add project or service**, select a
customer, then add milestones and post updates. Customers use Projects,
Services and Requests & reports. Ecommerce uses
`COMMERCE_PREVIEW_IN_PRODUCTION=true` by default until its later release gate.

The documentation-only qualification follow-up does not alter the tested
implementation. Production release, existing database migration, role/capability
seeding and post-release workflows remain a separately authorized gate.
