# First Commerce Release Approach

| Planning field | Decision |
|---|---|
| Date | 2026-10-05 |
| Status | Approved implementation approach; commercial launch inputs remain pending |
| Commerce authority | Native Laravel domain in `adzbyte-app` |
| Public storefront | `adzbyte-next` |
| Initial product type | One-time service |
| Fulfillment | Manual service delivery after administrator fit approval |
| Payment flow | PayMongo Hosted Checkout v2 |
| Checkout currency | PHP |
| Subscription billing | Deferred until after the one-time path is verified |

## Decision

The first commerce implementation will be a narrow native Laravel vertical
slice rather than an installed general-purpose commerce platform or a second
hosted store.

This direction reuses the application's existing users, Filament panels,
Laravel policies, versioned API foundation, persistent idempotency boundary,
queues, and operations baseline. It preserves `adzbyte-app` as the system of
record and avoids introducing another catalog, customer account, checkout,
order, or administration authority.

The first sellable product type is a fixed-scope, one-time service. Each service
line has a quantity of one, is fulfilled manually, and requires a human fit and
scope review before payment is enabled. The exact launch products and PHP price
amounts must be approved before catalog implementation; the current pricing
page is a candidate source, not an authoritative import.

This document defines the approved implementation boundary. It does not claim
that commerce is implemented, that the current USD amounts have been converted,
or that the remaining tax, receipt, payment-method, cancellation, refund, and
retention rules are settled.

## Why This Is the Fastest In-App Path

- The existing Laravel application already owns identity, authorization,
  administration, APIs, idempotency, queues, and operational safeguards.
- The launch candidates are manually fulfilled services, not a physical retail
  catalog that needs inventory, warehouses, shipping, or variant matrices.
- Filament can manage the small catalog and approval workflow without adding a
  second administration system.
- PayMongo Hosted Checkout keeps payment-entry UI and payment credentials at the
  provider while Laravel retains the authoritative order and payment state.
- A narrow local domain is easier to align with approval-before-payment than a
  generic retail checkout pipeline.

General-purpose commerce packages, a separate headless engine, Shopify,
WooCommerce, and PayMongo Storefront are not part of this release. They may be
reconsidered only if the product direction changes enough to justify replacing
the current system-of-record boundary. A temporary external payment page would
collect money but would not constitute ecommerce capability in this app.

## First-Release Scope

### Included

- An authoritative Laravel catalog containing only approved fixed-price,
  one-time service packages.
- Versioned PHP prices stored in minor units, with accepted product, price,
  scope, and fulfillment terms snapshotted on the order.
- Publication and basic sale availability controlled by authorized
  administrators.
- Safe public product and product-detail API representations for
  `adzbyte-next`.
- Anonymous Laravel-owned carts, compact storefront summaries, and a
  short-lived single-use handoff to the app-owned full cart.
- Public customer registration, verification, login, and deterministic cart
  attachment or merge.
- A checkout brief sufficient for an administrator to determine whether the
  selected service fits the customer's request.
- An administrator approve, reject, or request-correction workflow before
  payment is enabled.
- An internal order and immutable order-item snapshot before a PayMongo session
  is created.
- PayMongo Hosted Checkout v2, informational browser returns, verified
  idempotent webhooks, and explicit reconciliation records.
- Customer and administrator order/payment views and a purchased-service record
  after confirmed payment.
- The existing complete-release path into customer requests or reports after
  the payment-capable milestone is verified.

### Explicitly excluded from the initial slice

- Physical goods, inventory, backorders, shipping, warehouse, and pickup rules.
- Digital downloads, license delivery, and appointment scheduling.
- Product variants, customer-group pricing, tiered quantities, and line
  quantities greater than one.
- Coupons, promotions, automatic discounts, abandoned-cart campaigns, and
  pass-on-fee behavior.
- Subscription checkout for the current monthly care plans.
- Automatic acceptance of custom projects or informational project-price
  ranges as purchasable products.
- Self-hosted payment fields or treating the browser return as proof of payment.

Care plans remain enquiry-only until PayMongo subscription capability and the
cancellation, retry, grace-period, plan-change, invoice, refund, and entitlement
rules are approved. Project price ranges remain informational and continue into
the custom-quote flow.

## Approval-Gated Customer Journey

1. `adzbyte-next` reads published services and current PHP prices from the
   versioned storefront API.
2. The customer adds an eligible service to an anonymous Laravel-owned cart.
   The server fixes each line quantity at one and calculates the summary.
3. **View Cart** obtains a short-lived, opaque, single-use handoff and redirects
   the browser to `adzbyte-app`.
4. The customer reviews the full cart before authentication.
5. Continuing requires registration or login, email verification, and secure
   cart attachment or merge.
6. The customer supplies the required project brief, billing information, and
   acceptance of the applicable service terms.
7. Laravel creates an owned order with immutable product, price, scope, and
   terms snapshots. The order is not yet payable.
8. An authorized administrator reviews the request and either approves it,
   rejects it, or requests a customer correction. Approval is recorded with the
   actor and timestamp.
9. Approval enables the customer-facing **Pay Now** action for the approved
   snapshot. A material scope or price change requires a new customer acceptance
   before payment.
10. Laravel creates a PayMongo Hosted Checkout v2 session for that persisted
    order and redirects the customer to PayMongo.
11. The return route shows only pending or informational state.
12. A verified, idempotently processed PayMongo webhook records the provider
    event and changes the authoritative payment and order state exactly once.
13. Confirmed payment creates or activates the purchased-service record and
    makes it visible in the customer portal for manual fulfillment and support.

Exact enum names remain an implementation detail, but the domain must represent
cart, approval, order, payment, and fulfillment state separately. Approval must
never imply payment, and payment must never imply completed fulfillment.

## Application Boundaries

### Shared Laravel services

Catalog publication, cart calculation, handoff, cart attachment, order
creation, approval, PayMongo session creation, webhook processing, and
purchased-service creation must each have one shared application-service
boundary. Filament pages, REST controllers, jobs, and webhooks call those
services rather than reproducing their rules.

### Authorization

- Public catalog responses expose only published safe fields.
- Next.js receives only narrow cart and handoff capabilities; it never receives
  a general integration credential in browser code.
- Customers can access only their own cart, order, payment summary, and
  purchased-service records.
- Administrator role membership alone does not grant every commerce action;
  Laravel policies and named capabilities authorize catalog mutation, fit
  approval, payment operations, and fulfillment actions.
- The approving administrator, approval time, and later material changes must be
  auditable.

### Money and provider authority

- Laravel stores integer minor-unit amounts and the explicit `PHP` currency.
- The client never supplies an authoritative price, total, currency, customer,
  order, approval, entitlement, or payment state.
- A PayMongo session is created only for an authenticated customer's persisted,
  approved order snapshot.
- Session creation and every retry-prone mutation are idempotent.
- Provider signatures are verified against the raw webhook request body.
- Duplicate, delayed, invalid, mismatched, and out-of-order events must be safe.
- Browser redirects never mark an order paid or grant purchased access.

## Minimum Domain Boundaries

The first slice needs durable records for the following concepts. Exact table
and class names are implementation decisions.

- Product and versioned price.
- Minimal versioned entitlement or purchased-service terms.
- Anonymous or customer cart and cart line.
- Single-use handoff.
- Order and immutable order-item snapshot.
- Fit review or equivalent append-only approval activity.
- Payment attempt, PayMongo session/reference, and provider event.
- Purchased service and separate fulfillment state.

The models should remain deliberately small. Do not add inventory, shipping,
subscription, promotion, download, or appointment fields in anticipation of
unapproved future requirements.

## Required API Contracts

The implementation must extend `/api/v1` with documented contracts for:

- safe public service listing and detail reads;
- restricted Next.js cart creation, mutation, summary, and handoff;
- authenticated customer cart, checkout-brief, order, payment-summary, and
  purchased-service actions;
- the dedicated PayMongo webhook;
- any approved administrator or integration action that gains an external
  caller.

API Resources define response fields, Form Requests validate input, policies
authorize record access, and the OpenAPI document changes in the same slice as
each endpoint. Filament may call shared services directly and does not make HTTP
requests back into the same application.

## Delivery Sequence

1. **Commercial input gate** — approve launch products, PHP prices, tax and
   receipt treatment, brief fields, approval rules, payment methods, and initial
   cancellation/refund terms.
2. **C1: catalog and pricing** — product, versioned price, minimal entitlement,
   Filament management, safe storefront APIs, and migration of the approved
   catalog out of hard-coded Next.js data.
3. **C2: cart and handoff** — anonymous cart, quantity-one rules, summary,
   narrow server credential, secure cookie reference, and single-use handoff.
4. **C3: registration and identity** — safe public registration and cart
   preservation through authentication and verification.
5. **O1: order and fit approval** — brief, immutable snapshots, customer
   acceptance, approval activity, and customer correction path.
6. **O2: PayMongo one-time payment** — Hosted Checkout v2, payment attempts,
   signature verification, idempotent events, and reconciliation.
7. **O3/S2: portal and purchased service** — customer/admin order views,
   purchased-service creation, fulfillment state, and notifications.
8. **R1: service communication** — the minimum approved request/report flow
   needed to complete the product plan's end-to-end launch gate.

Every slice requires successful and forbidden-path tests, OpenAPI alignment,
reversible migrations where practical, and the applicable full verification
gate before its roadmap status changes.

## Decisions Required Before C1 Implementation

- Which existing fixed-price packages are the initial authoritative products.
- The exact PHP amount for each approved product; current USD figures must not be
  converted silently or at runtime without an approved pricing policy.
- Whether tax is included, excluded, zero-rated, or handled manually, plus the
  required receipt or invoice output.
- Publication defaults, sale availability, and any per-customer purchase limit.
- Whether one cart may contain more than one distinct service package.

## Decisions Required Before Checkout and Payment

- Required project-brief and billing fields.
- Who may approve fit, how corrections and rejection work, and when an approval
  or Pay Now action expires.
- Cart expiry and deterministic anonymous/authenticated merge behavior.
- Enabled PayMongo one-time payment methods and whether fees are absorbed.
- Customer cancellation, expired-payment, full-refund, partial-refund, and
  dispute behavior.
- Terms acceptance, privacy language, retention, and customer notifications.
- The minimum request/report categories and purchased-service fulfillment
  workflow required for launch.

These decisions must be added to the commerce platform plan before the slice
that depends on them begins. Development must not replace missing commercial or
legal choices with framework defaults.
