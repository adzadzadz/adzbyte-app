# Adzbyte App Implementation Roadmap

| Field | Value |
|---|---|
| Status | In progress — foundation F, branding M0, customer Home M1.1, and administrator Home M2.1 complete |
| Product source of truth | `docs/plans/2026-08-04-experimental-launch-products.md` |
| Progress tracker | `docs/STATUS.md` |
| Application | Laravel 13 / PHP 8.3 |
| Management UI | Filament 5 authenticated root `/` customer panel and `/admin` |
| API prefix | `/api/v1` |

## Delivery Rules

- Implement phases in dependency order; do not generate broad CRUD before the underlying workflow and policies exist.
- Keep business transitions in application services/actions shared by Filament, REST controllers, jobs, and webhooks.
- Add policies and ownership tests with each protected model instead of postponing authorization.
- Use explicit enums or equivalent typed state objects for payment, requirements, and fulfillment statuses.
- Make retry-prone mutations idempotent and record important transitions in `order_events`.
- Update the product plan for durable decisions and `docs/STATUS.md` after every completed task.
- Defer the product/catalog discussion and D1 product-specific implementation until the authenticated application core is functional, per the 2026-08-04 sequencing decision.

## Phase F — Foundation

### F1. Framework and access foundation

- [x] Install Filament 5 and scaffold the `admin` panel.
- [x] Add the separate `account` panel.
- [x] Install Laravel Sanctum with API routing support.
- [x] Install Spatie Laravel Permission.
- [x] Install Filament Shield and configure `App\Models\User` as the auth provider.
- [x] Add `HasApiTokens` and `HasRoles` to the shared `User` model.
- [x] Implement panel entry rules for `customer`, `administrator`, and `super_admin`.
- [x] Seed roles and a deliberate local super-administrator path without open role assignment.
- [x] Add tests proving guests cannot enter either panel and roles cannot enter the wrong panel.

**Gate:** both panels boot, authentication is required, migrations pass, role seeding is repeatable, and `composer test` is green.

### F2. Authentication lifecycle

- [x] Enable login, logout, password reset, email verification, and profile management.
- [x] Disable open application registration.
- [x] Define the signed activation/set-password handoff used after checkout creates or identifies a customer.
- [x] Add rate limits and audit-sensitive authentication events.
- [x] Test activation expiry, existing-account checkout, verification, and unauthorized panel access.

**Decision resolved:** new customers receive a single-use 24-hour signed activation link after verified payment; existing emails must authenticate before checkout continues. Production origins are `https://adzbyte.com` and `https://app.adzbyte.com`.

## Phase D — Domain and Authorization

### D1. Core records

- [ ] Products and versioned product entitlements
- [ ] Orders with opaque public identifiers
- [ ] Payment records and PayMongo references
- [ ] Order briefs with questionnaire snapshots and revisions
- [ ] Order messages and private internal notes
- [ ] Attachments
- [ ] Order drafts and draft feedback
- [ ] Correction requests
- [ ] Sites and lifecycle records
- [ ] Content items
- [ ] Append-only order events

### D2. Workflow state machines

- [ ] Separate payment, requirements, and fulfillment statuses.
- [ ] Centralize allowed transitions in application services/actions.
- [ ] Record actors, timestamps, reasons, and transition metadata.
- [ ] Implement first-draft SLA start, pause, resume, and completion calculations.
- [ ] Add tests for valid, invalid, repeated, and concurrent transitions.

### D3. Policy matrix

- [ ] Customer ownership policies for every customer-visible record.
- [ ] Administrator capability permissions by business action.
- [x] Super-administrator override through Laravel Gate.
- [ ] Explicit authorization for submit, approve, correct, reconcile, refund, publish, archive, and reactivate actions.
- [ ] Cross-customer access-denial tests for panels and APIs.

## Phase M — Management Panels

### M0. Shared management visual foundation

- [x] Implement the dark-first token, typography, asset, and component foundation from the [Management UI Branding Plan](2026-08-04-management-ui-branding.md).
- [x] Self-host Poppins and translate the source palette into reusable Filament tokens, keeping brand accents separate from semantic statuses.
- [x] Copy and optimize the full wordmark and square mark, record them in an asset manifest, and configure panel identity without a runtime dependency on `adzbyte-next`.
- [x] Brand login, activation, password reset, email verification, profile entry points, and the shared `/` and `/admin` shells.
- [x] Establish shared interactive, validation, loading, empty, and status states before screen-specific decoration.
- [x] Review representative desktop and `320px` mobile renders and verify contrast, keyboard focus, zoom-safe responsive layout, reduced motion, alternative text, production build, panel access, and authorization tests.

**Gate:** the branding plan's acceptance criteria pass for both panels, selected media is locally owned by the build, and no anonymous UI or runtime repository coupling is introduced.

### M1. Customer root `/` panel

- [x] First-party Home and navigation foundation with authenticated account context and an honest pre-domain empty state
- [ ] Purchase and order dashboard
- [ ] Structured brief with autosave and completion checklist
- [ ] Attachment management
- [ ] Asynchronous order conversation
- [ ] Draft preview, feedback, approval, and correction request
- [ ] Product-entitlement controls and content items
- [ ] Site details, hosting dates, credentials, and reactivation requests
- [ ] Notifications and order timeline

### M2. Administrator `/admin` panel

- [x] First-party Overview and navigation foundation with authenticated access context and no speculative operational data
- [ ] Operational dashboard and SLA queue
- [ ] Customer, order, and payment management
- [ ] Brief review and missing-information requests
- [ ] Customer conversation and attachment review
- [ ] Draft delivery and feedback handling
- [ ] Fulfillment and site lifecycle management
- [ ] Roles, permissions, and user administration
- [ ] Audit event visibility with customer/internal separation

**Gate:** panel feature tests cover visibility, allowed actions, forbidden actions, ownership, validation, and the primary workflow.

## Phase A — REST API

### A1. Contract foundation

- [ ] Create `/api/v1` route groups for customer, integration, and webhook surfaces.
- [ ] Define a consistent resource envelope, pagination, UTC timestamps, and error format.
- [ ] Use Form Requests, API Resources, policies, and route-model authorization.
- [ ] Add separate rate limits and idempotency handling for retry-prone mutations.
- [ ] Maintain an OpenAPI contract and contract tests.

### A2. Customer API

- [ ] Identity and notification preferences
- [ ] Orders and timelines
- [ ] Brief autosave and submission
- [ ] Messages and attachments
- [ ] Drafts, feedback, approval, and corrections
- [ ] Product controls, content items, sites, and reactivation requests

### A3. Restricted Next.js integration API

- [ ] Dedicated revocable service token with explicit abilities.
- [ ] Product/catalog read endpoints required by the public UI.
- [ ] Idempotent checkout-initiation endpoint.
- [ ] Tests proving the service principal cannot call customer or administrator actions.

**Gate:** no endpoint trusts a supplied customer ID as identity, and every protected record lookup has an ownership or capability test.

## Phase P — Payments

- [ ] Create PayMongo Hosted Checkout sessions from trusted server-to-server requests.
- [ ] Store internal order, session, payment, event, amount, currency, and reference identifiers.
- [ ] Verify `Paymongo-Signature` against the raw webhook body.
- [ ] Process webhook events idempotently and tolerate retries or reordering.
- [ ] Treat return pages as informational rather than payment proof.
- [ ] Reconciliation, failed-payment, expiration, and approved refund workflows.
- [ ] Queue account activation and payment/brief-ready notifications.

**Decision required:** enabled payment methods and refund rules.

## Phase C — Collaboration and Fulfillment

- [ ] Product-specific, schema-versioned brief definitions.
- [ ] Brief completeness rules and formal submission.
- [ ] Asynchronous messaging, read state, and automated acknowledgements.
- [ ] Private uploads with allowlists, limits, temporary URLs, and security status.
- [ ] Immutable draft versions and consolidated feedback.
- [ ] Approval, included-correction consumption, and out-of-scope quote handling.
- [ ] SLA visibility and overdue alerts in both panels.
- [ ] Manual fulfillment checklist and publication quality gate.

**Decision required:** questionnaire content, attachment limits, operating hours, and notification expectations.

## Phase H — Hosting Automation

- [ ] Hostinger capability and plan verification.
- [ ] Queued provisioning with safe retries and status polling.
- [ ] WordPress install/template clone and static-site provisioning adapters.
- [ ] Preview-domain retrieval, content application, smoke checks, and publication.
- [ ] Site credential delivery, archive, reactivation, and deletion workflows.
- [ ] Manual fallback for unsupported operations and customer hPanel access.

**Decision required:** hosting plan/API availability, domain strategy, credential rules, and retention lifecycle.

## Phase Q — Production Readiness

- [ ] Full authorization and cross-account isolation audit.
- [ ] Webhook replay, duplicate, invalid-signature, and out-of-order tests.
- [ ] Upload security and private-file access audit.
- [ ] Queue retry, failed-job, notification, and monitoring setup.
- [ ] Database indexes and query review for operational queues and timelines.
- [ ] Backup, retention, privacy, takedown, and incident procedures.
- [ ] End-to-end checkout-to-publication acceptance test.
- [ ] Production configuration checklist with secrets kept outside the repository.

## Definition of Done for Every Task

- Acceptance behavior is documented and implemented.
- Authorization and ownership are tested.
- Relevant focused tests and `composer test` pass.
- PHP formatting checks pass when PHP changes.
- Asset builds pass when frontend assets change.
- Migrations work from a clean test database and remain reversible where practical.
- No secrets, service credentials, or customer data are committed.
- Relevant roadmap boxes and `docs/STATUS.md` reflect reality.
