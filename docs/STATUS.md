# Project Status

**Last updated:** 2026-10-18 (C1.2 locally verified; PHP 8.3 CI pending)

## Current Stage

The reusable pre-commerce application core is complete: foundation phase F,
management branding M0, the customer Home, the administrator Overview,
administrator user/role management, API foundation A1, and the generic
production-readiness baseline. Both Filament panels provide login, logout,
password reset, required email verification, verified email changes, and profile
management. Public registration is now a product requirement but is not yet
implemented. The customer panel owns the authenticated root `/` dashboard and
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
retry receipts, and actor/time audit history. No catalog
management screens or public catalog routes exist yet. Cart, checkout, order,
payment, subscription, purchased-product, request, and report records/routes
remain unimplemented. Catalog/audit migrations have only run in disposable test
databases, not the development or production database.

The approved first commerce approach is a narrow native Laravel vertical slice
for fixed-scope, one-time services charged in PHP through PayMongo Hosted
Checkout v2. Services are fulfilled manually and require administrator fit
approval before payment is enabled. Physical goods, inventory, shipping,
downloads, appointments, variants, promotions, and subscription checkout are
outside the initial slice. Packages are dynamic administrator-managed records;
no initial products or amounts are specified or needed for catalog development.
Tax/receipt treatment, brief and approval rules, enabled payment methods, and
refund/cancellation behavior still require decisions before the purchasing
slices that depend on them.

Generic readiness includes transactionally locked single-use activation,
after-commit queue dispatch, bounded activation-notification retries, trusted
hosts and response headers, database-aware health, queue signals, production
configuration checks, and an operations/recovery runbook. Commerce development
now builds on that foundation; remaining domain/adaptor work and its applicable
decision dependencies are tracked in the roadmap and runbook.

The previously released PHP 8.3-compatible application remains deployed to Hostinger through the machine-managed `deploy` branch. M0 and the root-route change are verified on `main` but require a future explicitly authorized release before production changes from `/account` to `/`.

## In Progress

Development planning is complete. The first target is the Laravel backend,
versioned REST API, and Filament tools for user/product and later commerce
management. The [backend development plan](plans/2026-10-17-backend-development.md)
breaks the master roadmap into dependency-ordered task cards, acceptance gates,
API operation families, and verification requirements. **C1.1 is complete:**
empty/draft product persistence, stable identity, lifecycle guards, immutable
price/term versions, current-version relations, and configuration eligibility.
The [catalog domain specification](plans/2026-10-18-catalog-domain.md) records
the schema, invariants, boundaries, and verification.

**C1.2 is locally complete:** shared actions enforce separate capabilities,
transactional lifecycle/content/version changes, immutable actor/time history,
and duplicate-command safety. The [action contract](plans/2026-10-18-catalog-actions.md)
records behavior and concurrency boundaries. The next card is **C1.3: Filament
catalog management**. Administrator screens (C1.3), public REST/catalog caching (C1.4), and the B1
backend milestone remain pending. D01 is resolved; D03's basic controls are
specified; D02 tax/receipt policy remains deferred to checkout/sales. The
[decision register](decisions/commerce-development.md) and
[development handoff](DEVELOPMENT-HANDOFF.md) preserve the remaining rules and
resume checkpoint. Frontend integration and production release remain separate.

## Up Next

**Implement C1.3: a capability-backed Filament catalog resource using
CatalogActions for draft creation, content edits, lifecycle/availability,
and price/terms revisions. Keep operation UUIDs stable across a failed request
retry, expose explicit capabilities in role management, preserve historical
versions, and test authorized/denied panel workflows. See the
[backend task cards](plans/2026-10-17-backend-development.md).**

The owner-authorized dependency remediation is complete. Source `a27f834`
passed PHP 8.3 CI, including both audits, all 109 tests, production build, and
cache checks. No audit thresholds or ignores were changed. C1.2 now passes local
verification; its own PHP 8.3 CI qualification is pending source delivery.
C1.3/C1.4 and B1 remain pending.

## Latest Catalog Action Verification — 2026-10-18

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
- No existing local/production database, credentials, dependencies, or frontend
  assets changed. PHP 8.3 CI and MySQL runtime qualification are separate;
  latest source CI is pending. No release or production promotion ran.

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

- The earlier dependency-audit CI blocker is resolved by the verified follow-up
  above. This source/CI verification does not imply deployment of the fixes to
  the older production release.
- The production super-administrator has not been bootstrapped. Its identity remains recorded, but password provisioning is a separate deliberate operation.
- Production still runs the previous release with `/account`; the new root customer route and M0 branding require an explicitly authorized release before production smoke checks change to `/login` and `/`.

## Working Tree Handoff

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
