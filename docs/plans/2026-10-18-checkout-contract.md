# O1.2 checkout contract

Status: implemented, local/HTTP/browser gates passed; current supported-engine
source CI pending. O1.1 passed at 55cbb94 / CI 39097303894. Owner authorized configurable D02/D05/D08 defaults.

Verified customers review their canonical owned cart in the app, provide the
configured brief/billing details, explicitly accept displayed service and commerce
terms, and submit one pending review request. Guest/unverified/staff identities
cannot create an order. Clarification and Admin approval come in O1.3. No Pay Now
or provider/payment capability is added here.

Use shared OrderActions for native checkout, customer panel navigation and v1
REST. Native /checkout and owned /orders/{opaque-reference} show the accepted
order summary and independent statuses. Customer navigation exposes requests.
GET /api/v1/checkout returns a strict safe Resource with current allowlisted
service details, exact totals/tax mode, public policy version/content, required
field lists and review fingerprint. POST /api/v1/orders accepts only configured
brief/billing, true acceptance, fingerprint and header UUID; native form uses
body UUID. GET owned order detail exposes accepted snapshots and status, never
owner IDs, hashes, internal audit/notes or raw models. Reads are bodyless; writes
reject query parameters, unknown/nested fields and malformed JSON. Enforce fresh
web identity, required verification/customer middleware, CSRF, exact write Origin,
private no-store responses and named throttling. Trusted IDs come from the session.

Submission reuses the same UUID after retry and shows a visible result/error.
Stale review or changed offers returns 409, preserving entered customer fields
and requiring fresh price/terms acceptance. Validation errors remain actionable;
unavailable/empty/conflicting cart cannot submit. Return locations are fixed app
routes, never caller URLs. Safe text escaping applies to catalog, brief/billing
and legal content. Policy changes cannot rewrite the accepted request view.

Acceptance: real middleware + domain services via both adapters, positive and
cross-owner/role/verification denial, real CSRF/exact Origin, strict payload and
UUID retries, settings/offer changes, not-found/error/no-cache envelopes, and
independent actual HTTP/OpenAPI consumer. Actual browser Next -> handoff/login ->
cart -> checkout -> pending request, keyboard, desktop/320px, field validation,
stale review/retry, disabled payment, logout/other-owner denial and no unexpected
console/overflow. Full supported PHP 8.3 SQLite/MySQL CI, audits/build/caches,
formatting and source readback before O1.3. No schema is planned for the adapters;
reuse verified O1.1 migration evidence unless implementation changes it.


## Local and integration checkpoint

Local PHP 8.5.11 full suite: **364 tests / 6,019 assertions**. Checkout adapter,
JSON-session HTTP consumer and draft recovery tests pass, including real CSRF,
Origin, role/verification/owner, strict JSON/query/UUID, malformed native arrays,
256 KiB body bound, no hidden draft price leakage, repeat submissions, private
responses, throttling, and typed-field preservation after stale review. Native
form recovery is server-session-only, identity-bound, bounded by configured draft
retention and the shorter session lifetime; acceptance/fingerprint are never
restored. Success clears recovery data. It survives the required cart price review.

An independent Node consumer uses real encrypted database sessions and CSRF
against actual Laravel HTTP and validates current OpenAPI payloads. The harness
has no login/bypass route and refuses non-disposable targets. Its synthetic
session payload follows the application's JSON session serialization. Native unit
redirect tests carry their actual session cookie; a fresh synthetic ID otherwise
remarshals JSON error bags rather than reading stored data.

Actual built Next 52148d3 -> app handoff/login -> owned cart -> checkout -> pending
order passes **nine checks** at 1440/320px, including keyboard, required/server
validation, changed-price recovery with retained brief and fresh acceptance,
exact total, same-UUID replay, owned list/existing-request recovery and another
customer's native 404. No unexpected console/page errors or overflow. Source
capture evidence is in [browser results](../evidence/2026-10-18-checkout/browser-results.json).
Oversized Chrome compositing artifacts were replaced with settled viewport
captures; DOM has one header/title. Layout captures are a subsequent one-service
review; the accepted two-service order remains unchanged. Initial missing favicon
was a test-server asset-map omission, corrected and the full journey rerun clean.

No new schema or dependencies. Existing DBs, ignored Vite hot file and production
are unchanged. Disposable browser DB/key/token/password state and servers are
removed. Source 538e3ca passes required PHP 8.3 SQLite/MySQL CI 39098787766,
364 / 6,019 each; O1.2 is verified complete. See
[qualification](../evidence/2026-10-18-checkout/qualification.md). Aggregate O1 is incomplete; Admin communication/review/settings, expiry and
material-change acceptance remain O1.3. No payment provider action exists.
