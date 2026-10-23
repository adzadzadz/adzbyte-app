# Project Status

**Last updated:** 2026-10-24 (combined UI/CRUD and ecommerce lock release authorized; exact-source qualification pending)

## Commerce navigation lock — locally verified, 2026-10-24

Customer Ecommerce/Cart/Orders and every admin Commerce navigation entry, including Package checkouts, now
show lock icons and stay on the current page. Clicking or using Enter/Space
shows **This feature is under development.** The dashboard commerce shortcut
uses the same locked behavior. Direct commerce screen URLs return a private,
uncached 503 notice; Filament retains the guard for subsequent Livewire requests.
`COMMERCE_FEATURES_LOCKED` defaults to `true` in every application environment;
commerce development tests explicitly disable it and lock tests enable it.
Existing API/provider processing behavior is unchanged by this UI lock.

Focused navigation/account/portal tests pass **12 tests / 304 assertions**.
The production asset build passes; an isolated browser fixture using the actual
Filament navigation renderer confirms click/keyboard tooltip feedback without
navigation. No production deployment or data change occurred.

The owner subsequently authorized the combined UI/admin CRUD release with only
ecommerce locked. Data management and project management remain accessible.
Fresh Composer/npm audits pass with zero findings; [release preflight](evidence/2026-10-24-commerce-lock/release-preflight.md)
records the source boundary and official advisory review.

Next: commit the release source, pass exact-source CI on both databases and run
the protected production workflow once.

## Recoverable admin CRUD — verified source, included in authorized release

The owner selected editable admin business records, preserved payment/provider/
audit history, and cart changes in the customer workflow. Administration → Data
management now covers 30 allowlisted types with Active/Deleted/All filters,
safe details, confirmed Delete/Restore, parent recovery and lifecycle history.
Existing admin resources and portal details expose contextual controls; staff
can create requests and edit their details in side drawers. Original rows and
business status values remain stored. Deleted identities and stale edits are
blocked; self/last-super and assigned-role protections include concurrent races.

Source `9a46720` passes [CI 40013619074](https://github.com/adzadzadz/adzbyte-app/actions/runs/40013619074):
**723 tests / 9,380 assertions independently on SQLite and MySQL 8.0.46,
PHP 8.3.36**, including populated migration/recovery and independent-process
contention. Audits report zero vulnerabilities; formatting, clean installs,
builds, 16 deployment-safety tests and production caches pass. Independent
review has no unresolved material findings. Actual desktop/320px create/edit,
Delete/Restore, parent recovery, modal focus and customer-denial checks pass.

One additive metadata migration is included but has not been applied to any
existing database. The earlier **do not deploy yet** hold is superseded by the
owner's current combined release authorization. At source qualification no merge,
deployment, real mail or provider operation occurred. Permanent purge/bulk
deletion, support email/attachments/automatic refresh/status transitions and
production latency measurement remain outside this increment. Commerce stays
paused. [Full CRUD review, limitations and evidence](evidence/2026-10-24-filament-ux/record-management.md).

Next: owner review of the combined [draft PR #1](https://github.com/adzadzadz/adzbyte-app/pull/1).

## Filament UX improvements — verified source, ready for owner review

Three authorized batches retain Filament/Livewire and remove the abandoned
React dependencies. Panel navigation, actionable waiting queues, staff filters,
contextual assignment/work drawers, milestone editing, inline conversations,
draft/conflict recovery and account/commerce clarity are implemented. Independent
review approves the source after regression fixes. Built desktop/mobile browser
checks pass using disposable synthetic data, including 320px without horizontal
overflow; the QA server and databases are removed.

The complete local SQLite suite passes **692 tests / 9,028 assertions**.
[CI 40004323532](https://github.com/adzadzadz/adzbyte-app/actions/runs/40004323532)
passes the same **692 / 9,028 on each database**, SQLite and MySQL 8.0.46, using
PHP 8.3.36, for implementation `229b296` in
[draft PR #1](https://github.com/adzadzadz/adzbyte-app/pull/1). Full Pint, metadata,
both audits, clean installs, build, all 16 deployment-safety tests and production
caches pass. No merge or production deployment occurred. Commerce stays paused.
[Full review and evidence](evidence/2026-10-24-filament-ux/qualification.md).

Next: owner review of draft PR #1. Deployment requires a separate explicit command.

## Guest authentication visual refresh — locally verified, 2026-10-24

Customer and administrator sign-in retain the familiar Filament form card, now
positioned on the right on desktop with refined spacing, controls and focus
treatment. Password recovery shares the shell. A self-hosted GetLayers **Purple
Desert** landscape supplies the background: a 1.02 MB silent loop on desktop,
pause/play control, and a 48 KB poster for mobile, reduced-motion and data-saving
preferences. The source is a free commercially licensed download; provenance
and hashes are recorded in `resources/brand/auth-background.json`.

Focused branding/authentication verification passes **19 tests / 118 assertions**;
PHP formatting and the Vite build pass. Browser checks cover customer/admin,
password recovery, 320/390px layouts, validation errors and animation controls.
The encoded video decodes cleanly. No authentication rules or production data
changed. Next: review the local sign-in design; deployment remains separately gated.

## Latest production release — automated CI/CD verified, 2026-10-23

Owner-authorized [release 39975941196](https://github.com/adzadzadz/adzbyte-app/actions/runs/39975941196)
deploys source `d6f6df447987d63c909c9037f6de58e68f06bb9b` as
`621e2c47763c3f49583c536c8c781cbdaf87ddee`. Both database suites pass 678 tests /
8,930 assertions; all 16 deployment safety tests, fresh dependency audits, build
and cache checks pass. GitHub performed the encrypted backup, locked update,
post-pull, migrations, cache generation, worker restart and both HTTPS checks
without manual production deployment commands or hPanel changes.

Independent checks confirm the live SHA, protected backup/secret permissions,
unchanged environment/key and aggregate customer/work/conversation counts,
all 22 migrations applied, zero queued/failed jobs and no new application errors.
The existing customer session survives reload, opens both assignments and shows
the same admin reply on the project and Requests pages. Scheduler/worker outputs
at 15:22 UTC prove automatic work resumed. [Execution evidence](evidence/2026-10-23-cicd/qualification.md#first-production-run--passed).

## Portal workflow and production integration — deployed

Customer Dashboard replaces Home. Requests/reports use an expandable inbox on
Dashboard, Requests & reports, and linked project/service pages; inline create
and replies keep the current work page. Admin Customers supports manual identity
creation, signed activation invitations and work assignment. Invitations use the
same-connection database queue and audit/user/job commit together. New customer
capabilities remain explicit; no staff rights are granted to customers.

Latest account lifecycle release [39947378628](https://github.com/adzadzadz/adzbyte-app/actions/runs/39947378628)
passed and deployed source `a3eeb5a96c80` as `3bcc292e5931` on Hostinger.
Both PHP 8.3.36 SQLite/MySQL suites pass **677 tests / 8,853 assertions**;
Node 24.22.1, dependency audits and independent review pass. Both panels now
queue verification on first prompt entry and scope password recovery before
creating or consuming reset tokens. Post-pull completed with nothing to migrate;
all 22 migrations remain Ran. Environment contents, encryption key and existing
records are preserved; `.env` is mode 600 after restoring permissions reset by
Hostinger's pull. Health/auth pages, signed-in admin Dashboard and queue health
pass. The owner has signed in and verified email; no real customer or production
fixture was created. See [account lifecycle release evidence](evidence/2026-10-23-customer-portal/polish/account-lifecycle-review.md).
The earlier [portal release](evidence/2026-10-23-customer-portal/polish/qualification.md)
remains qualified; native public-site package checkout/fulfilment is retained.
This current release record supersedes historical production statements below.

## Current Stage

**Current owner priority:** complete the customer portal, especially manually
assigned project and service tracking. Ecommerce development is paused;
commerce screens are locked with **Under development** navigation tooltips
and direct-page protection. Staff can assign work to a customer, manage
milestones/status/next steps and publish updates. Customers can create linked
or standalone requests/reports and exchange messages with authorized staff;
private notes stay separate. See the [portal scope](plans/2026-10-23-customer-portal.md),
[API contract](api/customer-portal.md), and [qualification](evidence/2026-10-23-customer-portal/qualification.md).
The portal and manual customer workflow are live. Customer/staff support messages
use in-app unread indicators; activation invitations and owner password setup
email are verified. Outbound support-conversation email and native attachments
remain outside this increment. Operational work does not create purchased
entitlements or qualify the paused commerce phases.


The reusable pre-commerce application core is complete: foundation phase F,
management branding M0, the customer Dashboard, the administrator Dashboard,
administrator user/role management, API foundation A1, and the generic
production-readiness baseline. Both Filament panels provide login, logout,
password reset, required email verification, verified email changes, and profile
management. Public customer registration and verified cart ownership/merge are implemented
and qualified through C3/B2. The customer panel owns the authenticated root `/` dashboard and
root-level auth routes; only administration uses `/admin`.

The product direction is now an API-first commerce and customer-service
platform. `adzbyte-next` owns the primary public product listings, add-to-cart
controls, and compact cart summary. Its **View Cart** action will hand the
customer to `adzbyte-app`, which owns the authoritative catalog, anonymous and
customer carts, registration, full cart, checkout, orders, PayMongo payments,
subscriptions, purchased products, requests, reports, account portal, and
administration. Customers must authenticate before payable order creation, but
may review an anonymous cart first. Anyone may register without a cart handoff.
The customer app may add authenticated product listings later through the same
catalog services.

The versioned API has separate customer, integration, and webhook route files,
stateful Sanctum support, named throttles, stable error envelopes, an
OpenAPI-documented `GET /api/v1/me` identity resource, and a dormant
persistence-backed idempotency boundary. C1.1 adds catalog models, a migration,
and domain guards for dynamic products and price/term versions. C1.2 adds
capability-backed shared catalog actions, locked revision allocation, persistent
retry receipts, and actor/time audit history. C1.3 adds protected administrator
catalog list/create/view/edit pages and explicit revision/lifecycle actions.
C1 public catalog, C2 carts/category replacement/handoff, and C3 verified ownership
are qualified with the real Next consumer in draft PR #6. O1.1/O1.2 immutable
order snapshots and owned checkout are qualified. O1.3 review, communication,
settings and recoverable notifications are qualified at `e27fefa` / CI
39101761212, 397 / 6,374 independently on PHP 8.3.35 SQLite/MySQL. Aggregate O1
is verified; the corrected CI builds assets before both full suites. Payment, subscription, purchased-service
and support-case processing remain pending. The schema is now deployed with the portal release; applying these migrations
does not qualify generic ecommerce for customer transactions.

The approved first commerce approach is a narrow native Laravel vertical slice
for fixed-scope, one-time services charged in PHP through PayMongo Hosted
Checkout v2. Services are fulfilled manually and require administrator fit
approval before payment is enabled. Physical goods, inventory, shipping,
downloads, appointments, variants, promotions, and subscription checkout are
outside the initial slice. Packages are dynamic administrator-managed records;
no initial products or amounts are specified or needed for catalog development.
Tax/receipt, review, payment and fulfillment/support use owner-authorized
configurable starter defaults. Actual merchant tax/document settings, provider
methods and sandbox payment/refund outcomes remain qualification requirements.

Generic readiness includes transactionally locked single-use activation,
after-commit queue dispatch, bounded activation-notification retries, trusted
hosts and response headers, database-aware health, queue signals, production
configuration checks, and an operations/recovery runbook. Commerce development
now builds on that foundation; remaining domain/adaptor work and its applicable
decision dependencies are tracked in the roadmap and runbook.

The current PHP 8.3-compatible portal is deployed through the machine-managed `deploy` branch. Customers use `/` and `/login`; administration uses `/admin`.

## Verified Foundation and Catalog

Development planning is complete. The first target is the Laravel backend,
versioned REST API, and Filament tools for user/product and later commerce
management. The [backend development plan](plans/2026-10-17-backend-development.md)
breaks the master roadmap into dependency-ordered task cards, acceptance gates,
API operation families, and verification requirements. **C1.1 is complete:**
empty/draft product persistence, stable identity, lifecycle guards, immutable
price/term versions, current-version relations, and configuration eligibility.
The [catalog domain specification](plans/2026-10-18-catalog-domain.md) records
the schema, invariants, boundaries, and verification.

**C1.2 is verified on SQLite and MySQL/PHP 8.3 CI under the owner's stronger
completion gate:** shared actions enforce separate capabilities,
transactional lifecycle/content/version changes, immutable actor/time history,
and duplicate-command safety. The [action contract](plans/2026-10-18-catalog-actions.md)
records behavior and concurrency boundaries. **C1.3 is complete:** administrators can create drafts, edit content and
image references, add price/terms versions, and explicitly manage publication,
availability, archival, and restoration. History is read-only, and permissions
remain independent. See the [management contract](plans/2026-10-18-catalog-management.md).
C1.4 and B1 are verified complete at backend `9b4bf7b`: 206 tests / 1,300 assertions independently on SQLite/MySQL in PHP 8.3 CI. The Next.js consumer passes local/browser qualification; its source CI passes. See the [public contract](plans/2026-10-18-public-catalog.md). D01 is resolved; D03's basic controls are
specified; D02 configurable starter tax/receipt rules are implemented; merchant qualification remains a launch gate. The
[decision register](decisions/commerce-development.md) and
[development handoff](DEVELOPMENT-HANDOFF.md) preserve the remaining rules and
resume checkpoint. Frontend integration and production release remain separate.

## Customer onboarding check — 2026-10-23

The combined admin create → assign project/service → real queue worker/isolated
SMTP receipt → captured invitation link → activation → fresh login → owned pages
passes **1 test / 77 assertions**. Focused onboarding/activation/ownership tests
pass **32 / 329**. The owner-approved production QA customer (user 2) and labeled
sample project/service now exist. Actual invitation receipt in Gmail INBOX is
confirmed at **21:27:04 PHT**; jobs/failures are zero. Owner activation is now
confirmed live. The authenticated customer Dashboard, project/service lists and
both detail pages display the two correct assignments, including after reload.
The owner completed a fresh password-form sign-in; the resulting customer session
survives reload and opens both correct detail pages. The live onboarding journey
is verified end to end; the QA customer remains signed in for manual testing.
Test-only source `a6a790b` also passes [CI 39960905290](https://github.com/adzadzadz/adzbyte-app/actions/runs/39960905290):
**678 / 8,930 per database**, PHP 8.3.36 SQLite/MySQL, all gates pass.
See [onboarding evidence](evidence/2026-10-23-customer-portal/polish/customer-onboarding-check.md).

## Live linked conversation check — 2026-10-23

Owner-authorized QA report submitted from the project stayed inline and retained
its correct work link. One public admin reply, posted through the shared staff
action as the existing owner, is visible to the QA customer on both the project
and Requests & reports; the expanded Requests thread survives reload. One report,
one public reply, no private notes/duplicates, and correct audit/read state are
confirmed. No application fix or deployment was needed. See [live conversation evidence](evidence/2026-10-23-customer-portal/polish/linked-report-live-check.md).

## CI/CD automation — deployed and verified, 2026-10-23

Owner requests releases without repeated hPanel/SSH work. Reviewed main-only
GitHub release now prepares restricted SSH deployment, request/cron drain,
encrypted backup, locked fast-forward, Laravel setup and end-to-end health checks.
16 deployment safety tests and full local 678 / 8,930 pass. Initial source CI
39970852391 passes both database suites and all gates. Followup source `3efb95a`
adds isolated SSH identity, a short locked worker wrapper, read-only connection
check and compiled-secret permissions. Final source `7928aa9` passes
[CI 39973252724](https://github.com/adzadzadz/adzbyte-app/actions/runs/39973252724):
678 / 8,930 per database, all 16 safety tests, audits/build/cache gates. Independent review
approves the final implementation. One-time main-only production environment,
restricted key/pin, locked cron cutover and Hostinger auto-pull-off are configured.
GitHub-to-Hostinger preflight 39972909499 passes; readiness is `true`. The first
explicitly authorized release 39975941196 now passes end to end. Production is
`621e2c4`; customer data and environment/key are preserved. See [CI/CD evidence](evidence/2026-10-23-cicd/qualification.md).

## Up Next

**Customer onboarding:** account lifecycle fixes are independently reviewed and
deployed; release 39947378628 passes 677 tests / 8,853 assertions per database.
The owner has signed in and completed verification, and live admin pages work.
Next operational use is creating customers and assigning their projects/services.
Ecommerce O2.3c and dependent commerce work stay paused unless the owner explicitly
reprioritizes them.

### Historical commerce checkpoints (paused)

**Independent Laravel checkup:** reviewer reproduced and approved corrections
for private terms exposure, activation email and managed-user authorization races,
and latent parameterized-route idempotency replay. Five transaction retry copies
now share one native-backed helper. Full local PHP 8.5.11 SQLite **401 / 6,427**,
focused actual HTTP metadata/privacy proof, formatting, metadata and build pass.
Source `f128d5c` passes [CI 39103121139](https://github.com/adzadzadz/adzbyte-app/actions/runs/39103121139):
**401 / 6,427 per engine**, PHP 8.3.35 SQLite/MySQL 8.0.46, both audits zero,
formatting/metadata/build/production caches passed. The review gate is cleared. See the
[review and qualification record](evidence/2026-10-18-laravel-review/qualification.md).

**O2.1 — verified complete at `8f13521`.** [CI 39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120) passes **436 / 6,688 per engine**, PHP 8.3.35 SQLite/MySQL8.0.46, audits/metadata/format/build/caches. Independent review, actual QRPh test creation/retrieval/replay/expiry, real HTTP/OpenAPI, schema/concurrency and desktop/320px native UI pass. [Evidence](evidence/2026-10-18-payments/qualification.md). **O2.2 verified complete at `7560514`.** [CI 39116369733](https://github.com/adzadzadz/adzbyte-app/actions/runs/39116369733) passes **477 / 6,908 per engine**, PHP 8.3.35 SQLite/MySQL8.0.46, audits/metadata/format/build/caches. Actual signed QRPh test payment/callback, provider delivery Success, duplicate safety, native desktop/320px and independent review pass. See [webhook evidence](evidence/2026-10-18-payments/webhook-qualification.md). **O2.3a verified complete at`28a0f86` / [CI39123794458](https://github.com/adzadzadz/adzbyte-app/actions/runs/39123794458): 505 /7,164 per PHP 8.3.35 SQLite/MySQL8.0.46 engine; all source gates pass.** shared policy-backed Admin/REST reads and audited reconciliation recover the preserved actual signed test payment with one settlement/resolution and unchanged original receipt. Final local505 /7,164 and native desktop/320px pass; independent review corrections pass. [Evidence](evidence/2026-10-18-payments/reconciliation-qualification.md). **O2.3b qualified at46614f8 / [CI39191936629](https://github.com/adzadzadz/adzbyte-app/actions/runs/39191936629): 539 /7,537 per PHP 8.3.35 SQLite/MySQL8.0.46 engine; all source gates pass.** Actual test unknown-ID recovery, verified expiry/customer cancellation, native1470/320, real HTTP/OpenAPI, schema/races and independent approval pass. [Closure evidence](evidence/2026-10-18-payments/closure-qualification.md). **O2.3c is partial and blocked on sandbox refund qualification:** QRPh-specific test refund failed422 without a claim link, generic API rejects QRPh, and test wallet inventory is empty. No successful refund/claim contract or C implementation is claimed. [Blocker and support-ready note](evidence/2026-10-18-payments/refund-qualification-blocker.md). O2 aggregate and dependent O3/S2/R1/Q remain incomplete.

Historical O1 evidence:
D06/D07 configurable defaults are recorded. O1 is verified complete at
`e27fefaa520e3e9407b3046f285fefe551b8dcb3` /
[CI 39101761212](https://github.com/adzadzadz/adzbyte-app/actions/runs/39101761212):
**397 tests / 6,374 assertions on each PHP 8.3.35 SQLite/MySQL 8.0.46 job**,
zero audit findings, builds/formatting/metadata/production caches passed.
Fifteen actual built Next-to-checkout/Admin/customer browser checks pass at
1440/320px. Actual HTTP/OpenAPI/native form and database-queue/SMTP
success/three-attempt failure/recovery pass. See
[qualification](evidence/2026-10-18-order-review/qualification.md).
The initial native HTTP CI failure was fixed by building Vite assets before
both suites; no gates were bypassed. Private fixtures/processes are removed.
O2.3 still requires operational/refund proof; O2.2 actual callback/payment passes. O3/S2 refund/access defaults are now authorized; support attachments are deferred in favor of customer-shared Google Drive links. Actual launch merchant/legal settings remain later qualification inputs. No production or existing database change occurred.

O1.2 is verified at `538e3ca` / [CI 39098787766](https://github.com/adzadzadz/adzbyte-app/actions/runs/39098787766):
364 tests / 6,019 assertions independently on PHP 8.3.35 SQLite/MySQL;
audits, formatting, metadata, build and caches pass. Actual HTTP/OpenAPI and
nine built Next-to-checkout desktop/320px checks pass. See
[qualification](evidence/2026-10-18-checkout/qualification.md).
O1.1 remains verified at `55cbb94` / CI 39097303894. Aggregate O1 is now qualified by the review evidence above. C3 identity/B2 are verified complete at backend
`40ae2b7` / [CI 39095546942](https://github.com/adzadzadz/adzbyte-app/actions/runs/39095546942):
331 tests / 5,680 assertions each on PHP 8.3.35 SQLite/MySQL 8.0.46; all source
checks pass. Next `52148d3` / [CI 39096289880](https://github.com/adzadzadz/adzbyte-next/actions/runs/39096289880)
passes 144 tests/31 files/type/lint/build/audit under Node 22.23.3. The actual
built two-app registration/real SMTP activation/login/merge/reset/new-browser
journey passes 15 checks at desktop/320px with no unexpected errors or overflow.
See [qualification](evidence/2026-10-18-ownership/qualification.md). Private QA
fixtures and processes are removed; no deployment or existing DB migration.
D02/D05/D08 now use owner-authorized configurable defaults; see the
[decision register](decisions/commerce-development.md). Admin approval favors
clarification/conversation; no time-based cart expiry. O1 enforces verified ownership before order creation. Payments, fulfillment/support and launch remain
pending; D06/D07 configurable defaults are authorized; test integration is authorized and O2.1 is qualified. Frontend remains
in draft PR #6, with original frontend changes preserved.

The owner-authorized dependency remediation is complete. Source `a27f834`
passed PHP 8.3 CI, including both audits, all 109 tests, production build, and
cache checks. No audit thresholds or ignores were changed. C1.2 source
`cea2199` now passes [PHP 8.3 CI](https://github.com/adzadzadz/adzbyte-app/actions/runs/39050287698)
with 154 tests / 660 assertions, both audits, build, and cache gates.
C1.3 source `7d43b13` passes both database CI jobs; C1.4/B1 and real Next.js source qualification now pass at the revisions recorded below.

## Public Catalog and Real Consumer — 2026-10-18

C1 development and B1 are verified at the backend and frontend revisions below.
Frontend source remains in a draft PR; source promotion/deployment is separate.
At that historical checkpoint, C2 onward were incomplete. Current progress is recorded above.

- C1.4/B1 backend: `9b4bf7bac89c95ccc7929dd44b01e66444a93d0c` passes
  [CI 39055675151](https://github.com/adzadzadz/adzbyte-app/actions/runs/39055675151):
  **206 / 1,300 on each database**, PHP 8.3.35, both dependency audits, formatting,
  metadata, build and cache gates. The public API is stateless, read-only,
  allowlisted, throttled and revalidated through ETags; no stale payload cache.
- MySQL qualification caught the latest-empty-terms availability predicate;
  native JSON entry counting fixes it. Real browser setup caught an inherited
  database connection in the HTTP harness; the target and array cache/session
  settings now apply before application boot, with a regression assertion.
- Actual Next.js source `805c003` on isolated `codex/public-catalog` consumes
  the real backend at `/shop` and `/shop/{public_id}`. [Draft PR #6](https://github.com/adzadzadz/adzbyte-next/pull/6)
  preserves the existing frontend checkout and its two unpublished commits.
  Frontend [CI 39056186126](https://github.com/adzadzadz/adzbyte-next/actions/runs/39056186126) passes; no deploy-triggering develop push was made.
- Frontend local proof: **129 tests / 29 files**, type check, lint, production
  build, and **0 dependency audit findings**. Inherited critical dependencies
  were patched, and a tested scoped glob override removes an unfixed lint chain.
  Runtime: local Node 26.11.1, target CI Node 22. The PR documents the override,
  observer-mock migration and tested comment-identity compatibility correction.
- Real Chrome desktop/mobile proves list/detail/pagination, safe text/media,
  exact price, content/price updates, unpublish/draft/archive denial, empty state,
  upstream outage and retry, and keyboard focus/Enter. No unexpected console or
  page errors; no horizontal overflow at 1440/320px. Evidence is in the frontend
  PR's `docs/commerce/evidence/2026-10-18`.
- Disposable SQLite and all browser QA scripts/credentials are removed; ports
  8814/8815 are stopped. Existing application databases were not migrated.
  No production catalog records, release, provider payment or deployment changed.

## Latest Catalog Management Verification — 2026-10-18

- Added four `/admin/products` routes for list, draft creation, view, and content
  editing. Named actions call CatalogActions for all mutations; no direct
  resource CRUD, deletion, public API, or purchase behavior was introduced.
- Form saves and modal commands retain locked UUIDs for lost-response retries;
  conflicting reuse produces visible errors. Create, edit, price, and terms
  replay cases, revoked permissions, view-only/create-only/price-only staff,
  direct history access, injection, and escaped markup are covered.
- PHP price strings convert and display without floating-point rounding,
  including PHP_INT_MAX minor units. Image references accept HTTPS or safe
  `/images/` paths; structured media and terms documents are preserved.
  Textual scope/deliverables/obligations/fulfillment/access descriptions create
  new terms versions without inventing executable entitlement rules.
- Shield exposes exactly the 11 catalog capabilities, without default grants
  or destructive Product permissions. Price/terms/change tables are read-only;
  history components reauthorize on mount and hydration.
- **Local PHP 8.5.11:** `AdminCatalogManagementTest` 25 tests / 315 assertions;
  full `composer test -- --compact` **183 tests / 1,018 assertions**. Full Pint,
  strict Composer validation, Vite build, route/middleware inspection, and
  whitespace checks pass. Source `7d43b13d8161ed83b0abdde6438412de9440256e`
  passed [CI run 39053801945](https://github.com/adzadzadz/adzbyte-app/actions/runs/39053801945):
  PHP 8.3.35, **183 tests / 1,018 assertions separately on SQLite and MySQL**,
  formatting, Composer validation, both audits (0 findings), frontend build,
  and Laravel production-cache generation.
- Real Chrome workflows passed at desktop 1440px and mobile 320px: draft create,
  edit, price/terms, publish/enable, archive/restore, read-only history, inline
  price validation/correction, view-only denial, role controls, and Escape/focus
  restoration. Checked layouts have no document overflow; no unexpected console
  or page errors. [Browser results](evidence/2026-10-18-catalog-management/browser-result.json)
  and [screenshots](plans/2026-10-18-catalog-management.md#browser-evidence) are saved.
- The browser used a disposable SQLite database with database sessions/cache,
  mail array transport, and synthetic admin/viewer fixtures. Its server,
  database, credentials, and temporary scripts were removed. A QA-only router
  bypassed an old Vite hot marker to use compiled assets, preserving that
  existing file. No existing database, secrets, dependencies, release, or
  production environment changed. C1.3's applicable development gates now pass;
  public REST/cache contracts and integrated C1/B1 acceptance remain pending.

## Latest Catalog Action Verification — 2026-10-18

The owner now requires every phase/card to pass all applicable gates before
completion or dependent work. [Verification policy](verification.md) records
the checks and evidence format. A new MySQL CI job and guarded test configuration
exercise the full suite, real MySQL races, and repeatable migration reversal.
Local SQLite: 158 tests / 703 assertions pass on PHP 8.5.11; Pint, Composer
validation, and whitespace checks pass. Corrected source `6697e81` passed
[CI run 39051237331](https://github.com/adzadzadz/adzbyte-app/actions/runs/39051237331):
**158 tests / 703 assertions independently on SQLite and MySQL**, both on
PHP 8.3.35; formatting, audits (0 findings), frontend build, and caches pass.
This closes C1.1/C1.2's MySQL gap. The new harness initially copied a connection
name incorrectly; explicit names and target assertions resolved that isolated
test failure before qualification. Populated catalog upgrades preserve existing
products, price/terms history, and users. See [evidence](verification.md#current-catalog-qualification).
Earlier 154-test source qualification below remains valid historical evidence,
but did not test MySQL. Existing local/production databases remain untouched.

- Added `ProductCapability`, convention-discovered `ProductPolicy`, shared
  `CatalogActions`, append-only `ProductChange`, and the audit migration.
  Role seeding defines 11 capabilities without granting regular roles or
  creating products. No catalog screen, REST route, or API contract changed.
- Every mutation requires an actor and UUID operation ID. Same-command retries
  replay their original result identity; conflicting reuse fails; authorization
  is rechecked. Content cannot inject lifecycle, identity, or revision fields.
- Parent locks serialize lifecycle and revision allocation; existing unique
  constraints remain the revision boundary. Transactions include the audit
  receipt and roll back together. Concurrency failures retry with bounded
  backoff only after rollback; nested transactions leave recovery to the caller.
- Unpublish/archive disable availability; restore returns to draft. New zero
  prices or empty terms disable an offer and retain earlier revisions.
- Full `composer test -- --compact`: **154 tests / 660 assertions passed** on
  local PHP 8.5.11. Includes 45 new cases with independent-process races for
  distinct price/terms revisions and identical create/price/terms commands.
  Four workers use a common start barrier and isolated file-backed SQLite;
  initial tests exposed immediate lock-retry exhaustion, fixed with backoff.
- Full Pint and strict Composer validation pass. Clean migration apply,
  audit-only rollback retaining prior tables, and reapply passed on disposable
  SQLite, with no products seeded; all temporary databases were removed.
- Source `cea219978d31b3dae519306820653e2c63277610` was pushed to `main`.
  [CI run 39050287698](https://github.com/adzadzadz/adzbyte-app/actions/runs/39050287698)
  passed on PHP 8.3.35: 154 tests / 660 assertions, formatting, Composer
  validation, both dependency audits (0 findings), production frontend build,
  and Laravel production-cache generation. No audit thresholds changed.
- No existing local/production database, credentials, dependencies, or frontend
  assets changed. MySQL qualification was unverified at that original source
  checkpoint; source `6697e81` above now closes it.
  No release or production promotion ran.

## Latest Dependency Verification — 2026-10-18

| Audited package | Previous | Updated |
|---|---|---|
| `filament/filament` and synchronized Filament packages | 5.7.5 | 5.10.2 |
| `laravel/framework` | 13.23.0 | 13.37.0 |
| `league/commonmark` | 2.9.0 | 2.10.3 |
| `league/flysystem` | 3.35.2 | 3.36.0 |
| `symfony/http-foundation` | 7.4.15 | 7.4.21 |
| `concurrently` | 9.2.4 | 9.2.5 |
| `nanoid` | 3.3.17 | 3.3.20 |
| `shell-quote` | 1.9.0 | 1.12.0 |
| `source-map-js` | 1.2.1 | 1.2.2 |

- Targeted Composer resolution updated 16 packages and added 2 required
  dependencies; Livewire moved to 4.4.7 and Laravel Prompts to 0.3.26.
  PHP resolution remains pinned to 8.3.0. Root manifests and CI are unchanged;
  no major-version upgrade or advisory suppression was introduced.
- Local Composer audit: 0 advisories / 0 abandoned packages. The previously
  skipped npm audit exposed 4 affected packages, now fixed compatibly; npm
  audit reports 0 vulnerabilities. Both lock files are updated.
- Filament's upgrade script regenerated published CSS/JS/fonts. Replaced seven
  unreferenced old font files with the seven referenced new generated files.
  Upstream assets remain unmodified; `git diff --check` reports three upstream
  template-string whitespace lines whose files were verified byte-identical to
  vendor distributions. Authored/lock/CSS/font-reference whitespace checks pass.
- Local full suite remains 109 tests / 416 assertions on PHP 8.5.11. Pint,
  strict Composer validation, Vite build, isolated Laravel production-cache
  generation, and cached login-route discovery pass. Temporary test caches
  were cleared. No existing database was migrated or production changed.
- Source `a27f834bb05ee4d2ca5b3fd06da75f3b98a19475` was pushed to `main`.
  [CI run 39048996685](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048996685)
  passed on PHP 8.3.35: 109 tests / 416 assertions, Composer validation, both
  dependency audits (0 findings), formatting, Vite build, and Laravel production
  caches. Earlier failed-run details below are historical and superseded by
  this verified remediation. No release or production promotion ran.

## Latest Implementation Verification — 2026-10-18

- `CatalogDomainTest`: 38 passed / 71 assertions; test-first failures captured
  before implementation and strict numeric-validation corrections.
- Full `composer test -- --compact`: 109 passed / 416 assertions on local PHP
  8.5.11. PHP 8.3 runtime verification belongs to the existing CI workflow;
  do not infer it from local results.
- `vendor/bin/pint --test` and `composer validate --strict` passed.
- Disposable SQLite clean apply → catalog-only rollback → reapply passed,
  retaining prior tables and creating no packages; temporary database removed.
  MySQL runtime migration verification is not claimed.
- No existing local database, production environment, secrets, routes, panel
  behavior, or public API contract changed. The source migration awaits a
  future explicitly authorized release. No release workflow ran.
- Source commit `284dcbb` was pushed to `main` and the remote head verified.
  [CI run 39048141348](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048141348)
  failed at `Audit PHP dependencies`: 11 advisories across `filament/filament`,
  `laravel/framework`, `league/commonmark`, `league/flysystem`, and
  `symfony/http-foundation`. Composer files and CI configuration are unchanged
  by C1.1. PHP dependencies installed, but formatting, PHP tests, frontend
  checks/build, and cache verification were skipped after the audit failed.
  PHP 8.3 test success was therefore unverified for that original lock file.
  The authorized dependency follow-up above now resolves the audit blocker
  and verifies the same catalog behavior under PHP 8.3; no audit exceptions used.

## Prior Planning Verification — 2026-10-17

- Repository inspection at `e08f6b0` confirmed existing user/auth/API foundations
  and the absence of commerce models, migrations, and business endpoints.
- The backend plan, decision register, and handoff were prepared without
  application, database, credential, or production changes.
- The owner clarified dynamic packages; the earlier fixed launch-catalog/price
  gate was removed from current plans and the next card is now C1.1.
- Local Markdown links, task/decision references, document consistency, and
  whitespace checks passed; see the development handoff for the checkpoint.
- Runtime tests, live CI, and production were not rechecked for this
  documentation-only session. Earlier verification entries below are historical
  evidence, not results of the latest planning session.

## F2 Verification

- Both panels expose Filament login, logout, password reset, email verification, verified email-change, and profile routes; the customer routes are rooted at `/`, and neither panel exposes registration.
- Trusted provisional-customer creation normalizes identity data, assigns only `customer`, creates no usable public password, and rejects existing emails with a generic authentication-required outcome.
- Customer activation is queued by a shared action and uses a signed 24-hour URL, strong password validation, email verification, login/session rotation, role checks, replay protection, and five-attempt-per-minute throttling.
- Framework `Login` and `Verified` events and application customer-provisioned/customer-activated events are covered.
- Confirmed Sanctum stateful origins include `adzbyte.com` and `app.adzbyte.com`.
- Focused F2 tests: 14 passed, 68 assertions.
- Full PHP suite: 25 passed, 91 assertions.
- Configuration caching, Pint, Composer validation, route/middleware inspection, npm dependency audit, and the production asset build passed.

## CI/CD and Branding Plan Verification

- Remote `main` contains the release flow and the PHP 8.3 dependency correction at source commit `12ed0c81d235`.
- GitHub Actions content writes are enabled and the `production` environment exists.
- CI run 2 passed the full verification workflow after Composer was pinned to a PHP 8.3 resolution platform and Symfony packages were resolved to compatible 7.4 releases.
- The authorized release workflow run `30860097717` passed and promoted source commit `12ed0c81d235` to deploy commit `eda1d21cc641`.
- Hostinger pulled the `deploy` branch into `public_html`, installed production dependencies, and reports the deployment completed on PHP 8.3.
- A dedicated production database and mode-`600` `.env` were created without storing credentials in Git. A pre-migration dump is retained outside the web root.
- A dedicated SSH key is authorized with mode-`600` key-file permissions. Both the Laravel scheduler and stop-when-empty database queue worker are configured in hPanel at `* * * * *` and have produced cron output records.
- All five production migrations ran, Laravel production caches were built, the manual scheduler and queue checks passed, and both `jobs` and `failed_jobs` were empty after verification.
- `/up`, `/account/login`, and `/admin/login` each returned HTTP 200 over HTTPS. The only Laravel errors were expected first-boot entries created before the application key and database tables existed; later verification created no new errors.
- Production mail authenticates through the primary Hostinger mailbox and sends application mail from the `notifications@adzbyte.com` alias. Hostinger accepted a Laravel SMTP test message to the primary mailbox without exposing the credential.
- Full PHP tests remained green at 25 tests and 91 assertions; Pint and the production frontend build passed during setup verification.
- The CI, manual promotion, Hostinger post-pull, and explicit-command authorization paths were reviewed; workflow/interface YAML and shell syntax checks passed.
- The release skill passed its validator during creation and has implicit invocation disabled.
- Branding source colors, font weights, logo files, image directories, documentation links, and ownership boundaries were checked against the local `adzbyte-next` source.
- M0 now uses one shared panel configurator and compiled theme, local Fontsource Poppins weights 300–700, three checksum-verified brand assets documented in `resources/brand/assets.json`, forced dark mode, explicit customer/admin identity, and panel-specific density.
- Customer login, administrator login, activation, and both authenticated dashboards were reviewed at desktop and `320px`; no horizontal overflow or browser-console warning remained, and primary auth actions stayed inside the initial mobile viewport.
- Focused branding, routing, authentication, and panel-boundary verification passed with 26 tests and 128 assertions; the full PHP suite passed with 28 tests and 131 assertions, and the production Vite build emitted only local Poppins font assets.
- M1.1 replaces the customer panel's stock account widget with a first-party Home while retaining Filament's panel authorization on every request. Focused dashboard and panel-boundary verification passed with 15 tests and 72 assertions; the full PHP suite passed with 31 tests and 145 assertions, and the production asset build passed.
- M2.1 replaces the administrator panel's stock account widget with a first-party Overview, explicit role context, and configured safeguard labels without inventing operational data. Focused management dashboard verification passed with 18 tests and 83 assertions; the full PHP suite passed with 34 tests and 156 assertions, and the production asset build passed.
- A1.1 adds stateful Sanctum middleware, separate versioned route surfaces, named customer/integration/webhook throttles, stable JSON errors, an API Resource boundary, `GET /api/v1/me`, and a machine-readable OpenAPI 3.1 contract. Nine focused API tests passed with 30 assertions; the full PHP suite passed with 43 tests and 186 assertions, route inspection confirmed the expected middleware stack, and Composer validation passed.
- A1.2 adds a reusable authenticated-mutation middleware with hashed principal/route keys, canonical request and upload fingerprints, atomic cache locks, a unique persistence boundary, completed-response replay, deterministic mismatch and in-progress conflicts, retry-safe server failures, expiry, stale-request recovery, and daily pruning. Ten focused tests passed with 58 assertions; the full PHP suite passed with 53 tests and 244 assertions, migration apply/rollback, schedule and command discovery, Pint, Composer validation, and the production asset build passed. No product mutation route was added.
- M2.2 adds a first-party `/admin/users` list/edit resource and shared managed-user action with explicit policy checks, validation, email re-verification, transactional role assignment, self-demotion protection, and no password, create, or delete surface. Regular administrators can receive narrow identity capabilities but cannot promote staff, edit super administrators, or enter Shield role management; application access roles cannot be renamed or deleted. Eight focused tests passed with 58 assertions and the full PHP suite passed with 61 tests and 302 assertions. Desktop and `320px` browser review found no overflow or console warnings, Users and Roles share one Administration navigation group, and the disposable QA super administrator was removed.
- The pre-domain readiness audit moved activation into a shared transactionally locked action, made queued work dispatch after commit, bounded activation-notification retries below the queue retry window, added queue depth/staleness/failure logging and health commands, made `/up` database-aware, enabled trusted hosts and baseline response security headers, and documented production configuration, queue recovery, migration incidents, backup verification, and credential compromise. The full suite passed with 71 tests and 345 assertions; production cache generation, schedule discovery, shell syntax, Pint, Composer validation, both dependency audits, and the Vite build passed. Current production still returned 403/404 for direct `.env`, Composer, and Laravel-log requests and 200 for `/up`; it was not modified.
- GitHub CI passed for the pushed A1.1 (`7f12d7f`), A1.2 (`efd7756`), and M2.2 (`2e2cc02`) commits. No release workflow ran.
- Repository diffs passed whitespace checks, and no supplied local password, production credential, or private key was found in the committed files.

## F1 Verification

- Installed Filament 5.7.5, Sanctum 4.3.3, Spatie Laravel Permission 8.3.0, and Filament Shield 4.3.1.
- Both panel route sets boot and require authentication.
- Customer, administrator, super-administrator, dual-role, and no-role access boundaries are covered by tests.
- Shield role management is available only to authorized super administrators by default.
- The role seeder is repeatable; local super-administrator creation uses `php artisan shield:super-admin --panel=admin`.
- Migration apply, rollback, re-apply, and seed verification passed on a disposable SQLite database.
- Focused tests: 9 passed, 21 assertions.
- Full PHP suite: 11 passed, 23 assertions.
- Pint, Composer validation, and Composer security audit passed.

## Decisions Already Locked

- `adzbyte-next` owns the primary public product-listing experience,
  add-to-cart controls, and compact cart summary.
- Next.js has no checkout action. **View Cart** hands the customer to a
  short-lived app-owned cart URL.
- `adzbyte-app` owns the authoritative catalog, carts, registration, full cart,
  checkout, orders, payments, subscriptions, purchased products, requests,
  reports, customer account management, administration, and APIs.
- Anyone may register directly in the app; a cart handoff is not required.
- Customers may review an anonymous cart but must authenticate before payable
  order creation or PayMongo authorization.
- The app may show product listings inside the authenticated customer portal in
  a later phase, using the same catalog services and records.
- The [management branding plan](plans/2026-08-04-management-ui-branding.md) adapts the palette, Poppins typography, wordmark, square mark, and context-appropriate media from `adzbyte-next` into a dark-first Filament system; `adzbyte-app` must copy, optimize, and version what it uses without hotlinks or runtime repository coupling.
- Both management panels are authenticated Filament panels in this repository: customers use the root `/` panel and administrators use `/admin`.
- Shop functionality originates in shared Laravel application services and is
  exposed through versioned API contracts needed by Next.js and future clients.
- Every customer-facing shop capability receives a versioned API contract even
  when its initial app page calls the shared service directly.
- Roles begin with `customer`, `administrator`, and `super_admin`.
- `adzbyte-next` is live at `https://adzbyte.com` and `adzbyte-app` will be hosted at `https://app.adzbyte.com`.
- The initial super-administrator identity email is `adzbite@gmail.com`; no password or activation secret is stored in the repository.
- Existing accounts must authenticate before an anonymous cart can be attached,
  without public account-existence disclosure.
- Laravel policies and record ownership remain authoritative across every interface.
- PayMongo is the first payment provider for one-time and subscription billing.
- Payment redirects are informational; only verified, idempotently processed
  PayMongo webhooks confirm financial state or grant purchased access.
- The first commerce implementation is a narrow native Laravel vertical slice,
  not a second hosted store or installed general-purpose commerce platform.
- The first sellable type is a fixed-scope one-time service with line quantity
  one, manual fulfillment, and administrator fit approval before payment.
- First-release checkout prices are stored and charged in PHP through PayMongo
  Hosted Checkout v2; administrators configure exact amounts dynamically.
- No predefined package list is required. Authorized administrators will add,
  edit, publish/unpublish, enable/disable, and archive packages through the
  backend; drafts may be incomplete and historical purchases retain snapshots.
- Physical goods, inventory, shipping, downloads, appointments, variants,
  promotions, and subscription checkout are excluded from the initial slice.
- Monthly care plans and custom project ranges remain enquiry-only until their
  separate billing and fulfillment rules are approved.
- Customers can submit distinct requests and issue reports, communicate through
  customer-visible threads, and manage them from the authenticated app.

## Decisions Needed Later

The [commerce platform plan](plans/2026-10-05-commerce-platform.md) records the
complete decision backlog. There is no commercial-content blocker to C1:
the dynamic package requirement replaces the fixed launch-catalog gate.

Before the cart, checkout, and payment slices, the project must also resolve:

- tax and receipt/invoice treatment before checkout/sales, plus additional
  package-specific/per-customer purchase constraints before enforcement;
- whether a cart may contain more than one distinct service, plus cart expiry
  and authenticated merge rules;
- required project-brief and billing fields, approval authority, correction and
  rejection behavior, approval expiry, and customer notifications;
- enabled PayMongo one-time methods, refunds, cancellation, and dispute rules;

Deferred phases still require:

- PayMongo subscription capability, retries, grace periods, plan changes,
  cancellation timing, and entitlement suspension;
- request/report categories, service targets, attachment rules, notifications,
  and retention.

## Known Issues

- **Account lifecycle fixes deployed 2026-10-23:** first-entry verification and
  panel-scoped password recovery are live at `3bcc292e5931`. Full release gates
  pass 677 tests / 8,853 assertions per engine. Actual verification mail receipt
  and owner verification are confirmed; queues are healthy. See [review/release evidence](evidence/2026-10-23-customer-portal/polish/account-lifecycle-review.md).
- Hostinger's automatic pull reset `.env` permissions to 644; this release restored
  600 and verified contents unchanged. Recheck permissions after future pulls as
  required by the deployment runbook.
- **Password reset delivery repaired 2026-10-23:** owner-approved absolute-path
  cron replacements now run every minute. Actual admin form → automatic worker
  → Gmail INBOX receipt verified at 18:58 PHT; zero queued/failed jobs and good
  queue health. Both admin/customer reset flows pass isolated delivery tests.
  Use the newest reset email in the Gmail thread. See [repair evidence](evidence/2026-10-23-customer-portal/polish/password-reset-check.md).
- Owner login and email verification are complete. Fresh reset and verification
  email receipt in Gmail INBOX are verified; no agent-set password or verification
  bypass was used.
- Local preview is stopped after QA; deployed links are available for owner
  testing. Outbound support-message email/native attachments remain deferred.
- Paused commerce refund, merchant/legal and broader retention qualification
  remains unchanged; this portal release does not close those gates.

## Working Tree Handoff

Current release and next action are recorded at the top. The entries below are
historical checkpoints and do not describe the current production version.

- F1 and F2 remain preserved in prior history.
- Commit `2ecd1c2` adds the explicit-command Hostinger CI/CD and release flow.
- Commit `8643a1a` adds the polished management UI branding plan and synchronized documentation.
- Commit `937fec5` fixes the PHP 8.3 dependency resolution mismatch; GitHub CI run 2 passed.
- The local super-administrator identity is verified; its password is not stored in the repository.
- The session-owned development server was stopped.
- The last recorded production release promoted source commit `12ed0c81d235` to generated `deploy` commit `eda1d21cc641`; current `main` includes later unreleased work. Production state was not rechecked during planning on 2026-10-17.
- The first production database, `.env`, SSH path, pre-migration backup, migrations, scheduler cron, and queue cron were created and verified during the authorized release window.
- Hostinger SMTP is active for `notifications@adzbyte.com`; the protected production `.env` holds the mailbox credential and all temporary credential files were removed after verification.
- No database seeders ran and no production super-administrator was created.
- The local M0 slice moves the customer panel to `/`, removes the Laravel placeholder and Filament promotional widget, and keeps `/admin` unchanged; two disposable visual-QA customers were removed after verification.
- The local M1.1 slice adds the first-party customer Home and removes the remaining stock customer account widget without claiming the still-planned purchase and order dashboard is complete. A requested verified local-only `customer` demo account exists at `demo@adzbyte.com`; its password is not stored in the repository.
- The local M2.1 slice adds the first-party administrator Overview and removes the remaining stock administrator account widget without claiming the still-planned operational dashboard or SLA queue is complete. Its disposable visual-QA administrator was removed after verification.
- The local A1.1 slice exposes only current-customer identity. The integration and webhook files intentionally contain no routes until their authentication/signature and business behavior arrive together; no anonymous, product, order, checkout, or provider endpoint was added.
- The local A1.2 slice registers but does not attach the `idempotent` middleware. Its storage migration and daily prune command take effect only after a future explicitly authorized release; no production migration or scheduler change was made.
- The local M2.2 slice adds only authenticated administrator management. It does not create, change, or delete the local demo customer, and it does not modify production users, roles, or permissions.
- The local readiness slice changes no product catalog, order, payment, collaboration, upload, hosting, or fulfillment behavior. Its remaining-task classification is documented in `docs/operations/core-readiness.md`; the empty leftover `.superdesign` directory was removed and no Superdesign artifact is used.
- The documented first commerce approach changes no application or production
  behavior. It resolves the initial product-type, fulfillment, platform, and
  payment-flow direction while leaving the listed commercial inputs as the C1
  entry gate in that historical plan. The subsequent clarification below
  supersedes the predefined-package requirement.
- The 2026-10-17 backend plan adds task-level acceptance and API/access coverage
  without implementing commerce. The subsequent dynamic-package clarification
  supersedes P0: resume from `docs/DEVELOPMENT-HANDOFF.md` at C1.1 without waiting
  for real package names, prices, or a launch catalog.
- C1.1 was implemented and locally verified on 2026-10-18. Its migration was
  tested only in isolated databases. Resume at C1.2 using the current handoff;
  admin management and public catalog APIs are still pending.
