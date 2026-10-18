# O1.2 checkout contract

Status: planned; dependent implementation waits for final O1.1 PHP 8.3
SQLite/MySQL CI qualification. Owner authorized configurable D02/D05/D08 defaults.

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
