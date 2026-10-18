# O2.3a audited signed-receipt reconciliation

2026-10-18. Local, independent, actual-provider and native gates pass.
Current-source PHP8.3 SQLite/MySQL CI remains pending delivery. Aggregate O2.3,
refunds, expiry/cancellation, purchased access and production remain incomplete.
[Contract](../../plans/2026-10-18-paymongo-operations.md).

## Actual preserved payment recovery

The Admin browser action reconciled original event
`evt_q8HBA6ZmirecamrEGPmrPodm` / session`cs_06704d4455175716164815d4` /
payment`pay_PYHgGkTio373Bm8z9fYGVEcN` from the earlier callback fee-field
omission. It used the saved test key, original signed receipt and a fresh native
PayMongo GET. Current proof confirms PHP125.00, livemodefalse, matching succeeded
intent, disputedfalse and refunds[]. No provider POST, second payment or live
operation occurred.

Result: original attempt/order paid, revision4, exactlyone settlement and one
immutable Admin resolution. The original receipt's raw attribute hash is
unchanged: rejected/session_mismatch stays readable. Existing corrected second
payment is unchanged; disposable database now has two settlements for its two
distinct actual test payments. [Sanitized proof](actual-test-reconciliation.json).

Actual Admin login, list/detail/public-reference navigation and action succeed.
Desktop1470px and mobile320px have document scrollWidth equal to viewport width,
no warning/error console entries and a readable paid/settlement/original-receipt/
resolution view. Completed reconciliation hides the action. Evidence:
[desktop](reconciliation-desktop.png), [mobile](reconciliation-mobile.png).

## Verification

- Full local PHP8.5.11 SQLite: **505 tests / 7,164 assertions**. Pint, Composer
  strict metadata, production build and whitespace checks pass. Dependency
  manifests unchanged; supported-runtime CI will repeat audits and caches.
- Shared action/REST/Filament tests cover safe projections, exact capabilities,
  unverified/customer/ungranted Super Admin denial, stale revisions, permission
  revocation during GET, prior Admin command UUID conflict before GET, original
  signed mismatch, live/amount/currency/intent/session mismatch, actual-shaped
  paid-with-refunds and disputed readback denial, provider failure, atomic local
  failure/retry, webhook winning during GET, immutable audit/history, escaped
  notes, hydration revocation and public-reference URL generation.
- Four-process matching/different UUID races create exactlyone settlement and
  one resolution. Matching retries return that resolution; competing commands
  reject409. Populated upgrade, empty reversal/reapply and retained-resolution
  reversal protection pass in disposable databases.
- Actual loopback HTTP with independent Node/OpenAPI consumer: real encrypted
  Admin/customer sessions, CSRF/Origin, strict request/response shapes, privacy,
  matching replay/conflict and persistent throttling; **1 test /15 assertions**.
- Reviewer independently passed **37 /268**, found refund/dispute readback and
  cross-action UUID gaps, and approved their corrections. Final route-key/action
  visibility followup approved; reviewer independently passed52 /398 plus actual
  HTTP transport1 /15. Current final local suite passes above.
- Browser fixture initially used a stale disposable database permission cache
  after its CLI seeder used array cache. Clearing only that QA cache fixed it;
  existing application/provider configuration was unaffected. Real browser
  exposed numeric breadcrumb generation; Laravel model public route key and a
  generated-URL/actual-GET regression fixed it before reconciliation.

## Runtime and scope

Only disposable task databases migrated. Existing development/production data,
provider live mode and original WordPress hooks remain untouched. The private QA
Admin UI on59861 is stopped after capture. Dedicated test-only webhook ingress
and official tunnel remain for immediate O2.3b/c sandbox work; keep private
state/callback/credential files until that work finishes, then disable only the
dedicated test hook and remove temporary secrets/raw captures/processes. Persistent
owner-requested credential archives remain ignored mode0600.

No refund/access/fulfillment outcome is inferred from this card. Next gate is
supported PHP8.3 SQLite/MySQL CI; then O2.3b provider expiry/cancellation recovery.
