# Checkout capture, purchase notifications and automated-mail footer

This is the owner-approved follow-up to the native QRPh package checkout. Source is prepared in the isolated backend clone; production execution and commit remain with the Main Orchestrator after independent review. The release guard pins the parent-verified current deployment `25126114762afe1533863b1f7e1264fcd14ea7fe`. Unrelated main commerce changes remain excluded.

## Customer fields and compatibility

Create accepts the existing package_code, offer_version, operation_id and email, plus exactly one of full_name or the legacy business_name alias. Full name is trimmed, limited to 160 characters and rejects control characters/angle brackets. The legacy alias retains its previous control-character validation, including support for literal angle brackets in historical names. Names are not split or treated as a fabricated PayMongo payer identity.

Phone is optional, nullable, trimmed, at most 30 characters, accepts `+`, digits, spaces, parentheses, periods and hyphens, and requires 7–15 digits when supplied. Empty becomes null. Name, email and phone are encrypted at rest, immutable after reservation and excluded from the public checkout resource. Legacy business_name snapshots remain available through a name fallback. Proof-bound historical operation hashes still replay; changing immutable details conflicts.

The public checkout response adds only fulfillment_status: null before paid, pending while completed-phase delivery is queued/in flight, delivered after the bridge confirms completed delivery, or review when intervention is needed. Historical paid records without a delivery record report review; no historical form/mail backfill is performed.

## Durable two-phase delivery

The reservation transaction now saves a pending capture outbox record **before the first PayMongo POST**. Valid customer details therefore survive QR creation failures or unknown outcomes. A WordPress failure never triggers QR recreation. The existing proof/operation replay returns the same checkout and creates no duplicate capture.

Only a new verified paid transition creates the completed outbox record, atomically with payment and its webhook receipt. Replayed paid events, additional paid events and already-paid historical records do not create another completion. Payment confirmation has no network or mail dependency; subsequent delivery failures do not change paid back to failed.

Each phase stores a frozen encrypted JSON payload and its hash. `(package_checkout_id, phase)` is unique. Two independent phases allow completion to arrive before delayed capture: the WordPress bridge must keep the single form entry monotonic, with Completed winning over Pending. Pending capture sends **no owner alert or customer purchase email**. Completed updates the same entry, then sends the approved owner alert and customer receipt/next-steps email through the controlled Fluent Forms notification channels.

## Exact WordPress bridge contract

POST only `https://wp.adzbyte.com/wp-json/adzbyte/v1/package-purchase`, with dedicated server `Authorization: Bearer <PACKAGE_PURCHASE_BRIDGE_TOKEN>` and `Idempotency-Key: <reference>`. Reference is preserved byte for byte, including lowercase Laravel ULIDs. No provider client key, intent ID, proof or browser token is forwarded.

The exact flat JSON keys, in stable order, are:

```text
reference, phase, payment_reference, full_name, email, phone,
package_code, package_name, amount_minor, currency, quantity, paid_at, test_mode
```

phase is pending or completed. Pending sends payment_reference=null and paid_at=null. Completed sends the verified pay_ reference and a UTC timestamp formatted `Y-m-dTH:i:sZ`, without fractional seconds. Phone is null or the immutable string. Currency is PHP, amount_minor is an integer, quantity is 1 and test_mode is boolean. Apart from phase/payment_reference/paid_at, both phases carry the same immutable customer/offer snapshot. WordPress deduplicates the receipt reference and per-phase payload/base hashes, preserving one Fluent Forms entry and independent mail-channel state.

Responses contain `data.reference`, `data.status` and `data.entry_id`. Laravel accepts HTTP 200 captured **only for pending**, and HTTP 200 delivered **only for completed**. An HTTP 202 pending defers delivery; a review response stops automatic retries. A captured acknowledgement can never mark completed notification delivery successful. Conflict/authentication/payload-rejection responses stop for review. Response messages and customer/provider secrets are not stored or logged.

## Scheduling, retries and operator visibility

After the database transaction commits, a job for that specific reference/phase runs after the HTTP response. Under FastCGI this keeps WordPress/SMTP work outside the customer response and payment acknowledgement. The durable fallback is `package-purchases:deliver --limit=25`, scheduled every minute through the existing Laravel scheduler. Ensure the production PHP/FastCGI execution budget permits this after-response work.

Workers claim each row transactionally with a unique lease token. Network runs outside the transaction. An abandoned five-minute lease can be reclaimed; an older worker cannot overwrite a newer claim. HTTP uses a fixed endpoint, no redirects, a five-second connection timeout and 35-second overall timeout to cover the bridge's two bounded SMTP calls. The command reserves that request budget and normally exits within approximately 45 seconds.

Temporary/ambiguous transport, 503 or pending responses retry the same bytes/reference with exponential backoff starting at 60 seconds and capped at one hour. Ten unsuccessful attempts stop in needs_review. WordPress separately persists channel outcomes: an uncertain mail crash must return review and suppress automatic resend. Laravel never decides to resend a completed mail channel independently.

Verified administrators can inspect full name, private phone/email, capture state and attempts, completion state, safe error code, next attempt and delivered time in the existing read-only package checkout resource. They cannot edit payment or delivery snapshots. After resolving the underlying WordPress/configuration/mail uncertainty, an authorized operator may queue a specific reviewed phase with:

```sh
php artisan package-purchases:retry REFERENCE pending
php artisan package-purchases:retry REFERENCE completed
```

These commands retain the original payload and lifetime attempt count. Delivered or active rows cannot be reset. Retrying a WordPress review state does not bypass its independent mail-channel safeguards.

## Configuration and deployment preparation

New environment settings default off:

```text
PACKAGE_PURCHASE_BRIDGE_ENABLED=false
PACKAGE_PURCHASE_BRIDGE_TOKEN=
PACKAGE_PURCHASE_BRIDGE_TEST_ENABLED=false
PACKAGE_PURCHASE_BRIDGE_TEST_EMAILS=
```

The bearer must match the dedicated WordPress option and contain 43–128 URL-safe characters. Both applications reject test delivery unless explicitly enabled for an owned address; the WordPress gate restricts it to adrian@adzbyte.com. Do not commit real values or place them in generated public frontend configuration.

After reviewed source promotion, apply only `database/migrations/2026_10_22_010000_add_package_purchase_fulfillment.php` using the approved production backup/migration process. It adds nullable encrypted name/phone columns and the outbox table, without changing existing rows or generating historical entries/emails. Retain the existing APP_KEY. Do not delete delivery/WordPress ledgers to retry external effects. Verify the existing `schedule:run` cron runs every minute; do not add a duplicate scheduler. Clear/rebuild Laravel caches after deployment.

The parent owns runtime MAIL_FROM_ADDRESS=support@adzbyte.com, MAIL_FROM_NAME=Adzbyte Solutions and APP_NAME branding. This source changes the example defaults only. Laravel HTML/plain-text Markdown footer overrides reuse the approved real company PNG, teal styling, support email, phone, Messenger, Facebook and website. Existing Filament password-reset and customer-activation notifications render the shared footer once. Purchase email content and the 24-hour contact promise remain owned by the WordPress template, avoiding a second purchase mail sender.

New release-allowlisted paths are the two CLI commands, DeliverPackagePurchase job, PackagePurchaseDelivery model, PackagePurchaseDeliveries service, the migration above, routes/console.php, both footer Blade overrides, AutomatedMailBrandingTest and this document. Existing checkout/service/config/test/support-router paths remain individually allowlisted. Both workflow checks and the script use baseline 25126114762afe1533863b1f7e1264fcd14ea7fe. The parent will advance source history to that artifact before commit; this work did not merge main or change HEAD.

## Verification and integration handoff

- Full PHP 8.5.11 SQLite suite: **123 tests / 842 assertions**, including the new capture/completion, legacy replay, phone privacy/validation, transaction rollback, worker timeout/backoff/review/lease/exhaustion and branded mail-render cases.
- Pint, whitespace and Laravel optimize/optimize:clear pass. Schedule discovery shows the minutely delivery command. Disposable SQLite migration apply, one-step rollback and reapply pass.
- Both dependency lockfiles remain unchanged in this follow-up. PHP 8.3/MySQL CI and independent source review remain release gates.
- No production mutation, provider call, real email, Fluent Forms entry or deployment was performed by this backend implementation task.

After source/WordPress review, the parent can qualify actual bridge delivery while keeping all PayMongo traffic fake:

1. Create a fresh disposable database named `/private/tmp/adzbyte-package-http-NAME.sqlite` and migrate it from this clone. Keep APP_ENV=testing and DB_CONNECTION=sqlite.
2. Run the existing loopback `tests/Support/package-qrph-http-router.php` with the bridge token in a private process environment, PACKAGE_PURCHASE_BRIDGE_SMOKE=1, bridge enabled, test delivery enabled and PACKAGE_PURCHASE_BRIDGE_TEST_EMAILS containing **only** adrian@adzbyte.com. No credential value belongs in the command history/report.
3. The router permits a real request only to the fixed WordPress bridge, only with test_mode=true and email exactly adrian@adzbyte.com. Every PayMongo endpoint stays Http::fake and all other stray requests remain blocked. Default fixture mode makes no real WordPress call.
4. Submit a new synthetic Full Name/optional phone checkout through the local Next UI or authenticated fixture API. Verify one Pending entry in the configured dedicated Fluent Forms form, pending capture acknowledged, and **zero** purchase mail channels sent.
5. Send a synthetic payment.paid callback signed with the fixture-only webhook secret to the local webhook, matching its persisted fake PI/amount/mode. Verify the **same** entry becomes Completed, the owner alert and customer receipt each send once to the owned address, and public fulfillment_status becomes delivered.
6. Replay the operation/event and retry delivery: entry count and channel-send count must remain unchanged. Also qualify completion-before-capture without a downgrade. Stop the local server and disable the temporary test gates afterward.

The synthetic PNG is not a payable QR. This integration proof creates only explicitly approved owned-address WordPress test data/mail and performs no PayMongo payment.
