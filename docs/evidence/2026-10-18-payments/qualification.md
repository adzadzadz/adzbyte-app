# O2.1 payment-attempt qualification

2026-10-18. Baseline `ee73c210e103c908a9a2db036fdf3befa63ef1eb`.
**O2.1 qualified source: `8f13521cedecd8feaf064e0eaaa0cc4df9edfcaf`.** Pushed and remote main read back; [CI 39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120) passes **436 tests / 6,688 assertions independently on PHP 8.3.35 SQLite and MySQL 8.0.46**, plus audits/metadata/formatting/build/production caches. Independent code review approved. This does not
qualify O2 aggregate, webhook authority, refunds, purchased access or production.

## Scope and framework use

Shared `PaymentAttemptActions` owns fresh authorization, aggregate locks,
immutable captured payloads, command receipts and once-only after-commit
dispatch. Native Laravel HTTP client, Eloquent encryption casts, FormRequest,
Resource, policy and database primitives back thin web/REST adapters. No new
dependency, custom authentication stack or live-payment activation is added.
Provider PHP amount limits (100–999999999 centavos) and the 999-line maximum
including exclusive tax are checked before reservation; eight boundary tests
cover both. These deterministic rejections keep the order unpaid.

One current attempt blocks duplicate creation. Same UUID/input returns its
receipt; distinct commands conflict. Ambiguous transport or post-commit local
failure preserves the reservation and cannot automatically repeat creation.
Known provider IDs are saved before full retrieval. Verified mode, captured
amounts/currency, reference, metadata, methods and fee settings are required to
expose a hosted URL. Neither session creation nor redirect marks an order paid.

## Independent findings resolved

`laravel_checkup` independently reproduced two issues in the initial slice:

- Same-host foreign session URL was accepted. The final validator accepts only
  the exact captured ID path with or without the `cs_` prefix. The latter is
  the form actually returned by v2. Fragments are preserved; arbitrary paths,
  foreign IDs, credentials, ports, non-HTTPS URLs and different hosts fail.
- Incomplete minimal creation metadata discarded a known session ID. Creation
  now extracts identity; full retrieval validates mode/commercial attributes
  after that identity has been persisted. Missing/wrong minimal mode and failed
  retrieval preserve the ID without another POST.

Reviewer independently checked both allowed URL forms and foreign/traversal
denials without network/database effects. Final scoped approval received, conditional only on delivered PHP 8.3 SQLite/MySQL CI.

Browser testing additionally reproduced a native expired-session HTTP 500:
default Laravel auth referred to a missing named login route. Native
`redirectGuestsTo` now targets the existing Filament account login. Regressions
cover payment, checkout, order detail redirects and unchanged API 401 behavior.

## Verification record

| Gate | Current evidence |
|---|---|
| Domain/failure/security | `PaymentAttemptsTest`: exact immutable amount and exclusive tax, integer overflow denial, ownership/verification/fresh role, stale approval/revision, live-key denial, real CSRF/Origin/strict input, encrypted fields, provider mismatch/missing evidence, lost response, known-ID retention, outer rollback and post-commit local-failure replay |
| Contention | `PaymentConcurrencyTest`: four independent processes, same/different UUIDs; exactly one creation POST, attempt, receipt and pending transition; SQLite and MySQL supported-runtime CI pass |
| Migration/recovery | `PaymentMigrationTest`: populated O1 upgrade preserves snapshots; empty reverse/reapply; populated payment reversal refuses deletion and preserves current pointer/history; SQLite and MySQL supported-runtime CI pass |
| API/consumer | `PaymentTransportTest` / independent Node consumer: real loopback HTTP, encrypted database sessions, actual CSRF/Origin/auth/ownership, strict OpenAPI commands/resources, replay/conflict/privacy and persistent throttling; 1 test / 12 assertions passes locally |
| Native browser | Built assets; real synthetic-customer login, native preparation POST/redirect, refresh preserves one attempt/receipt, desktop and 320px layout without overflow, keyboard navigation, one h1, accessible status and no warning/error console entries; screenshots below |
| Actual provider | Native action/client creates one real QRPh test session for PHP 125.00, verifies full resource, replays same command, denies distinct command409; order remains pending; session expired via provider HTTP200/statusexpired; [sanitized receipt](native-test-transport.json) |
| Source checks | Full final local PHP 8.5.11 SQLite suite **436 tests / 6,688 assertions**, Pint, whitespace, Composer metadata and production build pass; Composer/npm audits both zero findings with network access. Initial sandbox DNS failures were environmental, not successful audits |
| Supported runtime/delivery | Source 8f13521 pushed/read back; [CI 39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120) both jobs pass; local PHP 8.5.11 is supplementary |
| Payment callback/refund | Separate O2.2/O2.3 gates; no paid transition, callback or refund is claimed |
| Deployment | Separate authorization gate; existing development/production DB, deployment and live provider settings untouched |

The unknown-state browser screenshot uses an explicit disposable presentation
fixture after the real native success path. Provider-failure transitions and
retry safety are proved by action tests, not that presentation fixture.

- [Desktop preparation](prepare-desktop.png)
- [320px preparation](prepare-mobile.png)
- [Native success/continue state](active-mobile.png)
- [Unknown state hides payment/retry links](unknown-mobile.png)

## Provider contract observations and cleanup

Actual identical v2 POSTs with an identical `Idempotency-Key` returned different
IDs; both preflight sessions were retrieved and expired. This implementation
therefore relies on its durable reservation, not an assumed provider creation
idempotency guarantee. General provider documentation alone was insufficient.

The first exact-path validator rejected actual v2 URLs because the provider
omits `cs_` in the path; those diagnostic sessions were also confirmed expired.
Both URL forms now have success coverage and same-host foreign paths are denied.
Metadata JSON key ordering is canonicalized for comparison.

Only the saved test key was loaded. Live credentials and existing WordPress
webhooks were untouched. Private native/browser QA scripts, synthetic DB,
session config and UI server were removed/stopped. Provider checkout URLs,
client keys, API credentials and raw authenticated exchanges are excluded from
this evidence. Temporary QA DNS resolution retained HTTPS certificate checks
and was never added to application configuration.

Next: dedicated test webhook and actual provider payment/callback verification in O2.2.

## Provider-limit follow-up

The current official [amount bounds](https://docs.paymongo.com/docs/payment-acceptance-introduction) and [999-line limit](https://docs.paymongo.com/reference/checkout-session-resource) are checked before persistence/POST. Independent reviewer approval received for this follow-up; 8 focused cases / 24 assertions pass. Initial source `1cadf969591837ccd973f9961dcbc6e2199f2a55` application CI passes PHP8.3.35 / 428 tests / 6,664 assertions. Current follow-up source delivery/CI supersedes that baseline qualification and remains pending.

Final local full suite after provider-limit guards: **436 / 6,688**, PHP8.5.11 SQLite; Pint/whitespace pass. Initial source 1cadf96 now passes both PHP8.3 SQLite/MySQL CI39113969112 jobs. Final follow-up CI remains required.

Final follow-up source **8f13521** is qualified by [CI 39114275120](https://github.com/adzadzadz/adzbyte-app/actions/runs/39114275120): both engines **436 / 6,688**, PHP8.3.35, MySQL8.0.46. Source delivery, independent review and all O2.1 applicable development gates pass. O2 aggregate remains incomplete.
