# Adzbyte Commerce Platform Implementation Roadmap

| Field | Value |
|---|---|
| Status | Customer portal priority; ecommerce development paused at O2.3c refund qualification |
| Product source of truth | `docs/plans/2026-10-05-commerce-platform.md` |
| First commerce approach | `docs/plans/2026-10-05-first-commerce-release.md` |
| Progress tracker | `docs/STATUS.md` |
| Backend task cards | `docs/plans/2026-10-17-backend-development.md` |
| Decision gates | `docs/decisions/commerce-development.md` |
| Resume checkpoint | `docs/DEVELOPMENT-HANDOFF.md` |
| Application | Laravel 13 / PHP 8.3 |
| Management UI | Filament 5 customer panel at `/` and administrator panel at `/admin` |
| API prefix | `/api/v1` |

## Current priority — 2026-10-23

The owner paused ecommerce development and prioritized the customer portal,
particularly projects and services **manually assigned by admins**. This
independent increment provides operational tracking and support without waiting
for checkout/purchased-entitlement qualification. Existing ecommerce screens
remain accessible with production Under development notices. The [portal plan](2026-10-23-customer-portal.md)
and [qualification record](../evidence/2026-10-23-customer-portal/qualification.md)
define its own acceptance gate. Commerce O2.3c/O3/S2 and broader R1/Q checkboxes
remain incomplete and paused, rather than being relabeled as portal completion.

## Delivery Rules

- The first target is backend services, versioned REST contracts, and usable
  Filament management. Follow the [backend task cards](2026-10-17-backend-development.md)
  for the detailed C1 → C2 → C3 → O1 → O2 → O3/S2 → R1 sequence.
- The owner superseded P0's predefined-package gate: build C1 against dynamic
  managed records and an initially empty catalog. Tax/receipt and later
  commercial decisions block purchasing behavior that needs them. Keep backend readiness, actual
  Next.js integration, and production promotion separately evidenced.
- Implement in dependency order and deliver narrow end-to-end slices.
- Keep Filament panels, API controllers, jobs, and webhooks on shared application
  services and Laravel policies.
- Add ownership and capability tests with each protected record.
- Store money in minor units with an explicit currency and snapshot accepted
  prices and terms on orders.
- Use explicit states for carts, orders, payments, fulfillment, subscriptions,
  invoices, entitlements, requests, and reports.
- Make retry-prone mutations and provider events idempotent.
- Update the product plan for durable decisions and `docs/STATUS.md` only after
  implementation is verified.

## Phase F — Existing Foundation

### F1. Authentication, roles, and panels

- [x] Shared user model and Laravel web authentication.
- [x] Customer and administrator Filament panels.
- [x] Login, logout, password reset, email verification, verified profile
  changes, and trusted customer activation.
- [x] Customer, administrator, and super-administrator roles with panel access
  boundaries.
- [x] Policy-backed administrator user and role management.
- [x] Enable safe public customer registration without accepting public roles or
  weakening verification and throttling.

### F2. API and operational baseline

- [x] Versioned customer, integration, and webhook route groups.
- [x] Stateful Sanctum support, named throttles, stable JSON errors, API
  Resources, and `GET /api/v1/me`.
- [x] Persistence-backed idempotency boundary for authenticated mutations.
- [x] Queue, health, trusted-host, security-header, production configuration,
  and recovery baseline.
- [x] OpenAPI contract and contract tests for implemented endpoints.

## Phase C — Commerce Foundation

### C1. Catalog and pricing

- [x] Resolve the first-release product type and fulfillment boundary: manually
  fulfilled one-time services with administrator fit approval before payment.
- [x] Add C1.1 product/price persistence, media references, publication state,
  and basic configuration-availability guards (no external adapter yet).
- [x] Support an empty catalog and incomplete drafts; no predefined package
  names/prices or production seeds. Unset price must not mean free.
- [x] Add immutable versioned terms/entitlement-document storage; actual
  purchased-service interpretation and snapshots remain in later slices.
- [x] Add C1.2 capability-backed shared catalog actions, locked revision
  allocation, duplicate-command receipts, and append-only actor/time history.
- [x] Add administrator catalog management through shared services and policies.
- [x] Allow authorized add/edit, publish/unpublish, availability changes, and
  archival without code/deploy changes, preserving referenced price/term history.
- [x] Add safe, cacheable storefront catalog and product-detail APIs.
- [x] Test draft/unpublished visibility, price snapshots, and unauthorized
  catalog mutations.

C1.1 proof is recorded in the [domain specification](2026-10-18-catalog-domain.md)
and STATUS: 38 focused tests / 71 assertions, full suite 109 / 416, and isolated
SQLite migration apply/rollback/reapply. C1.2 is verified with 154 tests /
660 assertions, including independent-process concurrency and retry coverage;
see the [action contract](2026-10-18-catalog-actions.md). Audit-migration
apply/rollback/reapply, Pint, and Composer validation pass. Source `cea2199`
passes [CI run 39050287698](https://github.com/adzadzadz/adzbyte-app/actions/runs/39050287698)
on PHP 8.3.35 with the same counts, both audits, build, and cache gates.
C1.4 and B1 remain incomplete.
The C1.3 implementation is now locally/browser verified (183 tests / 1,018
assertions, focused 25 / 315, build/format/metadata gates, real desktop/mobile
workflows), with [management evidence](2026-10-18-catalog-management.md).
Source `7d43b13` passes [CI run 39053801945](https://github.com/adzadzadz/adzbyte-app/actions/runs/39053801945)
on PHP 8.3.35 with 183 / 1,018 independently on SQLite and MySQL, both audits,
build, formatting, and cache gates. C1.3 is qualified; C1.4 is next.
The owner's expanded [phase gates](../verification.md#current-catalog-qualification)
are now qualified for C1.1/C1.2 at source `6697e81`:
[CI run 39051237331](https://github.com/adzadzadz/adzbyte-app/actions/runs/39051237331)
passes 158 tests / 703 assertions separately on SQLite and MySQL 8.0 under
PHP 8.3.35, including populated migration upgrades and real process races.
After the authorized dependency update, [CI run 39048996685](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048996685)
at source `a27f834` passes the full suite on PHP 8.3.35, both dependency audits,
build, and cache gates. C1.1 is qualified; production is unchanged.

C1.4/B1 backend qualification: source `9b4bf7b`, [CI 39055675151](https://github.com/adzadzadz/adzbyte-app/actions/runs/39055675151),
206 / 1,300 on SQLite and MySQL/PHP 8.3. Immutable catalog price/terms versions
remain intact; accepted order snapshots belong to O1. Actual Next.js consumer
source `805c003` is locally/browser verified in [draft PR #6](https://github.com/adzadzadz/adzbyte-next/pull/6),
with [source CI passing](https://github.com/adzadzadz/adzbyte-next/actions/runs/39056186126). See the [public contract](2026-10-18-public-catalog.md).
C2 follows the verified [category prerequisite](2026-10-18-category-cart-controls.md), backend 4859e4e / frontend f202534 with both source CI gates green. Owner-approved D04 rules are recorded; a new selection replaces the previous service in that restricted category and notifies the customer.

**Gate:** Laravel can publish a product, `adzbyte-next` can render only its safe
public representation, and an administrator can change future catalog state
without altering an existing price or entitlement snapshot.

The first slice uses no variants, inventory, shipping, downloads, appointments,
promotions, subscriptions, or line quantities greater than one. Package details
and amounts are dynamically configured through administration. No initial
catalog is needed to implement C1. Tax/receipt and purchase-policy decisions
remain required before the checkout/sales behavior that depends on them.

### C2. Anonymous cart and app handoff

- [x] Add anonymous and authenticated carts and cart items (C2.1; attachment/merge is C3).
- [x] Implement quantity-one, availability and multi-product/category limits with
  no time-based cart/item expiry; promotions excluded. Authenticated merge is C3.
- [x] Add narrow service-token abilities for Next.js cart mutations (C2.2).
- [x] Return a server-calculated compact cart summary for `adzbyte-next` (API verified; actual controls are C2.3).
- [x] Add a short-lived, single-use signed **View Cart** handoff to the app.
- [x] Render the full cart in the customer application before authentication.
- [x] Test tampering, replay, expiry, cross-cart access, and concurrent updates.

**Gate:** Next.js never calculates an authoritative total and a valid cart can
move to the app without exposing another customer's cart or a privileged token.

C2 development qualified at backend `0c3d606` / frontend `5467e0d`: both source
CI gates and real integrated desktop/mobile browser proof pass. See
[qualification](../evidence/2026-10-18-cart/qualification.md). No deployment.

### C3. Registration and checkout identity

- [x] Enable public customer registration in `adzbyte-app` (C3.1 f32bc36, CI 39093386908).
- [x] Preserve the cart across registration, verification, login, password
  reset, and session rotation.
- [x] Attach or merge the cart only after authentication using documented rules.
- [x] Require authenticated ownership before order creation (O1.1/O1.2 source 538e3ca, CI 39098787766; payment remains disabled).
- [x] Add abuse throttles and tests for account enumeration, role injection,
  unverified accounts, and duplicate identities.

**Gate:** anyone can safely create a customer account, while a public request can
never choose privileges or attach a cart/order to an unverified identity.

## Phase O — Orders and One-Time Payments

### O1. Order domain

- [x] Add customers' orders, immutable order-item snapshots, public references,
  addresses where required, totals, and separate order/payment/fulfillment
  states.
- [x] Add append-only order events and authorized transition services.
- [x] Add customer ownership and administrator capability policies.
- [x] Add the checkout review and terms snapshot (O1.2 verified).
- [x] Add the project brief and administrator fit approval, rejection, and
  correction boundary before an order becomes payable.
- [x] Add idempotent cart-to-pending-order conversion (O1.1/O1.2 verified).

### O2. PayMongo one-time payments

- [ ] Confirm and document enabled PayMongo payment methods.
- [ ] Create a PayMongo Hosted Checkout v2 session only for a persisted,
  authenticated, approved order snapshot.
- [ ] Store provider session, payment, event, amount, currency, mode, and order
  references.
- [ ] Verify webhook signatures against the raw body.
- [ ] Process duplicate, delayed, invalid, and out-of-order events safely.
- [ ] Keep browser returns informational and reconcile mismatches explicitly.
- [ ] Add approved full/partial refund and failed/expired payment workflows after
  their policies are decided.

**Gate:** a PayMongo test payment moves one order from pending to paid exactly
once through a verified webhook, and no redirect, replay, or mismatched event can
grant purchased access.

### O3. Customer and administrator order management

- [ ] Customer order list, detail, receipt, payment state, and timeline.
- [ ] Administrator order, payment, provider-event, reconciliation, and refund
  views and authorized actions.
- [ ] Notifications for material order and payment events.
- [ ] Customer and operational order APIs with ownership/capability tests.

## Phase S — Subscriptions and Purchased Products

### S1. Subscription billing

Subscription billing is deliberately deferred until the one-time service path
is verified. Current monthly care plans remain enquiry-only.

- [ ] Confirm PayMongo subscription capability and supported merchant methods.
- [ ] Add internal subscription plans mapped to provider plans.
- [ ] Add subscriptions, billing cycles, invoices, payment attempts, and provider
  references.
- [ ] Implement first-payment authorization and verified subscription webhooks.
- [ ] Implement explicit incomplete, active, past-due, unpaid, and cancelled
  transitions.
- [ ] Implement decided cancellation, plan-change, retry, grace-period, and
  entitlement-suspension rules.
- [ ] Add customer and administrator subscription management.

### S2. Purchased products and entitlements

- [ ] Create purchased-product records from paid order items and active
  subscriptions.
- [ ] Snapshot capabilities, limits, renewal behavior, and fulfillment terms.
- [ ] Gate every product-specific action by ownership and current entitlement.
- [ ] Update access through explicit refund, expiry, cancellation, and failed
  renewal transitions.
- [ ] Add the customer purchased-products dashboard and administrator controls.

**Gate:** confirmed one-time and recurring payments grant only the snapshotted
access they purchased, and failed or cancelled billing follows the approved
access policy.

## Phase R — Requests, Reports, and Communication

### R1. Customer cases

- [ ] Finalize request/report categories, required fields, statuses, priorities,
  assignment, escalation, reopening, and service targets.
- [ ] Add customer cases with explicit `request` and `report` types.
- [ ] Link cases to an owned purchased product, order, or subscription where
  appropriate without blocking account/payment/privacy/security reports.
- [ ] Add customer-visible messages, read state, staff assignment, internal
  notes, and append-only activity.
- [ ] Add private attachments after file rules and retention are approved.
- [ ] Add email and in-app notifications.
- [ ] Add customer and administrator panel workflows and API contracts.

**Gate:** customers can submit and follow their own requests and reports, staff
can triage and resolve them, internal notes never leak, and cross-customer access
is denied in panels and APIs.

## Phase M — Experience Expansion

- [x] Shared management branding and first-party customer/admin home foundations.
- [ ] Commerce-aware customer home with cart, orders, subscriptions, purchased
  products, and open-case summaries.
- [ ] Operational administrator dashboard for payment, subscription, fulfillment,
  and customer-service queues.
- [ ] Authenticated product listings in the customer app using the same catalog
  services as the public storefront.
- [ ] Notification preferences and account lifecycle controls.

## Phase Q — Production Readiness

- [ ] Full cross-account authorization and capability audit for commerce records.
- [ ] End-to-end Next.js catalog-to-app-cart handoff test.
- [ ] End-to-end registration-to-PayMongo-payment-to-entitlement test.
- [ ] PayMongo webhook replay, invalid-signature, duplicate, mismatch, and
  out-of-order tests.
- [ ] Subscription renewal, failure, retry, cancellation, and reconciliation
  acceptance tests.
- [ ] Upload security and private-file access audit.
- [ ] Queue retry, failed-job, notification, and external alerting verification.
- [ ] Database index and query review for carts, orders, billing, and case queues.
- [ ] Backup, retention, privacy, account deletion, financial-record retention,
  and incident procedures.
- [ ] Accessibility, responsive, and production-build review for every new panel
  workflow.
- [x] Generic production configuration and recovery baseline.

## Definition of Done for Every Task

The owner-approved 2026-10-24 Filament UI increment retains the existing commerce
phase boundaries. Three UX batches are implemented and independently reviewed;
final full-suite/source CI qualification is pending. See the
[increment review](../evidence/2026-10-24-filament-ux/qualification.md). This work
does not complete the deferred commerce/provider gates below.

- Follow the owner-required [phase verification gate](../verification.md).
  A task/phase stays partial until all applicable checks pass at its delivered
  source revision; dependent implementation waits for those gates.
- Acceptance behavior and any durable business decision are documented.
- Authorization, ownership, validation, idempotency, and forbidden paths are
  tested where relevant.
- Focused tests and the full relevant suite pass.
- Formatting, Composer validation, asset builds, and migration checks pass when
  affected.
- Migrations work from a clean database and are reversible where practical.
- OpenAPI matches every implemented endpoint.
- No secrets, payment credentials, or customer data are committed.
- Roadmap and status documents reflect verified implementation rather than
  intent.
- The [development handoff](../DEVELOPMENT-HANDOFF.md) records the active card,
  changed paths, checks, Git/runtime state, decision blockers, and one next
  action. Update it at meaningful checkpoints and before an interruption.

C3 identity/B2 qualification: [current evidence](../evidence/2026-10-18-ownership/qualification.md).
The downstream payable-order guard remains an O1 acceptance requirement.

## O1 qualified checkpoint — 2026-10-18

Source e27fefa / [CI 39101761212](https://github.com/adzadzadz/adzbyte-app/actions/runs/39101761212)
passes 397 / 6,374 each on PHP 8.3.35 SQLite/MySQL, audits/build/caches.
Fifteen built cross-app browser checks, real HTTP/OpenAPI/native form and
database-queue/SMTP success/three-attempt failure/recovery pass. See
[qualification](../evidence/2026-10-18-order-review/qualification.md).
O1 is complete. O2 remains blocked on owner-deferred test credentials and actual
provider sandbox qualification. Downstream phases remain incomplete. This
supersedes earlier continuation checkpoints; no production/existing DB changed.

## O2.1 qualified checkpoint — 2026-10-18

Source8f13521 / [CI 39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120), **436 / 6,688 per engine**, PHP8.3.35 SQLite/MySQL8.0.46, source checks/audits/caches and independent review pass. Actual test creation/retrieval/replay/expiry plus native UI/HTTP/OpenAPI/schema/races pass; [evidence](../evidence/2026-10-18-payments/qualification.md). O2.2/O2.3 and downstream phases remain pending.


## O2.2 qualified checkpoint — 2026-10-18

Source7560514 / [CI39116369733](https://github.com/adzadzadz/adzbyte-app/actions/runs/39116369733), **477 / 6,908 per engine**, PHP8.3.35 SQLite/MySQL8.0.46. Actual signed test payment, provider delivery Success, duplicate settlement safety, native paid UI, independent review and all source gates pass. [Evidence](../evidence/2026-10-18-payments/webhook-qualification.md). O2.3 operations/refunds is next; O2 aggregate remains incomplete.


## O2.3a qualified checkpoint — 2026-10-18

Source28a0f86 / [CI39123794458](https://github.com/adzadzadz/adzbyte-app/actions/runs/39123794458), **505 /7,164 per engine**, PHP8.3.35 SQLite/MySQL8.0.46; audits/source/cache gates pass. Actual preserved signed test receipt recovered through Admin with one settlement/resolution and unchanged original rejection. Native/REST/OpenAPI, independent review, migration and process races pass. [Evidence](../evidence/2026-10-18-payments/reconciliation-qualification.md). O2.3b expiry/cancellation is next; O2.3c refunds and aggregate O2 remain incomplete.


## O2.3b verification checkpoint — 2026-10-19

Captured configurable cancellation, durable once-only closure/recovery commands,
strict native/REST adapters, exact capabilities, migration/process races and
actual unknown-ID recovery/customer cancellation pass local/independent checks.
One actual unused test session is now expired, with zero payments/settlements;
accepted history remains readable. [Evidence](../evidence/2026-10-18-payments/closure-qualification.md).
O2.3b qualified source46614f8 / [CI39191936629](https://github.com/adzadzadz/adzbyte-app/actions/runs/39191936629) passes539/7,537 each
on PHP8.3.35 SQLite/MySQL8.0.46; all source gates pass. O2.3c is next.
Test credentials are available; earlier credential
blockers are historical. O2 aggregate and downstream phases remain incomplete.


## O2.3c contract discovery blocker — 2026-10-19

Actual QRPh-specific test refund failed with no claim link; generic endpoint
rejects QRPh. Test wallet inventory is empty. A successful sandbox refund/claim
and authoritative recovery contract remain unavailable; no C product
implementation or qualification is claimed. Owner asked to arrange PayMongo test
wallet/test funds and simulation support. [Evidence/support note](../evidence/2026-10-18-payments/refund-qualification-blocker.md).
O3/S2, R1 and Q stay pending. Dedicated test webhook disabled; temporary ingress,
UI/tunnel stopped and private recovery bundle preserved. No live change.
