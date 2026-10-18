# Independent Laravel code checkup

Date: 2026-10-18. Baseline: `d902fa853d7475344e11b503377084685a25f556`.
Framework: locked Laravel **13.37.0**, Filament **5.10.2**, supported PHP **8.3**.
Owner requested a full independent check before remaining phase implementation.

## Review decision and coverage

Independent `code_reviewer` agent `laravel_checkup` reproduced four defects
without editing repository files. The baseline required changes. The reviewer
inspected the corrective code, regression tests and consolidation and approved
them **subject to current full tests and supported-runtime SQLite/MySQL CI**.
This report is not a guarantee that every possible defect has been eliminated.

| Tracked baseline area | Files including keepfiles | Coverage |
|---|---:|---|
| `app/` | 153 | Actions/services, models, policies, FormRequests, Resources, middleware, controllers, Filament pages/actions, providers, commands, jobs and notifications |
| `database/` | 22 | Migrations/constraints, historical revisions, protected populated rollback, factories and seeders |
| `routes/` and `bootstrap/` | 9 | Sessions/stateless API groups, CSRF/auth/verification, origins, throttles, exception responses and scheduling |
| `config/` | 15 | Sanctum/Shield, database/cache/session/queue, storage/logging and services |
| `resources/` | 21 | Auth/cart/checkout/order/admin views, escaping, handoff JavaScript and headers |
| `tests/` and `.github/` | 72 | Risk-based coverage review, isolated databases/workers, races/migrations, real HTTP/SMTP contracts and supported-runtime CI/release approach |

Generated public assets and third-party code were excluded from the authored-code
review. Installed framework source was inspected where native behavior mattered.
Tests were reviewed by risk and coverage, not assertion-by-assertion. Production,
the separate Next.js checkout, merchant configuration and live provider behavior
were not audited or changed by this task.

## Confirmed findings and resolutions

### LR01 — P1: private terms in customer order projections

Baseline `OrderResource` and `OrderReviewResource` returned complete captured
service terms. The public catalog already exposed only six string fields.
Independent cart → attach → checkout → submit reproduction showed `internal_note`
and nested entitlement metadata hidden by the catalog but exposed in owned-order
JSON and native customer views.

Resolution: native `ProductTermsResource` supplies one shared allowlist for
catalog, accepted order lines and material proposals: scope, deliverables,
exclusions, customer obligations, fulfillment and entitlement descriptions.
Only string values are public. Full immutable database snapshots remain intact.
Empty terms retain JSON object shape. No customer ownership rule was relaxed.

Regression: `OrderReviewAdaptersTest::test_order_and_proposal_projections_hide_internal_terms_without_changing_snapshots`
failed before the fix; passes afterward for owned JSON/native pages, material
proposals, acceptance, preserved public scope and preserved private snapshots.
The actual HTTP/OpenAPI Node consumer additionally checks private synthetic
metadata never appears in checkout, submitted/read/review/proposal/acceptance
responses or the native proposal page.

### LR02 — P1: activation email race

The FormRequest checked the signed email hash before locking. The action
reloaded the user but checked only customer role and verification status. A
concurrent email change could therefore let the old link set a password and
verify the changed address. The independent reviewer reproduced this on an
isolated in-memory database.

Resolution: pass the actual signed route hash into the action and compare it
with the locked user's current verification email before password or verification
mutation. Laravel signed middleware, validation, hashing, events and login remain
the underlying primitives.

Regression: `AuthenticationLifecycleTest::test_activation_rejects_an_email_changed_after_the_link_was_authorized`
failed before the fix; passes afterward. Its transaction-entry hook deterministically
changes the email after request authorization. Password, verification, authentication
and activation/verification events remain unchanged on denial. Existing valid,
expired, role, one-use, rate-limit and cart activation tests remain required.

### LR03 — P2: managed-user stale authorization

The action authorized a possibly stale target before locking and did not repeat
`update` authorization against the locked target. The reviewer reproduced a
regular administrator editing a loaded customer after a second model promoted
that account to super administrator, supplying the already-promoted role set.
This proves the shared service defect; no public Livewire exploit was established.

Resolution: reload the actor and use Laravel Gate to reauthorize against the
locked target before saving its identity or processing role changes.

Regression: `AdminUserManagementTest::test_identity_updates_reauthorize_a_target_promoted_after_it_was_loaded`
failed before the fix; passes afterward and verifies identity and protected role
are preserved. Existing delegated identity updates and role-assignment denials pass.

### LR04 — P2, latent: idempotency target omitted

The reusable middleware scoped keys by named route/pattern and fingerprinted
body/query/files without the concrete path. Same-key/same-body mutations to two
resources on one parameterized route could replay the first resource's response.
No current production route attaches this middleware; current domain receipts
already include resource identity.

Resolution: include the concrete path in the request fingerprint. Reuse against
a different target conflicts; exact-target canonical retries still replay.

Regression: `ApiIdempotencyTest::test_a_key_cannot_replay_a_different_resource_on_the_same_parameterized_route`
failed with HTTP 201 instead of 409 before the fix; passes afterward, including
same-target replay and independent second-target execution with a fresh key.
There is no active middleware rollout or persisted-record migration in this task.

## Native framework use and justified extensions

Laravel routing, FormRequests/Validator, JsonResources, Eloquent relations/casts,
migrations/constraints, policies/Gates, Sanctum, sessions, signed URLs, queued
jobs/Notifications, `afterCommit`, scheduler and Artisan are extensively used.
Shared actions/services and directory choices are project conventions; Laravel
does not require a universal business-layer architecture or a broad rewrite.

Five repeated bounded transaction loops were consolidated into
`app/Support/Database/RetryingTransaction.php`. Catalog, category, cart, handoff
and order callers share Laravel's `DB::transaction` and native concurrency error
detector. The locked framework implementation supports native attempt counts,
but retries immediately without a delay option. Prior real SQLite process tests
justify the narrow backoff extension. Five attempts, 10/20/40/80ms delays, nested
transaction ownership and unique-constraint receipt recovery remain unchanged.

Other retained custom behavior has application requirements beyond framework
primitives: durable event/recipient notification receipts and recovery, immutable
commercial snapshots and command UUID receipts, exact large minor-unit totals,
anonymous cart authority/handoffs and owner-scoped merges. Notification transport
remains at-least-once if SMTP accepts before a process dies before receipt commit.

Direct commerce permission checks intentionally enforce the recorded fresh,
verified staff identity and exact-capability contract even under a global
`Gate::before` override. Replacing them with `can()` would broaden access.
The reviewer withdrew that proposed change after checking the actual contract.
Some generic Shield affordances can still differ from the stricter domain
authorization; domain commands continue to fail closed.

Official references consulted, with installed source used to resolve version drift:

- [Database transactions and native retry attempts](https://laravel.com/docs/13.x/database#handling-deadlocks).
- [API Resources](https://laravel.com/docs/13.x/eloquent-resources).
- [Authorization and Gate interception](https://laravel.com/docs/13.x/authorization#intercepting-gate-checks).
- [Queued work and transaction commits](https://laravel.com/docs/13.x/queues#jobs-and-database-transactions).

## Verification and delivery

| Gate | Result |
|---|---|
| Baseline | Full local PHP 8.5.11 SQLite **397 tests / 6,374 assertions**; baseline source CI 39102112148 success confirmed live |
| New regressions | Four failures reproduced before each correction; focused corrected suites pass |
| Auth/user suite | **25 / 143** on local PHP 8.5.11 |
| Catalog/checkout/review adapters | **41 / 514** on local PHP 8.5.11 |
| API idempotency | **11 / 72** on local PHP 8.5.11 |
| Catalog/cart/handoff/order concurrency | **29 / 305**, including independent-process races, on local SQLite |
| Full corrected suite | **401 / 6,427** on local PHP 8.5.11 SQLite before the final test-only metadata consumer expansion; includes migration recovery, ownership, HTTP/SMTP and queue failure/recovery |
| Final expanded HTTP metadata consumer | Passed **1 test / 10 PHPUnit assertions**, with independent Node/OpenAPI assertions on actual JSON/native proposal HTML; local PHP 8.5.11 |
| Formatting/metadata/assets | Full Pint, strict Composer validation, Node consumer syntax and production Vite build pass |
| Supported PHP 8.3 SQLite/MySQL, audits and caches | Pending current-source CI; old source results do not qualify these changes |
| New browser matrix | Not applicable: no visual/interaction change; customer Blade rendering and real HTTP projection regressions cover affected output; historical integrated browser evidence remains at its recorded revision |
| Schema/provider/production | No schema or provider change; existing databases, secrets and production untouched |

The initial restricted local run failed only because loopback listeners were
blocked. The same suite passed with local test listener permission. These are
environment failures, not suppressed application regressions. Test fixtures,
databases and loopback processes use existing teardown cleanup.

## Continuation gate

Independent code approval is granted for the corrective tree, conditional on
current source verification. Once those gates pass, no architecture blocker from
this review prevents the approved roadmap. **O2 still requires owner-deferred
PayMongo test credentials and actual sandbox payment/callback proof.** A presence-only
local check found no PayMongo configuration keys. No secret values were read into
the report or requested in chat. O3/S2, R1 and Q remain incomplete; S1 is deferred.
Do not substitute mocks for provider qualification or advance dependent phases.
