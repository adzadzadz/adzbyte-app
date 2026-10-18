# O2.2 signed payment outcomes

2026-10-18. Builds on qualified O2.1 source `8f13521` /
[CI39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120).
Current O2.2 status: code independently approved; local and actual-provider
verification pass; delivered PHP8.3 SQLite/MySQL CI pending. O2 aggregate,
operations/refunds, purchased access and production remain unqualified.

## Implemented boundary

Native Laravel request routing/HTTP middleware, encryption casts, Eloquent,
constraints and the existing transaction helper support a minimal event inbox
and immutable settlement. Raw-body HMAC-SHA256 verifies the timestamp plus body
before parsing/persistence. Strict unique header fields require test `te`, empty
live `li`, and a five-minute timestamp window. Live keys/mode/production deny.

Signed event ID and canonical resource hash deduplicate deliveries. Conflicting
event identity409; unknown attempts need Admin review; commercial mismatch
records a rejected receipt; unrelated/failure events cannot regress payment.
Only one exact paid payment and succeeded intent, matching captured mode,
session/reference/metadata/lines/method/amount/currency, can create a settlement
and increment the order once. A signed event can recover a reserved attempt
whose provider ID did not commit. Atomic local failure rolls everything back
for provider retry. A late initial dispatcher cannot overwrite paid state.

No billing/contact/card details, client keys or hosted URL are retained in the
encrypted event evidence. Processed event and financial history cannot be edited
or deleted. The acknowledged response contains only `received: true`. The
customer's owned payment page shows confirmation and hides initiation controls.
No service access or fulfillment transition is granted by this card.

## Actual provider observations and proof

[Sanitized receipt](actual-test-webhook.json). Saved **test** API key only;
dedicated test hook `hook_6tzkg95oQ8ddAMayZyQUuBJX`; prior WordPress/live
endpoints unchanged. Private signing secret saved in the existing ignored
mode0600 credential files, without exposing values in evidence or Git.

Official Cloudflare2026.10.1 temporary binary verified against the GitHub asset
digest and extracted binary checksum. The dedicated ingress rejects every path
and method except webhook POST before app boot. `/admin` returns404 and unsigned
webhook returns401. It points only at the disposable synthetic database.

Actual Hosted Checkout → QRPh generation → explicit **Simulate Payment** link →
**QRPH Test Payment Page / Authorize Test Payment** produces provider payment
`pay_urY4hsSwbjmkDVW6FrKodS7V`, PHP125.00, livemodefalse, succeeded intent.
No QR was scanned or paid through a bank/wallet. Actual provider event
`evt_NqRXbWaYQsqHyyWEKQpck88x` passes HMAC and commercial verification, records
one settlement, and changes the captured order/attempt to paid at revision4.
An own signed HTTP replay acknowledges200 without a second settlement or
revision. This replay is deliberately distinguished from a provider resend.

The actual event uses the documented `evt_` envelope with integer created_at;
the alternative narrative sample without event identity is not accepted.
Actual callbacks omit `pass_on_fees`, although retrieval returns explicitfalse.
Only signed-event verification permits absence; explicit invalid values reject.
Both paid-payment and intent amounts still must equal the captured total, so a
surcharge cannot be hidden by omitting the flag. Native retrieval remains strict.

The first real callback exposed that omission and was correctly rejected by the
initial implementation. Its receipt `evt_q8HBA6ZmirecamrEGPmrPodm` and test
payment `pay_PYHgGkTio373Bm8z9fYGVEcN` remain preserved unchanged for O2.3
audited reconciliation. The corrected success uses a separate synthetic order
and session; no financial operation or paid checkout was blindly repeated.

## Verification and applicability

| Gate | Evidence |
|---|---|
| Full local regression | PHP8.5.11 SQLite **477 tests / 6,908 assertions** pass; Pint/whitespace, Composer metadata and production build pass |
| Signatures/mismatches/history | `PaymentEventsTest`: raw-byte tamper/missing/stale/future/wrong-mode/duplicate header denial, strict envelope, amount/currency/reference/method/mode/intent mismatches, canonical duplicate/conflict, unrelated/delayed events, local failure rollback/retry, creation-ID crash recovery, dispatcher-vs-webhook race, immutable history and native confirmation |
| Size boundary | Independent reviewer reproduced256KiB rejecting an O2.1-supported999-line order. Receiver now permits8MiB; actual-receiver999×255-character ASCII/Unicode cases pass, oversized body rejects413 before persistence. Native PHPUnit subprocesses test large requests under unchanged128MiB, avoiding retained full-suite framework state; no production limit or assertion weakened |
| Persistence/concurrency | `PaymentEventConcurrencyTest`: four independent processes for same/different event IDs, one settlement/transition. `PaymentEventMigrationTest`: populated attempt upgrade, empty reversal/reapply and financial-history reversal protection. LocalSQLite pass; supported MySQLCI pending |
| Real HTTP/OpenAPI | `PaymentWebhookTransportTest`, independent Node raw-byte/HMAC consumer: no customer cookie/CSRF, tamper/query/method denial, acknowledgement and duplicate with documented schema;1 /14 passes locally |
| Actual provider | Captured real signed delivery and downstream paid state described above; old rejected receipt preserved; synthetic test-only, no live transaction |
| Native browser | Real synthetic customer logs into same actual-payment QA database; paid message, accessible role=status, no duplicate payment actions, desktop/320px without overflow or warning/error console entries; [desktop](confirmed-actual-desktop.png), [mobile](confirmed-actual-mobile.png), [test simulator](qrph-test-simulator.png) |
| Independent review | Reviewer approved corrected code and size test isolation; independently38 /199 plus isolated3 /10. Final source CI remains the qualification gate |
| Delivery/runtime | Pending scoped push/current PHP8.3 SQLite/MySQL CI; no existing DB migration, dependency update, release or production change |
| Temporary runtime | Guarded test ingress/tunnel and synthetic database retained for immediate O2.3 audited reconciliation/refund proof. Disable own hook and remove all temporary secrets/raw captures/processes before sandbox closeout; persistent credential archive remains private |

Native customer UI uses a separate loopback server; the public tunnel is only a
webhook ingress. The provider return URL is not evidence of payment and no full
cross-application launch journey is claimed here. Q retains that integrated gate.

Next: close O2.2 delivery/CI, then audited payment-event reconciliation, provider
expiry/cancellation/refund and operational recovery in O2.3.
