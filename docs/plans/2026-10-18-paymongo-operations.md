# O2.3 payment operations

2026-10-18. Depends on O2.2 qualified source7560514 / CI39116369733.
The owner approved configurable cancellation/refund defaults, Admin review after
payment, verified provider results and audited exceptions. No live activation or
production deployment is authorized by this development work.

## Verification sequence

O2.3 is divided into dependency-ordered qualification cards. Each card needs
independent review, all applicable local/actual-provider/native/REST/schema/race
checks and current-source PHP8.3 SQLite/MySQL CI before dependent implementation.
The aggregate remains incomplete until all cards qualify.

1. **O2.3a — Signed-receipt reconciliation and Admin visibility.** Read captured
   payment, paginated signed receipts and recent resolutions. Recover the
   preserved actual rejected test event without changing original history.
2. **O2.3b — Expiry/cancellation recovery.** Verify provider expiry before
   clearing an active/unknown session or allowing replacement/cancellation.
   Unknown creation without a known session cannot be guessed safe. Customer
   prepayment cancellation and approved configurable policies require shared
   ownership/capability and command/revision guards.
3. **O2.3c — Refund/dispute operations.** Durable once-only refund commands,
   verified full/partial provider outcomes, pending/unknown recovery, bounded
   sums, Admin disputes/reconciliation and notifications. Actual QRPh refund
   contract and outcomes must qualify, including failures and claims as
   applicable; processing never means succeeded. No blind provider POST retry.

## O2.3a contract

Separate ViewAny:PaymentAttempt, View:PaymentAttempt and
Reconcile:PaymentAttempt capabilities are defined without automatic role grants.
Only verified Admin role plus exact grants can inspect or reconcile; broad
Super Admin Gate shortcuts do not override the shared action or resource.
Customer-owned payment reads retain their existing narrower projection.

GET /api/v1/management/payments/{payment} is bodyless, private no-store,
web-session-only and accepts only event_page (20 receipts/page). It returns
safe captured fields, order revision/payment state, minimal settlement,
original receipt outcomes and latest20 Admin resolutions. No provider payload,
checkout URL, signatures, raw evidence, secret or contact data is exposed.

POST /api/v1/management/payments/{payment}/reconcile accepts a JSON object with
only event, expected_revision and note (nonblank, <=1000 characters), a UUID
Idempotency-Key, exact canonical Origin and real session CSRF. No query is
accepted; encoded body is bounded to16KiB. Missing/nonmatching receipt404;
stale or conflicting UUID/state/evidence409; unavailable provider503. The
OpenAPI contract and independent HTTP consumer verify this boundary.

Preflight authorizes a freshly locked actor, then locks order/attempt/receipt,
checks global prior OrderEvent UUID reuse and any existing resolution. The
bounded native Laravel HTTP GET runs outside database locks. The commit stage
reauthorizes the actor and rechecks revision/command identity under the same
locks before applying anything. The original signed receipt and current provider
GET must independently match captured session, mode, commercial snapshot,
amount/currency, exact paid payment and succeeded intent; payment/intent IDs
must agree between the two proofs. Current GET additionally requires explicit
disputed=false and refunds=[]: paid gross/status alone cannot erase existing
refund/dispute evidence. Ambiguity remains for refund-aware operations.

The same settlement verifier/action serves automatic webhooks and reconciliation.
Current-GET refund/dispute checks do not change ordinary signed webhook handling.
Reconciliation requires a bound, preserved rejected/needs_review paid receipt;
it cannot manually mark paid from GET alone, create a provider session, bind
an unknown receipt to a guessed attempt or override commercial mismatch.

The original receipt stays byte-for-byte unchanged. An immutable PaymentResolution
records actor/time, UUID/request hash, original state/reason, Admin note and
minimal encrypted provider proof. The settlement, paid transition, actor audit
and resolution commit atomically; local failure rolls back for safe retry.
Matching replay returns the existing resolution without a new provider GET.
Conflicting commands reject before provider access and again under final locks.
Four independent processes demonstrate one settlement/resolution and monotonic
order revision. Populated reversal fails closed; empty reversal/reapply and
populated upgrade preserve history.

Filament /admin/payments lists captured attempts and opens public references via
Laravel getRouteKeyName. The detail page reauthorizes hydration, escapes notes,
uses the shared action and hides reconciliation when no eligible receipt remains.
No generic payment/history editing, creation or deletion routes exist.

[O2.3a evidence](../evidence/2026-10-18-payments/reconciliation-qualification.md).
