# Adzbyte Commerce Platform Implementation Roadmap

| Field | Value |
|---|---|
| Status | Generic application foundation complete; commerce planning in progress |
| Product source of truth | `docs/plans/2026-10-05-commerce-platform.md` |
| Progress tracker | `docs/STATUS.md` |
| Application | Laravel 13 / PHP 8.3 |
| Management UI | Filament 5 customer panel at `/` and administrator panel at `/admin` |
| API prefix | `/api/v1` |

## Delivery Rules

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
- [ ] Enable safe public customer registration without accepting public roles or
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

- [ ] Resolve the first-release product types and fulfillment requirements.
- [ ] Add products, sellable variants or plans, versioned prices, media,
  publication state, and availability rules.
- [ ] Add versioned entitlement definitions.
- [ ] Add administrator catalog management through shared services and policies.
- [ ] Add safe, cacheable storefront catalog and product-detail APIs.
- [ ] Test draft/unpublished visibility, price snapshots, and unauthorized
  catalog mutations.

**Gate:** Laravel can publish a product, `adzbyte-next` can render only its safe
public representation, and an administrator can change future catalog state
without altering an existing price or entitlement snapshot.

### C2. Anonymous cart and app handoff

- [ ] Add anonymous and authenticated carts and cart items.
- [ ] Define cart expiry, quantity, availability, promotion, and merge rules.
- [ ] Add narrow service-token abilities for Next.js cart mutations.
- [ ] Return a server-calculated compact cart summary for `adzbyte-next`.
- [ ] Add a short-lived, single-use signed **View Cart** handoff to the app.
- [ ] Render the full cart in the customer application before authentication.
- [ ] Test tampering, replay, expiry, cross-cart access, and concurrent updates.

**Gate:** Next.js never calculates an authoritative total and a valid cart can
move to the app without exposing another customer's cart or a privileged token.

### C3. Registration and checkout identity

- [ ] Enable public customer registration in `adzbyte-app`.
- [ ] Preserve the cart across registration, verification, login, password
  reset, and session rotation.
- [ ] Attach or merge the cart only after authentication using documented rules.
- [ ] Require authenticated ownership before payable order creation.
- [ ] Add abuse throttles and tests for account enumeration, role injection,
  unverified accounts, and duplicate identities.

**Gate:** anyone can safely create a customer account, while a public request can
never choose privileges or attach a cart/order to an unverified identity.

## Phase O — Orders and One-Time Payments

### O1. Order domain

- [ ] Add customers' orders, immutable order-item snapshots, public references,
  addresses where required, totals, and separate order/payment/fulfillment
  states.
- [ ] Add append-only order events and authorized transition services.
- [ ] Add customer ownership and administrator capability policies.
- [ ] Add the checkout review and terms snapshot.
- [ ] Add idempotent cart-to-pending-order conversion.

### O2. PayMongo one-time payments

- [ ] Confirm and document enabled PayMongo payment methods.
- [ ] Create PayMongo checkout/payment resources only for a persisted pending
  order.
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
