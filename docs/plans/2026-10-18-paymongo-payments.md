# O2 — PayMongo payment implementation contract

Owner, 2026-10-18: proceed with saved **test credentials only**, fully qualify each
card/phase before dependent work. Baseline ee73c21; independent Laravel review
qualified at f128d5c / CI 39103121139. Live credentials remain an unused private
reference; do not load the combined credential archive into application config.

## Verified provider preflight

- Test-key `GET /v1/webhooks` and merchant capabilities return HTTP 200.
  `GET /v1/merchants/capabilities/payment_methods` returns only **qrph**.
  Existing test webhooks point to the retired WordPress adapter; preserve them.
- Actual `/v2/checkout_sessions` creation returns a minimal object containing
  session ID, checkout URL, mode and timestamps. Retrieve via
  `/v1/checkout_sessions/{id}` for the complete resource.
- **Identical creation requests with the same Idempotency-Key produced different
  session IDs.** The general idempotency documentation is insufficient evidence
  for this endpoint. Both disposable sessions were retrieved/expired successfully:
  `cs_abd44a17d7a6f281870655bf`, `cs_f5f583cf4532c604b3527e68`.
- Local DNS was intermittent. A temporary, independently resolved address with
  normal certificate validation was used only for the preflight; no production
  DNS/IP override is introduced.

## O2.1 — Attempts and owned checkout initiation

Use Laravel HTTP client, policies, FormRequests, Resources, Eloquent/constraints,
transactions, the already-qualified bounded retry helper and append-only events.
No payment framework or parallel authentication stack is required.

Persist one current attempt pointer on the order. Capture its immutable owner,
accepted revision, expected order revision, total/currency, test mode, request
UUID/hash, allowed methods and exact provider payload. Encrypt stored provider
payload/checkout URL using Laravel casts. Customer projections contain only
owned public references, state and an explicitly validated hosted checkout URL.

Only a fresh verified customer can initiate their owned, open, approved,
unexpired, unpaid request with no pending material proposal and a matching order
revision. Current merchant methods must intersect configured methods. Exact
decimal totals must be within the provider Payment Intent limits of PHP 1.00
to PHP 9,999,999.99 (100–999999999 centavos), also guaranteeing safe native
integer conversion; never round or truncate. A request may contain at most
999 provider lines, including an exclusive-tax line. Reject predictable provider
limits before reserving or dispatching. No surcharge; no automatic email receipt or customer brief/internal
terms sent to PayMongo. Return URLs are server-controlled and informational.

Reserve the attempt under aggregate locks and change the order to pending before
the outbound call. Send creation **once**, outside the database transaction.
Same-command retries recover the receipt; different commands cannot create a
second current session. An ambiguous dispatch stays unknown and blocks new
creation; it must never retry POST blindly, even with the original provider key.
Persist a known returned ID before retrieving/validating the full session.
Only matching mode, reference, metadata, amount/currency, methods and no-surcharge
settings permit an active hosted URL. A provider session does not mark payment.

Before another attempt can be created, the prior session must be verified expired
or a non-creation failure explicitly reconciled. Generic failure/timeout and local
lease expiry never prove that no checkout exists. Expiry, customer cancellation,
approval withdrawal and material change must account for any payable session.

Acceptance: success/replay/foreign owner/unverified/staff/revoked role, stale or
unapproved/expired request, malformed input, safe resource projection, live-key
denial, provider mismatch/outage, unknown-response recovery, concurrent same and
distinct commands, migration upgrade/reversal and actual test API transport.
Run full supported PHP 8.3 SQLite/MySQL CI and real HTTP/OpenAPI/owned native
entry points as applicable before the next card.

## O2.2 — Authoritative provider outcomes

Register a dedicated **test** webhook to an isolated test-only HTTPS ingress;
do not expose the existing app/admin/database or change production endpoints.
Verify the raw body, timestamp, strict test signature and mode before persisting
minimal necessary event identity/evidence. Deduplicate durably, reject conflicting
event IDs, serialize against the order/attempt and validate actual provider
session/payment identity, captured amount/currency, reference and metadata.
Only verified provider events grant one paid transition. Browser redirects,
expired QR codes, API readbacks, payment failures and replay never grant access.

Validate malformed/invalid/stale/wrong-mode signatures; duplicate/concurrent/
out-of-order events; wrong amount/currency/session/order; unknown attempts;
provider-success/local-failure recovery. Actual test checkout payment and actual
provider delivery/receipt/downstream state are required; mocks cannot qualify it.

## O2.3 and later dependencies

Policy-backed Admin payment/event/reconciliation/refund controls, configurable
approved cancellation/refund defaults, verified full/partial provider refund
outcomes, expiry/approval invalidation and queue recovery remain part of O2.
O3/S2 purchased service and fulfillment, R1 cases and Q integrated launch
qualification follow only after the O2 aggregate exit passes. Subscription S1
remains explicitly deferred. Owner approved configurable partial-refund-retains/full-refund-stops-new-requests rules with readable history and audited Admin exceptions. Attachments are explicitly deferred; customers share Google Drive file/folder links. These choices are recorded in the decision register. Production release remains separately authorized.

## Official references

- [Hosted Checkout v2](https://docs.paymongo.com/docs/payment-channels-hosted-checkout).
- [Checkout/session/QR expiry semantics](https://docs.paymongo.com/docs/payment-channels-key-concepts).
- [General idempotency contract](https://docs.paymongo.com/reference/idempotent-requests), contrasted with the observed endpoint behavior above.
- [Merchant method capabilities](https://docs.paymongo.com/docs/account-settings-account-capabilities).
- [Webhook setup/signatures](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management).
- [Laravel HTTP client](https://laravel.com/docs/13.x/http-client).

Provider PHP amount limits are verified from the [Payment Acceptance introduction](https://docs.paymongo.com/docs/payment-acceptance-introduction). Hosted v2 defers its Payment Intent, so a session must not be created for a total the intent cannot accept.
