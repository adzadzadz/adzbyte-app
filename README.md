# Adzbyte App

`adzbyte-app` is Adzbyte's commerce backend, checkout application, customer
portal, and administrative system of record.

The reusable application foundation is complete: authentication, management
branding, customer and administrator Home foundations, API contracts,
idempotency, queues, operational hardening, and administrator user/role
management. Catalog, cart, checkout, orders, PayMongo payments, subscriptions,
purchased products, customer requests, and issue reports remain on the commerce
implementation roadmap.

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

- `/` — customers manage their account, cart, checkout, orders, subscriptions,
  purchased products, requests, reports, messages, and attachments.
- `/admin` — administrators manage catalog, customers, orders, payments,
  subscriptions, purchased products, customer-service cases, roles, and audit
  events.

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

The initial contract exposes authenticated customer identity at `GET /api/v1/me` through Sanctum. Its success and error envelopes are documented in the versioned [OpenAPI contract](docs/api/openapi.json). A persistence-backed [idempotency contract](docs/api/idempotency.md) is ready for future retry-prone authenticated mutations; product, integration, and webhook business endpoints remain intentionally absent.

## Documentation

The current source of truth is the [Commerce and Customer Service Platform Plan](docs/plans/2026-10-05-commerce-platform.md).

That document defines application ownership, registration, cart handoff,
checkout, commerce records, PayMongo payments and subscriptions, purchased
products, requests, reports, APIs, and unresolved business decisions.

Implementation sequencing is tracked in the [Implementation Roadmap](docs/plans/2026-10-05-implementation-roadmap.md), while [Project Status](docs/STATUS.md) records the current state and the single next task for a fresh work session.

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
composer test
```

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
