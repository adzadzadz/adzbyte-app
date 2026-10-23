# Adzbyte App

`adzbyte-app` is Adzbyte's commerce backend, checkout application, customer
portal, and administrative system of record.

**Current priority (2026-10-23): customer portal.** Admins manually create customer accounts and assign projects
and services; customers follow milestones, next steps and updates, and exchange
support messages. Ecommerce development is paused. Its existing screens remain
reachable with production Under development notices and customer write protection.
See the [portal plan](docs/plans/2026-10-23-customer-portal.md) and
[portal API](docs/api/customer-portal.md). Source qualification is tracked in
[portal release evidence](docs/evidence/2026-10-23-customer-portal/polish/qualification.md).
The reviewed portal is deployed at [app.adzbyte.com](https://app.adzbyte.com);
administration is at [/admin](https://app.adzbyte.com/admin).

The reusable application foundation is complete: authentication, management
branding, customer and administrator Dashboards, API contracts,
idempotency, queues, operational hardening, and administrator user/role
management. C1.1 adds the dynamic catalog domain and versioned price/terms
persistence; C1.2 adds capability-backed shared actions, locked revision
allocation, retry safety, and actor/time audit history, verified in isolated
tests. C1.3 adds administrator catalog management through those actions, including
read-only history and capability controls. Its local/browser checks and both
PHP 8.3 SQLite/MySQL CI jobs pass. C1.4 adds safe public catalog endpoints and a
real Next consumer. Categories, persistent carts, replacement notices, handoff,
registration and verified ownership/merge are qualified through C3/B2. O1.1/O1.2
add immutable accepted order snapshots, native checkout, owned request summaries
and versioned REST operations, qualified at 538e3ca / CI 39098787766. Admin review, customer communication, versioned settings and recoverable
notifications complete O1 at e27fefa / CI 39101761212 (397 / 6,374 each on
PHP 8.3 SQLite/MySQL). Provider payment progress is recorded in STATUS;
subscriptions and purchased entitlements remain paused. Independent manual
project/service tracking and support cases are now implemented. See [current status](docs/STATUS.md) and the
[handoff](docs/DEVELOPMENT-HANDOFF.md) for the active increment and evidence.
No real packages are seeded.

## Responsibility Boundary

| Application | Responsibility |
|---|---|
| `adzbyte-next` | Primary public product listings, product details, add-to-cart controls, and a compact cart summary |
| `adzbyte-app` | Authoritative catalog, carts, registration, accounts, checkout, orders, payments, subscriptions, purchased products, customer service, REST APIs, administration, and audit history |

Next.js has no checkout action. Its **View Cart** action hands the customer to
the Laravel application, where the full cart, authentication, checkout, PayMongo
flow, and post-purchase account experience live. The app may later show product
listings inside the authenticated customer portal without creating a second
catalog source.

## Management Interfaces

Both management experiences use Filament 5 and require authentication:

- `/` — customer Dashboard, Projects, Services, Requests & reports, profile/security,
  and an ecommerce preview. Native attachments are deferred; share Drive links.
- `/admin` — administrator Dashboard, Customers, Projects & services, Customer
  support, catalog/order/payment tools, native package checkouts, user/role
  management and audit history. Subscription/purchased-entitlement work remains
  paused.

Filament is a PHP/Livewire server-driven UI framework. It is not React. The separate `adzbyte-next` application remains the React/Next.js public frontend.

## Backend Stack and Direction

- Laravel 13 and PHP 8.3
- Filament 5
- Spatie Laravel Permission and Filament Shield
- Laravel policies for record ownership and action authorization
- Laravel Sanctum for the versioned REST API and restricted integrations
- PayMongo one-time payments, subscription billing, and signed webhooks
- API-first storefront integration for `adzbyte-next`

The REST API is built under `/api/v1`. `adzbyte-next` consumes safe storefront
catalog APIs and narrow server-authenticated cart/handoff APIs. Filament remains
the account and administration UI, while shared Laravel services and policies
keep behavior consistent across panels, APIs, jobs, and webhooks.
Every customer-facing shop capability receives a versioned API contract even
when its initial app page uses the shared application service directly.

The API exposes authenticated customer identity at `GET /api/v1/me`, anonymous published catalog reads at `GET /api/v1/products` and `GET /api/v1/products/{public_id}`, and narrow [server cart operations](docs/api/storefront-carts.md) under `/api/v1/storefront/carts`. Success/error envelopes and examples are in [OpenAPI](docs/api/openapi.json); the [public catalog contract](docs/plans/2026-10-18-public-catalog.md) covers exact money, fields and ETags. Cart commands use their own proof-scoped durable receipts and fresh responses. The generic [idempotency boundary](docs/api/idempotency.md) remains available for later authenticated mutations; broad integration and webhook business endpoints remain absent.

## First Commerce Release

The approved first implementation is a narrow native Laravel vertical slice for
fixed-scope, one-time services. Each service line has a quantity of one, is
fulfilled manually, and requires administrator fit approval before payment is
enabled. Checkout uses PHP prices and PayMongo Hosted Checkout v2; the verified
webhook, not the browser return, confirms payment.

Physical goods, variants, inventory, shipping, downloads, appointments,
promotions, and subscription checkout are outside the initial slice. Monthly
care plans and custom project ranges remain enquiry-only until their separate
commercial rules are approved. Service packages are dynamic administrator-managed
records: add/edit content, scope, versioned prices/terms, publication,
availability, and archival without code changes. The catalog may start empty;
no package names or amounts are predefined. Tax/receipt treatment, approval,
payment, and refund/cancellation decisions remain required for their dependent
purchasing slices, and do not block generic catalog development.

See the [First Commerce Release Approach](docs/plans/2026-10-05-first-commerce-release.md)
for the scope, approval-gated journey, architecture boundaries, delivery order,
and remaining decisions.

## Documentation

For continued development, start with [Project Status](docs/STATUS.md) and the
[Development Handoff](docs/DEVELOPMENT-HANDOFF.md). The
[Backend Development Plan](docs/plans/2026-10-17-backend-development.md) defines
the backend-first task sequence, REST/access coverage, acceptance evidence, and
milestones. The [Decision Register](docs/decisions/commerce-development.md)
identifies unresolved inputs and the exact slices they block. Current verified
implementation is recorded separately from planned behavior in STATUS; public
registration is implemented; production commerce launch remains separately gated.

Every card and phase must pass the applicable [verification gates](docs/verification.md)
before completion or dependent implementation. CI runs the full suite on
SQLite and MySQL; phase-specific interface and integration proof is required
where applicable.

The current source of truth is the [Commerce and Customer Service Platform Plan](docs/plans/2026-10-05-commerce-platform.md).

That document defines application ownership, registration, cart handoff,
checkout, commerce records, PayMongo payments and subscriptions, purchased
products, requests, reports, APIs, and unresolved business decisions.

Implementation sequencing is tracked in the [Implementation Roadmap](docs/plans/2026-10-05-implementation-roadmap.md), while [Project Status](docs/STATUS.md) records the current state and the single next task for a fresh work session.

The [First Commerce Release Approach](docs/plans/2026-10-05-first-commerce-release.md)
narrows that roadmap into the approved native Laravel, one-time-service, manual
approval, and PayMongo Hosted Checkout path without treating unresolved
commercial rules as implemented behavior.

The [Management UI Branding Plan](docs/plans/2026-08-04-management-ui-branding.md)
defines how the authenticated Filament panels adapt Adzbyte's shared palette,
typography, logos, and contextual media without taking over public UI ownership
or depending on the `adzbyte-next` repository at runtime.

## Local Development

```bash
composer setup
composer run dev
```

Seed the repeatable application roles, then deliberately create or select the
local super administrator through Filament Shield's interactive command:

```bash
php artisan db:seed
php artisan shield:super-admin --panel=admin
```

Shield's role- and permission-mutating commands are disabled when the
application is running in the `production` environment. Customer accounts and
role assignments are never accepted from public application input.

Run the test suite with:

```bash
npm ci
npm run build
composer test
```

The native HTTP contract tests render built Blade pages and require the Vite
manifest. Both CI database jobs build assets before the suite.

## CI and Deployment

GitHub Actions verifies pull requests and `main`, but never promotes them
automatically. An explicitly authorized manual release workflow promotes the
verified `main` revision to a machine-managed `deploy` branch with compiled Vite
assets. Hostinger Business pulls only that branch for `app.adzbyte.com`.

See the [Hostinger Business deployment guide](docs/deployment/hostinger-business.md)
for repository settings, hPanel setup, production environment configuration,
post-pull commands, cron jobs, and release verification.

The [core operations and readiness runbook](docs/operations/core-readiness.md)
covers health signals, queue recovery, migration incidents, backup/restore
verification, credential compromise, and the exact boundary of deferred product
work.
