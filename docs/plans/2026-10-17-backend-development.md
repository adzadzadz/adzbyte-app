# Backend Development Plan

| Field | Value |
|---|---|
| Created | 2026-10-17 |
| Status | C1.3 locally/browser verified; source CI pending; C1.4 next after qualification |
| First target | Laravel backend, REST contracts, and Filament management |
| Product authority | [Commerce platform](2026-10-05-commerce-platform.md) |
| Scope boundary | [First commerce release](2026-10-05-first-commerce-release.md) |
| Master checklist | [Implementation roadmap](2026-10-05-implementation-roadmap.md) |
| Current checkpoint | [Development handoff](../DEVELOPMENT-HANDOFF.md) |
| Decision gates | [Decision register](../decisions/commerce-development.md) |

## Outcome and boundaries

Build the authoritative backend needed to manage users, products, carts,
orders, payments, purchased services, and customer communication. Deliver a
versioned REST contract for every customer-facing shop capability and usable
Filament management screens alongside each domain slice. Backend readiness is
the first target; the complete commerce launch also requires the real Next.js
integration and end-to-end acceptance already defined in the product plan.

`adzbyte-next` owns public discovery, add-to-cart, and the compact cart summary.
`adzbyte-app` owns identity, full cart, checkout, all business records, account
pages, administration, and provider processing. Customer pages stay in the `/`
panel and administrator pages in `/admin`.

The first purchase path remains fixed-scope, one-time services, quantity one
per line, manual fulfillment, human fit approval before payment, and PHP
pricing through PayMongo Hosted Checkout v2. Subscriptions, physical goods,
variants, shipping, downloads, appointments, and promotions remain deferred.
This plan authorizes no production release, migration, or data change.

## Verified starting point

Repository inspection on 2026-10-17 at `e08f6b0` found:

- Authentication, verification, activation, profiles, customer/admin Home,
  roles/policies, and administrator user list/edit management already exist.
  User creation, deletion, password administration, and unrestricted staff
  promotion are not existing management features.
- API route groups, stable errors, Sanctum identity at `GET /api/v1/me`, an
  OpenAPI contract, and dormant authenticated idempotency middleware exist.
- Health, queue monitoring, retry controls, CI, and recovery documentation exist.
- No commerce models, migrations, or business endpoints exist. Public
  registration is not implemented. Integration/webhook route files are empty.
- The last recorded full suite is 71 tests / 345 assertions; it was not rerun
  for this documentation change. Historical dependency versions and production
  claims must be rechecked when relevant to implementation or release.

Preserve these foundations. Extend shared actions/services and policies rather
than replacing authentication or duplicating behavior between panels and APIs.

## Delivery sequence and task cards

Task IDs below refine existing roadmap phases; the master roadmap remains the
completion checklist. C1.1 is locally verified; remaining implementation cards
are unstarted. Resolve only the
decisions needed by the next card. Do not turn an unresolved rule into a default.

### P0 — Superseded: predefined launch catalog gate

The owner clarified on 2026-10-17 that no specific service packages exist yet.
Packages, descriptions, scope, prices, and terms must be manageable data.
The former requirement to approve launch products/amounts before C1 is retired;
D01 is resolved and D03's basic controls are specified in the product plan.

Continue to C1.3 with an empty catalog and synthetic test data. D02 tax/receipt
decisions move to checkout/sales readiness; no tax policy is invented. The
remaining cart, payment, and fulfillment decisions still gate their own slices.

### C1 — Catalog, prices, entitlements, and product administration

**Dependency:** confirmed dynamic-catalog requirements in the product plan;
no real packages or prices required. **Management:** `/admin` catalog and pricing.

1. **C1.1 — Domain and persistence.** First specify the schema, field allowlists,
   relationships, indexes, lifecycle transitions, and acceptance tests. Then add
   data-driven service metadata/scope/media, stable identifiers, draft/published/
   archived lifecycle, separate availability, immutable optional price versions
   in integer PHP minor units, and versioned entitlement/fulfillment terms.
   Drafts may be incomplete; unset price is never treated as free. Include
   empty-catalog behavior and local migration reversal.
2. **C1.2 — Shared actions and policies.** Implement catalog changes and
   explicit publication/price-version actions with capabilities and audit
   evidence. Catalog edits must not rewrite accepted historical snapshots.
3. **C1.3 — Filament management.** Let authorized administrators create and edit
   packages, scope/deliverables, media, price/term versions, publication,
   availability, and archival using the same actions/policies. New packages
   start as drafts; adding a package requires no code change or deployment.
   Retain referenced products/versions rather than deleting purchase history.
   Reuse existing user/role management; add no general user-management API.
4. **C1.4 — Public REST contract.** Add safe product list/detail responses,
   current price/availability, pagination, cache behavior/invalidation, and
   throttling. Add synthetic fixtures, OpenAPI examples, and consumer checks.

**C1.1 evidence (2026-10-18):** [domain specification](2026-10-18-catalog-domain.md),
38 focused tests / 71 assertions; full suite 109 / 416; formatting and Composer
validation passed. Disposable SQLite apply/rollback/reapply passed. No existing
database was migrated, no real content seeded, and no panel/API exposed. Local
PHP was 8.5.11; CI PHP 8.3 and MySQL execution are separate verification facts.
Model-level guards do not replace C1.2 authorization, locks, and audit history.
Original source `284dcbb` failed CI at the old PHP dependency audit (11 advisories
/ 5 packages), before tests. The owner-authorized compatible Composer/npm
remediation at `a27f834` now passes [CI run 39048996685](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048996685)
on PHP 8.3.35: both audits clear, 109 tests / 416 assertions, build/cache checks
pass. This qualifies C1.1 on PHP 8.3. No C1.2–C1.4 behavior or production change
was added by maintenance.

**C1.2 evidence (2026-10-18):** [action contract](2026-10-18-catalog-actions.md),
capability-backed policy, shared actor-authorized transactional actions,
append-only audit receipts, immutable revision allocation, and retry safety.
Full local suite: 154 tests / 660 assertions on PHP 8.5.11, including five
four-process SQLite concurrency cases. Pint, Composer validation, and isolated
audit-migration apply/rollback/reapply pass. Source `cea2199` passes
[CI run 39050287698](https://github.com/adzadzadz/adzbyte-app/actions/runs/39050287698)
on PHP 8.3.35 with the same 154 / 660, both audits (0 findings), frontend build,
and production caches. MySQL runtime is unverified. No existing database, panel, REST
contract, or production environment changed. Resume with C1.3; C1.4/B1 pending.

**Stronger completion gate qualified (2026-10-18):** source `6697e81` passes
[CI run 39051237331](https://github.com/adzadzadz/adzbyte-app/actions/runs/39051237331)
with 158 tests / 703 assertions separately on SQLite and MySQL 8.0 under
PHP 8.3.35, including actual selected-engine races and populated migration
upgrade/rollback/reapply. See the [gate/evidence matrix](../verification.md#current-catalog-qualification).
This closes the earlier MySQL limitation; C1.3 remains next.

**C1.3 local/browser evidence (2026-10-18):** [management contract and screenshots](2026-10-18-catalog-management.md).
Administrator list/create/view/edit pages and named shared-service actions,
locked retry UUIDs, independent capabilities, exact decimal pricing, safe media,
structured-data preservation, and readonly history. Focused 25 / 309; full local
183 / 1,012 on PHP 8.5.11; Pint, Composer validation, build, routes, and real
1440px/320px browser workflows pass. Both PHP 8.3 SQLite/MySQL CI jobs are pending
source delivery; C1.3 remains partial until qualified. C1.4/B1 remain pending.

**Acceptance:** an empty catalog returns an empty list; administrators can add,
edit, publish/unpublish, enable/disable, and archive multiple synthetic packages
without code changes. Incomplete/unpriced packages cannot be purchased; no real
package or price is seeded. Public reads never expose drafts, archived products,
or internal fields; unauthorized
catalog writes fail in both services and panels; invalid money/publication
changes fail; updates invalidate public caches; price/entitlement history is
preserved, including referenced archived records. Migration and contract tests
pass. Next.js can consume verified
responses without importing Laravel internals.

**Backend milestone B1:** administrator can manage the catalog and a test client
can read its safe REST representation. The roadmap's real Next.js rendering
gate remains pending until integration is demonstrated in that repository.

### C2 — Carts and secure storefront handoff

**Dependency:** C1 and D04. **Management:** app-owned full-cart page; limited
staff inspection only if an approved support need exists.

1. **C2.1 — Cart service.** Add anonymous/customer carts, server-calculated
   quantity-one lines, availability/price revalidation, expiry, and approved
   multi-service behavior. Never accept client totals or arbitrary owners.
2. **C2.2 — Restricted REST access.** Add revocable server credentials scoped to
   cart/summary/handoff operations. Bind each request to its cart authority;
   possession of the Next.js service token alone must not expose all carts.
   Specify retry identity for service-token and anonymous operations: the
   current authenticated middleware is not automatically a cart boundary.
3. **C2.3 — Handoff and full cart.** Mint and atomically consume an opaque,
   short-lived single-use handoff bound to the cart and allowed destination;
   show the full cart in the app before authentication.

**Acceptance:** expiry, replay, tampering, cross-cart access, excessive token
abilities, changed prices, unavailable services, duplicate retries, and
concurrent line/handoff operations are covered. Browser bundles contain no
service credential. Document cookie, CSRF/origin, return-destination, and token
rotation behavior before wiring the actual storefront.

### C3 — Public registration and cart ownership

**Dependency:** C2 and the D04 attachment/merge rules. This completes the public
registration item also listed under roadmap F1; it is one implementation.

1. **C3.1 — Registration.** Add app-owned public customer registration through
   shared identity actions, server-assigned roles, verification, generic
   duplicate-identity handling, and abuse throttles. Preserve trusted activation.
2. **C3.2 — Cart attachment.** Preserve and attach/merge the cart through login,
   registration, verification, password recovery, and session rotation only
   after the identity and ownership requirements are satisfied.

**Acceptance:** direct registration works without a cart. Role injection,
enumeration, duplicate identity, session fixation, unverified payable-order
creation, and attaching another customer's cart fail. Existing panel/auth tests
remain green. Specify the identity/bootstrap API contract without moving
account pages into Next.js or introducing unrestricted token issuance.

**Backend milestone B2:** a test client can build a cart, hand it to the app,
register/login, and retain the correct owned cart.

### O1 — Orders, checkout brief, and fit approval

**Dependency:** C3, D02 tax/receipt decisions, D05, any applicable D03 purchase
constraints, and relevant terms/retention decisions from D08.

1. **O1.1 — Order domain.** Persist opaque references, immutable product/price/
   scope/terms snapshots, totals, and append-only events. Model approval,
   order, payment, and fulfillment states separately with allowed transitions.
2. **O1.2 — Checkout contract.** Add owned review/brief submission and idempotent
   cart-to-order conversion through shared services, panel pages, and REST.
3. **O1.3 — Staff review.** Add authorized approve/reject/request-correction
   actions, actor/time history, and approved expiry/notification rules. Material
   changes require customer acceptance again before payment.

**Acceptance:** repeat/concurrent checkout creates one order; catalog changes
cannot rewrite its snapshot; unauthorized transitions and cross-owner reads
fail; correction/rejection cannot accidentally enable Pay Now. Tests cover the
approved transition table through both panel and REST entry points.

### O2 — PayMongo payment processing

**Dependency:** O1 and D06, test-mode credentials, current official provider
contract verification, and the applicable D08 operational decisions.

1. **O2.1 — Payment attempts.** Create provider sessions only for persisted,
   owned, approved snapshots; store mode, amount, currency, provider IDs, and
   reconciliation references. Handle timeout/retry without duplicate charging.
2. **O2.2 — Webhooks.** Verify raw-body signatures, durably deduplicate events,
   validate order/amount/currency/mode, and process delayed/out-of-order events
   through allowed transitions. Browser returns remain informational.
3. **O2.3 — Operations.** Add policy-backed payment/event/reconciliation views
   and the approved failure, expiry, cancellation, dispute, and refund handling.
   Define recovery and retries for provider-success/local-failure cases.

**Acceptance:** mocked contract/failure tests plus a separately recorded
PayMongo test-mode payment demonstrate one authoritative paid transition.
Invalid signatures, mismatches, replay, redirects, and concurrent processing
cannot grant access. Provider-dependent verification remains explicitly pending
if test credentials or callbacks are unavailable; never substitute mock success.

### O3 + S2 — Order portal and purchased-service fulfillment

**Dependency:** O2 and D07 fulfillment/access rules.

- Add customer-owned order/payment/timeline/approved receipt representations,
  administrator operations, and material-event notifications.
- Create one purchased-service record per paid item using snapshotted terms;
  make creation retry-safe and expose owned list/detail REST contracts.
- Add explicit manual fulfillment actions and histories. Confirmed payment
  must not imply completed service delivery. Apply approved refund/access rules.

**Acceptance:** duplicate events cannot duplicate purchases or notifications;
unauthorized reads/transitions fail; historical terms stay stable. Paid service
and fulfillment state appear consistently in the portal, API, and admin panel.

**Backend milestone B3:** the one-time service path works through test payment
and purchased-service management. It is not the complete commerce launch gate.

### R1 — Requests, reports, and staff communication

**Dependency:** purchased-service linkage where applicable and D07/D08.

- Add distinct request/report types with owned cases, public references,
  messages, staff assignment, internal notes, and append-only activity.
- Keep account/payment/privacy/security reporting possible without an active
  purchased service. Implement approved states, categories, and notifications.
- Add private attachments only after their file/access/retention rules are
  resolved; document any approved deferral rather than silently omitting scope.
- Add customer and staff panel workflows and versioned customer contracts.

**Acceptance:** customers open and follow their own cases; staff triage,
respond, and resolve them; internal notes and other customers' records never
appear in customer payloads, counts, searches, notifications, or downloads.

### Q — Integration and launch qualification

**Dependency:** C1–R1 and launch decisions in D08.

- Coordinate the separately owned Next.js changes against verified contracts:
  catalog rendering, compact cart, secure cookies/server token, and handoff.
- Demonstrate the actual public-product → cart → app authentication → brief →
  approval → PayMongo test payment → webhook → purchased service → support
  request/report → staff response path. Record environment and commit IDs.
- Complete applicable roadmap Q checks: cross-account/capability boundaries,
  query/index review, private uploads if included, queue failures, provider
  recovery, backup restore, retention, accessibility, and responsive workflows.
- Produce a release candidate with configuration/migration inventory, rollback
  compatibility, known limits, and evidence. Production promotion remains a
  separately authorized operation; document post-release verification there.

**Exit:** backend acceptance and the real integrated journey both pass, or their
remaining blockers are explicit. Subscription Q checks are deferred with S1 and
must not be marked passed for this one-time-service launch.

## REST delivery matrix

These are planned operation families, not live routes or finalized URLs. Freeze
method/path/schema/auth/error/idempotency details in OpenAPI with each slice.
Keep the published contract limited to implemented behavior.

| Slice | Operations | Access and authority |
|---|---|---|
| Existing | `GET /api/v1/me` | Verified customer via Sanctum; identity only |
| C1 | Product list/detail and current safe price/availability | Public, rate-limited, cacheable; no drafts/internal metadata |
| C2 | Cart create/read/mutate/summary; mint handoff | Next.js server token with narrow abilities plus cart authority; no browser secret |
| C2/C3 | Consume handoff; owned cart read/mutate/attach | App web/session and documented cart proof; verify identity before ownership attachment |
| C3 | Registration and identity lifecycle needed by clients | App-owned bootstrap; customer-only role, CSRF/session and throttling rules documented |
| O1 | Review/brief, submit order, correction/acceptance, owned order reads | Verified owner, action policies, snapshot validation, retry protection |
| O2/O3 | Start allowed payment; read payment/order status and receipt | Verified owner; provider return cannot change payment state |
| O2 | Provider payment webhook | Raw-body signature and durable event deduplication; no customer session |
| S2 | Purchased-service list/detail and allowed actions | Verified owner plus current snapshotted entitlement |
| R1 | Request/report create/read, messages, allowed downloads | Owner and policy checks; customer-safe fields only |

Administrator-only actions may stay panel-only. Add an integration/admin API
only for a defined consumer with explicit capabilities. Keep public catalog,
anonymous bootstrap, customer, integration, and webhook middleware boundaries
separate; do not put public operations inside the existing verified-customer group.

## Verification and completion evidence

The owner's [phase verification policy](../verification.md) is mandatory for
every card and aggregate phase. Required pending/failed checks keep the card
partial and block dependent implementation. Record explicit applicability;
never treat a skipped check or an unrelated green CI run as proof.

For each card, record acceptance cases and test names before implementation,
then add behavior and forbidden-path tests. Use synthetic data in a disposable
test database; never reset or seed a valuable database to satisfy a test.

| Change | Required evidence |
|---|---|
| Documentation only | Link/path checks, consistency review, `git diff --check` |
| PHP behavior/API/policy | Focused cases, full `composer test`, `vendor/bin/pint --test`, contract alignment |
| Persistence | Clean/upgrade apply and rollback/reapply, history/FK/uniqueness, real concurrency/retry cases on SQLite and MySQL; compatibility notes |
| Panel/assets | Build and focused interaction/responsive checks for new workflows |
| Provider | Mocked failure cases plus recorded test-mode callback/payment proof; no live charge |
| Release candidate | Existing CI gates and applicable Q acceptance, backup/recovery/configuration evidence |

Existing CI additionally validates Composer, audits locked PHP/npm dependencies,
builds Vite assets, and generates Laravel production caches. Run checks in an
isolated local/CI environment; cache commands may mutate local runtime files.
Do not claim green CI, remote state, or production health from old notes.

A card is complete only when its acceptance evidence exists, the corresponding
master-roadmap items can be checked truthfully, and status/handoff are updated.
A partial implementation stays partial even when committed. Keep source commit,
CI result, release promotion, migration, and live verification as separate facts.

## Continuity documents

- [STATUS](../STATUS.md): short current state, blocker, and one next task.
- [Development handoff](../DEVELOPMENT-HANDOFF.md): active task, exact resume
  action, changed files, checks, Git/runtime state, and session checkpoints.
- [Decision register](../decisions/commerce-development.md): pending gates and
  links to owner-approved decisions in the product plan.
- [Master roadmap](2026-10-05-implementation-roadmap.md): verified phase progress.

Update the handoff at meaningful checkpoints and before stopping, including
when no code was completed. Resume by reconciling it with the real checkout;
never rely on conversation history alone. Keep credentials, customer data,
database dumps, and provider payloads containing sensitive data out of records.
