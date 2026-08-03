---
name: implement-feature
description: Use before writing application code for any adzbyte-app feature or fix involving Laravel, Filament, Livewire, models, migrations, policies, REST APIs, PayMongo, queues, notifications, files, or Hostinger. Enforces source-of-truth reading, shared service boundaries, authorization-first design, and proportionate verification.
---

# Implement Feature

## Before Code

1. Read `AGENTS.md` and `docs/STATUS.md`.
2. Read the exact relevant sections of:
   - `docs/plans/2026-08-04-experimental-launch-products.md`
   - `docs/plans/2026-08-04-implementation-roadmap.md`
3. Inspect the current code, migrations, routes, policies, tests, and package versions involved.
4. Check current official documentation before relying on unstable framework, package, provider, or API behavior.
5. Define acceptance behavior, states, authorization rules, failure paths, and verification before editing.

If the source of truth is silent and the choice materially changes product behavior, security, money, customer data, or architecture, ask the user and record the decision. Do not invent it.

## Architecture Rules

- Keep anonymous product UI in `adzbyte-next`; never add a public storefront here.
- Keep phase 1 customer and administrator management in authenticated Filament panels.
- Use one `User` model and one identity history.
- Put business workflows in application actions/services shared by Filament, REST controllers, jobs, and webhooks.
- Keep controllers, Filament resources, and jobs thin.
- Use Laravel policies for record authorization and Spatie permissions for capabilities.
- Scope customer records by authenticated ownership. Never trust a supplied customer ID as identity.
- Model payment, requirements, and fulfillment states separately.
- Expose named workflow actions instead of unrestricted status or payment CRUD.
- Record material transitions and actors in the order event timeline.
- Keep customer-visible communication separate from internal notes.

## Security and Integration Rules

- Validate requests through Form Requests or an equivalent explicit boundary.
- Serialize APIs through Resources; do not expose models directly.
- Use Sanctum abilities for service tokens and session authentication for first-party users as documented.
- Keep secrets and service tokens out of browser responses, logs, fixtures, and committed files.
- Verify PayMongo webhook signatures against the raw body.
- Make webhook and retry-prone operations idempotent.
- Treat redirects as informational and verified provider events as authoritative.
- Store uploads privately and authorize every download.

## Implementation Order

For each vertical slice:

1. Domain types, schema, and invariants
2. Factories and seed data needed by tests
3. Policies and permission definitions
4. Application action/service
5. Interface adapter: Filament, API, job, or webhook
6. Notifications and audit events
7. Automated tests
8. Documentation/status synchronization after verification

Avoid generating speculative CRUD or endpoints that are not used by the current slice.

## Verification

Select every applicable gate:

- Focused tests for the changed behavior
- `composer test` for the full PHP suite
- `vendor/bin/pint --test` for PHP formatting
- `npm run build` for asset changes
- Route inspection for route/middleware changes
- Migration and rollback verification on a safe test database
- Panel tests for visibility and actions
- API tests for validation, response contract, authentication, ownership, rate limiting, and idempotency
- Webhook tests for invalid signatures, duplicates, retries, and out-of-order events

Test successful behavior and forbidden behavior. A feature is not complete until the relevant gates pass and the roadmap/status reflect reality.

Do not commit, push, deploy, or mutate production systems unless the user explicitly requests it.
