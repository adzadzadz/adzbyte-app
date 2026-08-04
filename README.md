# Adzbyte App

`adzbyte-app` is the authenticated management and backend application for Adzbyte's experimental launch products.

The repository is in active implementation. The framework, access, authentication lifecycle, management branding, and first-party customer and administrator Home foundations are complete; domain, payment, management-resource, and fulfillment features remain on the implementation roadmap.

## Responsibility Boundary

| Application | Responsibility |
|---|---|
| `adzbyte-next` | Public campaign pages, product presentation, purchase calls to action, and payment return pages |
| `adzbyte-app` | Customer and administrator management, REST API, accounts, orders, briefs, messaging, drafts, payments, fulfillment, sites, notifications, and audit history |

`adzbyte-app` does not render an anonymous product catalog or campaign landing page.

## Management Interfaces

Both management experiences use Filament 5 and require authentication:

- `/` — customers enter the authenticated dashboard and manage purchases, briefs, files, messages, drafts, approvals, and purchased product controls.
- `/admin` — administrators manage customers, payments, reviews, conversations, drafts, fulfillment, sites, roles, and audit events.

Filament is a PHP/Livewire server-driven UI framework. It is not React. The separate `adzbyte-next` application remains the React/Next.js public frontend.

## Backend Stack and Direction

- Laravel 13 and PHP 8.3
- Filament 5
- Spatie Laravel Permission and Filament Shield
- Laravel policies for record ownership and action authorization
- Laravel Sanctum for the versioned REST API and restricted integrations
- PayMongo Hosted Checkout and signed webhooks
- Manual phase 1 fulfillment with later Hostinger automation

The REST API will be built under `/api/v1` alongside the Filament features. Filament remains the phase 1 management UI; the API is prepared for later selective use by `adzbyte-next`.

## Documentation

The current source of truth is [Experimental Launch Products — Product and System Plan](docs/plans/2026-08-04-experimental-launch-products.md).

That document defines the product scope, system boundary, authentication and RBAC model, REST API contract, post-payment brief, asynchronous messaging, first-draft SLA, payment flow, and hosting plan.

Implementation sequencing is tracked in the [Implementation Roadmap](docs/plans/2026-08-04-implementation-roadmap.md), while [Project Status](docs/STATUS.md) records the current state and the single next task for a fresh work session.

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
