# Adzbyte Commerce and Customer Service Platform

| Planning field | Decision |
|---|---|
| Date | 2026-10-05 |
| Status | Product direction approved; detailed commerce rules still being refined |
| Primary storefront | `adzbyte-next` at `https://adzbyte.com` |
| Account, cart, and checkout application | `adzbyte-app` at `https://app.adzbyte.com` |
| Backend and system of record | `adzbyte-app` |
| First payment provider | PayMongo |
| Management UI | Filament customer panel at `/` and administrator panel at `/admin` |
| API prefix | `/api/v1` |

## Product Direction

Adzbyte is building a headless commerce and customer-service platform. The
public website presents products, while the Laravel application owns the
authoritative catalog, carts, accounts, checkout, orders, payments,
subscriptions, purchased-product access, customer communication, and
administration.

The platform is not limited to the former experimental-launch catalog. Product
types, pricing, fulfillment rules, and subscription offerings will be defined
separately. The previous experimental product proposal is retired and is not an
active product or implementation source.

## Application Ownership

### `adzbyte-next`

`adzbyte-next` owns the primary public shopping and discovery experience:

- public product listings and product detail pages;
- search, filtering, merchandising, and campaign presentation;
- add-to-cart controls and a compact cart summary;
- a **View Cart** action that transfers the customer to `adzbyte-app`.

It does not own the full cart, authentication, checkout, payment confirmation,
orders, subscriptions, purchased-product management, or customer-service
records. It never receives PayMongo secret keys or a privileged application
credential in browser code.

### `adzbyte-app`

`adzbyte-app` owns:

- the authoritative product catalog, prices, availability, and sale rules;
- anonymous and customer carts;
- public registration, login, verification, password recovery, and profiles;
- the full cart and every checkout step;
- customers, orders, order items, payments, refunds, subscriptions, invoices,
  purchased-product access, and entitlements;
- customer requests, issue reports, messages, attachments, and notifications;
- the customer account portal and administrator operations;
- PayMongo calls, webhook processing, reconciliation, and provider references;
- the versioned APIs used by `adzbyte-next` and future clients;
- the audit trail and authoritative business state.

The app may add product listings inside the authenticated customer portal in a
later phase. The primary public product-listing experience remains in
`adzbyte-next`; a future app listing must reuse the same catalog services and
must not create a second catalog or pricing source.

## Registration and Customer Identity

- Anyone may register directly in `adzbyte-app`; a cart handoff is not required.
- Registration creates only a customer identity and must never accept a role or
  permission from public input.
- Email verification remains part of the account lifecycle.
- A customer must authenticate before Laravel creates the payable order and
  starts a PayMongo payment or subscription authorization.
- A customer may review an anonymous cart before authentication.
- Existing accounts must authenticate before an incoming anonymous cart can be
  attached to them.
- Every customer-visible record is authorized by ownership. A `customer` role
  alone never grants access to another customer's records.

Public registration replaces the earlier checkout-provisioned-only account
rule. The existing secure activation action may remain available for trusted
staff-created or integration-created customers, but it is no longer the only
way a customer account can begin.

## Cart and Checkout Sequence

1. `adzbyte-next` reads product and price data from versioned storefront APIs.
2. A customer adds products to an anonymous Laravel-owned cart through the
   Next.js server. The Next.js server may hold an opaque cart reference in a
   secure cookie, but it does not calculate authoritative prices.
3. Next.js displays only a compact, safe cart summary returned by Laravel.
4. **View Cart** requests a short-lived, single-use handoff from Laravel and
   redirects the browser to `app.adzbyte.com`.
5. Laravel validates the handoff and renders the full cart. The customer can
   review and edit the cart without authenticating.
6. **Continue to Purchase** starts the login or registration step. After a
   successful authentication flow, Laravel attaches or deterministically merges
   the anonymous cart into the customer's active cart.
7. Laravel revalidates products, prices, quantities, discounts, availability,
   tax, fulfillment requirements, and subscription terms. Client-supplied
   totals are never trusted.
8. The customer supplies required billing or fulfillment information, reviews
   the final amount, and accepts applicable purchase and recurring-payment
   terms.
9. Laravel creates an internal pending order before creating the PayMongo
   Checkout Session, Payment Intent, or Subscription. This provides a stable
   reconciliation reference even if the browser never returns.
10. Laravel redirects the customer to the provider-controlled authorization
    page when required.
11. The browser return route shows only a pending or informational result.
12. A verified, idempotently processed PayMongo webhook changes the
    authoritative payment, order, invoice, or subscription state.
13. The customer manages the resulting order, subscription, purchased product,
    requests, and reports in `adzbyte-app`.

The handoff token must be opaque, short-lived, single-use, and bound to the
intended cart and return destination. Laravel must validate every line again
after handoff and after authentication. Cart expiry, merge rules, promotion
conflicts, and inventory reservations still require explicit policy decisions.

## Commerce Domain

The target backend contains the following bounded areas. Exact tables and
classes are implementation decisions, but the domain boundaries are durable.

### Catalog

- Products with stable public identifiers, publication state, descriptions,
  media, and product type.
- Sellable variants or plans where a product has different prices, terms, or
  options.
- Versioned prices in minor currency units and an explicit currency.
- Product availability and purchase constraints.
- Versioned product entitlements so an existing purchase is not silently
  changed when the current catalog changes.

### Cart and checkout

- Anonymous and authenticated carts with line items and server-calculated
  totals.
- Deterministic cart handoff, attachment, merge, expiry, and invalidation.
- Checkout validation and a snapshot of the accepted commercial terms.
- Idempotent order and provider-session creation.

### Orders and payments

- Orders with opaque public references and immutable order-item snapshots.
- Separate order, payment, and fulfillment states.
- Payment attempts, provider events, refunds, disputes where supported, and
  reconciliation metadata.
- Append-only events for important state transitions and staff actions.
- Provider return pages are informational; verified webhooks are authoritative.

### Subscriptions and invoices

- Internal plans mapped to PayMongo plan identifiers without making provider
  objects the local source of truth.
- Subscriptions, billing cycles, invoices, payment attempts, cancellation, and
  provider status synchronization.
- Explicit handling for incomplete, active, past-due, unpaid, and cancelled
  states.
- Customer-visible next billing information and authorized self-service actions.
- Entitlement changes follow confirmed billing state rather than browser
  redirects.

PayMongo supports scheduled subscription billing through plans, customers,
subscriptions, invoices, and recurring collection. Subscription access requires
separate PayMongo account activation and supported payment methods must be
confirmed before implementation.

### Purchased products and entitlements

- A customer-facing purchased-product record derived from a paid order item or
  active subscription.
- A snapshot of access, limits, renewal behavior, and fulfillment expectations.
- Product-specific controls may be added behind entitlement checks without
  weakening order or subscription ownership.
- Expiration, cancellation, refund, and failed-renewal behavior must update
  access through explicit state transitions.

### Requests, reports, and conversations

Customers can contact Adzbyte from a purchased product, order, or subscription:

- A **request** asks Adzbyte to perform an allowed service action or change.
- A **report** describes a fault, incident, incorrect result, or other problem.
- Both have an opaque reference, owner, subject, description, status, priority,
  timestamps, and an optional link to a purchased product, order, or
  subscription.
- Both support a customer-visible message thread, attachments, staff assignment,
  notifications, and an append-only activity history.
- Internal notes are stored separately and must never be returned through a
  customer API.
- Product entitlements may restrict which request types or service levels are
  available, but customers must still have a safe way to report account,
  payment, privacy, or security problems.

A shared case model with an explicit `request` or `report` type is preferred
unless later workflow differences justify separate models. The two concepts
remain visibly distinct to customers even if they share infrastructure.

## API Contract

All shop capabilities originate in `adzbyte-app` application services. Every
customer-facing shop capability must have a versioned API contract, even when
the initial app-owned page calls the same service directly rather than making an
HTTP request to itself. Administrator-only configuration may remain panel-only
until an approved external client needs it.

### Access modes

| Caller | Purpose | Authentication |
|---|---|---|
| Public or Next.js storefront | Published catalog reads | Public, cacheable, rate-limited endpoints returning only safe fields |
| `adzbyte-next` server | Anonymous cart mutations and cart handoff | Dedicated revocable service token with narrow abilities |
| Authenticated customer | Identity and owned commerce/customer-service records | Stateful Sanctum session or an explicitly issued first-party token |
| Filament panels | Customer and administrator management | Laravel web session plus panel access and policies |
| PayMongo | Payment and subscription events | Verified signature against the raw request body |

Planned endpoint groups include:

- storefront catalog, product detail, price, and availability;
- anonymous cart creation, mutation, summary, and handoff;
- authenticated cart and checkout preparation;
- orders, payments, invoices, subscriptions, and purchased products;
- requests, reports, messages, and attachments;
- restricted administrator or integration actions where an API consumer is
  approved;
- dedicated PayMongo webhooks.

Every mutation that can be retried uses an idempotency key. API Resources define
response fields, Form Requests validate input, policies authorize records and
actions, and the OpenAPI document changes with the implementation. Generic
unrestricted CRUD is not an acceptable substitute for checkout, payment,
subscription, refund, cancellation, or entitlement workflows.

## Customer Portal

The app provides a limited anonymous full-cart page after a valid handoff. The
authenticated root `/` Filament panel will provide:

- account and profile management;
- the full cart and checkout entry;
- order history, order details, receipts, and payment state;
- subscriptions, invoices, next billing information, and allowed subscription
  actions;
- purchased products and their entitled controls;
- requests, issue reports, message threads, attachments, and notifications;
- a future authenticated product listing using the same catalog services.

The portal remains the authoritative customer account experience even though
the backend APIs are designed for reuse.

## Administrator Operations

The `/admin` panel will provide policy-backed management of:

- products, variants or plans, prices, availability, and publication;
- customers and account state;
- carts when operational support requires inspection;
- orders, payments, refunds, reconciliation, and provider events;
- subscriptions, invoices, failed renewals, cancellations, and entitlements;
- purchased products and fulfillment state;
- customer requests, reports, messages, attachments, assignment, and internal
  notes;
- roles, permissions, audit events, and operational queues.

Filament panels, API controllers, jobs, and webhooks must call shared application
services and policies so business rules cannot diverge by interface.

## Security and Reliability Rules

- Never trust a browser or Next.js-supplied price, total, customer identifier,
  entitlement, payment state, or role.
- Never expose Laravel, PayMongo, mail, hosting, or integration credentials to
  browser code.
- Verify PayMongo signatures against the raw request body and store provider
  event identifiers for idempotency.
- Process provider events safely when duplicated, delayed, or out of order.
- Keep payment credentials with PayMongo; store only required provider
  references and non-sensitive billing metadata.
- Enforce ownership in queries and policies across panels and APIs.
- Store uploads privately and issue authorized temporary downloads.
- Queue notifications and provider follow-up work after database commit.
- Record security-sensitive and financially significant transitions without
  storing secrets or unnecessary sensitive data in logs.

## Delivery Scope

The first complete commerce release should prove this vertical path:

```text
published product in Laravel
  -> product rendered by adzbyte-next through the catalog API
  -> anonymous cart and compact Next.js cart summary
  -> signed handoff to the full app cart
  -> registration or login
  -> authoritative checkout and pending order
  -> PayMongo test payment
  -> verified webhook confirmation
  -> order and purchased product visible in the customer portal
  -> customer submits a request or report
  -> administrator responds and resolves it
```

Subscription billing follows after the one-time purchase path is verified,
unless the first confirmed launch product requires a subscription to be useful.

## Decisions Still Required

### Catalog and fulfillment

- Which product types launch first: physical goods, digital downloads, one-time
  services, recurring services, or a defined combination.
- Whether variants, inventory, backorders, shipping, downloads, appointments,
  or manual service fulfillment are required in the first release.
- Initial products, prices, currencies, tax treatment, and invoice/receipt
  requirements.
- Product publication, archival, purchase limits, and availability rules.

### Cart and checkout

- Cart expiration and authenticated-cart merge rules.
- Promotion, coupon, discount, tax, and shipping calculations.
- Required customer and billing fields for each product type.
- Abandoned checkout behavior and notification policy.

### Payments and subscriptions

- Enabled PayMongo one-time payment methods.
- Confirmation that PayMongo Subscriptions is enabled for the merchant account.
- Subscription intervals, trials, plan changes, cancellation timing, proration,
  grace periods, retry outcomes, and entitlement suspension.
- Full and partial refund rules, cancellation policy, and dispute handling.

### Customer service

- Request and report categories, required fields, priorities, assignment rules,
  service targets, escalation, reopening, and closure behavior.
- Attachment types, sizes, counts, malware handling, and retention.
- Email and in-app notification events and customer preferences.

### Privacy and operations

- Retention and deletion periods for carts, accounts, orders, payments,
  subscriptions, messages, attachments, and audit events.
- Account deletion and financial-record retention behavior.
- Tax, consumer-protection, privacy, and terms language required for launch.
- Monitoring destination and financial/operational alert thresholds.

These decisions must be recorded here before their implementation slice begins.

## Official Provider References

- [PayMongo Hosted Checkout](https://docs.paymongo.com/docs/payment-channels-hosted-checkout)
- [PayMongo webhook setup and signature verification](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management)
- [PayMongo Subscriptions](https://docs.paymongo.com/docs/payment-acceptance-subscriptions)
- [PayMongo subscription resource](https://docs.paymongo.com/reference/subscription-resource)
- [Laravel Sanctum](https://laravel.com/docs/13.x/sanctum)
