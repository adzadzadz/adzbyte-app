# O2.3b verified checkout closure and cancellation

2026-10-19. **O2.3b qualified.** Local, independent, actual-provider, schema/race
and native/REST checks pass. Source `46614f89c0246be69e5c30c66d858f54e70b2273` / [CI39191936629](https://github.com/adzadzadz/adzbyte-app/actions/runs/39191936629)
passes **539 tests/7,537 assertions per engine**, PHP8.3.35 SQLite/MySQL8.0.46.
Audits zero; metadata, Pint, production build and caches pass. Remote main
readback matches this source. O2.3c refunds/disputes is next; aggregate O2 remains incomplete.
[Contract](../../plans/2026-10-18-paymongo-operations.md).

## Actual sandbox and native proof

One actual QRPh-capable test checkout for PHP125.00 was created with a
controlled lost-response fault before its identity could commit locally. The
application protected the unknown attempt without exposing a checkout URL.
Admin recovered the original session through the native Filament action and
fresh exact provider verification; no second checkout or settlement was created.
The synthetic customer then cancelled through the owned native form. A matching
PayMongo expired readback confirmed cancellation: order cancelled, unpaid,
fulfillment cancelled, attempt expired, revision7, zero payments/settlements.
The two earlier distinct test settlements remain unchanged. No live operation,
real transfer or provider key rotation occurred. [Sanitized proof](actual-test-closure.json).

Desktop1470 and mobile320 have no document overflow or warning/error console
entries. Cancelled requests retain accepted history and expose neither payment
options nor another cancellation button. Browser QA exposed legacy approval
wording that still claimed payment was unavailable and showed valid approval
after cancellation; the review partial now gates that paragraph on current
open/approved/unpaid-or-pending state, with before/after regression assertions.
Screenshots: [recovery](closure-recovery-desktop.png),
[customer desktop](closure-customer-desktop.png),
[customer mobile](closure-customer-mobile.png).

## Verification and invariants

- Final full local PHP8.5.11 SQLite suite: **539 tests/7,537 assertions**. Pint, strict Composer
  metadata, production build and whitespace checks pass.
- Domain/native/API suite29/285 before final wording assertions; focused final
  UnpaidCancellationTest2/21. Captured settings accept explicit false, reject an
  empty payment object and preserve fixed legacy omission defaults. Malformed
  historical rules fail closed; accepted snapshots are never rewritten.
- Actual independent Node HTTP/OpenAPI consumer2/30: encrypted sessions, ownership,
  exact Admin capabilities, CSRF/Origin, strict payloads, replay and throttles.
- Migration and four-process races3/53: populated legacy snapshots survive
  upgrade; empty reversal/reapply works; populated audit reversal is refused.
  Same/different UUID races reserve one command and perform one expiry POST.
- Pending commands reserve and audit before HTTP. Native bounded GET/POST/GET
  runs outside locks with no automatic POST retry. Final authorization, order
  revision/current pointer and exact provider proof are rechecked. A paid webhook
  wins; paid/pending/processing, malformed, wrong amount/mode/session or uncertain
  results cannot retire an attempt or reopen checkout.
- Lost expiry responses and local commit failures preserve durable uncertainty.
  GET-only verification can finish an already completed expiry. An explicit new
  Admin command may close a still-active session and preserve an earlier
  customer cancellation intent through an immutable same-attempt command link.
  Permission revocation before POST blocks mutation; revocation/stale order
  after provider expiry blocks local commit for later authorized recovery.
- Reviewer independently passed34 tests/368 assertions and approved the shared
  Laravel actions, exact policies, native primitives, schema, races and real
  HTTP adapters. Empty-rule validation and cross-command cancellation intent
  were corrected and reviewed before sandbox execution. Final wording correction
  also independently approved with2/21 regression assertions.

## Verification runner diagnosis

The overnight session interruption caused an outer Composer300-second timeout.
Subsequent runs encountered changing existing SMTP/catalog/cart/HTTP subprocess
timeouts, while affected isolated suites passed unchanged (SMTP5/68; catalog6/90).
This was traced to host sleep, not silently retried as a passing product gate:
macOS power logs show repeated Maintenance Sleep/DarkWake cycles, including a
520-second sleep ending with UserActivity/lid wake at10:48:19+0800 on2026-10-19.
A60-second Symfony child timeout was recorded as0.388seconds by PHPUnit.
Installed Symfony compares microtime wall time; PHPUnit measures hrtime, which
excludes sleep on this host. The independent reviewer confirmed the mechanism.
Final verification uses a temporary process-scoped `caffeinate -i` idle-sleep
assertion. No test timeout, assertion or product code was weakened, and no
persistent power setting changed.

## Scope and runtime

Only disposable QA databases migrated. Existing development/production data and
original WordPress/live provider hooks remain unchanged. The temporary Admin UI
is stopped after capture. Dedicated test webhook ingress/tunnel and private
state remain for immediate O2.3c refund qualification, then must be cleaned up.
Owner-requested credential files remain ignored/private. Aggregate O2, access,
fulfillment, support and production release remain incomplete.
