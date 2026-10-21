# Native QRPh package checkout — isolated source review

This branch adds the owner-authorized guest package purchase flow while retaining Preview first in the Next UI. Quantity is always one. It is a separate small application service, schema and provider adapter; it does not change the account/order approval or existing Hosted Checkout semantics on current main. No customer account, cart, customer activation, subscription, receipt email, automatic fulfillment or refund is created.

## Baseline and release boundary

- Isolated clone: `/private/tmp/adzbyte-qrph-backend-20261022`, branch `codex/qrph-package-checkout`.
- Base: `eda1d21cc641da7c8d3256c108da3c9d7a74bb8a` (`deploy`, source `12ed0c81d2350b6006fa1d6a8749e77ef326ebcf` plus built assets only). Read-only GitHub `ls-remote` verified this deploy ref on 2026-10-22 Manila time.
- The original application checkout was read only. The current main commerce changes/migrations were not imported. This branch adds one migration containing two tables.
- Feature defaults off. The backend implementer made no provider calls or production changes; the parent subsequently qualified sandbox QR generation as recorded below. No real payment has been made.
- The existing release workflow promotes main. Selective promotion of this release-based source needs separate review; merging unrelated main commerce work or replacing deploy with this feature branch is not authorized by these tests.

## Backend offer authority

`config/package_checkout.php` owns version `2026-10-22`, currency `PHP`, quantity `1`, and these minor-unit amounts:

| Code | Name | Amount (centavos) |
| --- | --- | ---: |
| starter | Starter | 77700 |
| business | Business | 349900 |
| growth | Growth | 649900 |
| pickleball | Pickleball | 250000 |

Custom has no purchase route/offer. Only the Pickleball promotion ends `2026-12-01T00:00:00+08:00`. New Pickleball QR creation closes during the last 60 seconds because the provider's minimum QR duration is 60 seconds. Each QR uses at most 1800 seconds; Pickleball's conservative local deadline is capped at its promotion end. Starter, Business and Growth remain available and receive a full 1800-second QR after December 1. Existing receipts, operation resolution, replay and authentic paid settlement remain available after the promotion ends.

## Exact integration contract

All responses are private/no-store and errors are generic JSON, including with APP_DEBUG enabled. The Next server sends `Authorization: Bearer <PACKAGE_CHECKOUT_API_TOKEN>` on all four integration routes. Browser-origin requests with an Origin header are rejected. There are no redirects or newly public CORS mutations.

| Method and path | Additional requirement | Success |
| --- | --- | --- |
| GET `/api/v1/package-offers/{slug}` | One of the four codes | 200 offer resource |
| POST `/api/v1/package-checkouts` | Proof header and exact body below | 201 first reservation; 200 matching replay |
| GET `/api/v1/package-checkouts/{ULID}` | Proof header | 200 checkout resource |
| GET `/api/v1/package-checkouts/resolve/{operation UUID}` | Proof header | 200 same checkout resource; 404 absent/wrong proof |

`X-Checkout-Proof` is 32 random bytes created by the Next server, encoded as lowercase hex64 (preferred) or base64url43. Only its SHA-256 hash is persisted. The same proof and operation UUID must survive retries/reload in a protected HttpOnly cookie. The browser never supplies the server integration token. Resolution and status read local state and never call PayMongo.

Create JSON contains exactly `package_code`, `offer_version`, `operation_id` (UUID), `business_name` (1–160 characters), and `email` (RFC email, maximum 254). Email and operation UUID normalize to lowercase; outer whitespace is trimmed from email/business name. Unexpected fields, including amount, quantity, provider IDs, status, billing, phone and return URL, produce 422. The business name is a business detail, never an invented payer name. No customer details are sent to PayMongo.

Offer response:

```json
{"data":{"code":"starter","name":"Starter","amount_minor":77700,"currency":"PHP","quantity":1,"version":"2026-10-22","available":true,"test_mode":true}}
```

Checkout response:

```json
{"data":{"reference":"01K00000000000000000000000","status":"pending","test_mode":true,"offer":{"code":"starter","name":"Starter","amount_minor":77700,"currency":"PHP","quantity":1,"version":"2026-10-22"},"qr_image":"data:image/png;base64,...","expires_at":"2026-10-22T04:30:00+00:00"}}
```

`test_mode` derives from validated provider mode for offer reads and the immutable checkout mode for receipts. Public statuses are `creating`, `pending`, `unknown`, `failed`, `expired`, `paid`. There is no separate public `reserved` state. `qr_image` is null outside pending; expiry is nullable before the method is durably reserved. Images must be PNG data URIs, maximum 350000 characters, decode to a PNG and have dimensions at most 2048×2048. Client keys, provider IDs, guest proof, customer details and secrets are never serialized into these responses. Generic errors use 401/403/404/409/413/422/429/503 as appropriate.

## Persistence, concurrency and failure rules

`package_checkouts` preserves an immutable offer/amount/customer/mode snapshot. Business name and email use Laravel authenticated encryption at rest. Admin reference/status columns remain queryable; encrypted customer fields are not searched. Operation UUID has a unique index; request/proof hashes must match on replay. A mismatched reuse returns 409 without disclosing the existing reference.

The winning transaction reserves the row before any provider POST. Creation phases persist before intent creation, method creation and attach. A concurrent retry reads the existing reservation and never sends another POST. Each step is sent once; there are no automatic provider POST retries or automatic new operations after an ambiguous result. Transport/HTTP/schema failures become unknown, retaining any verified provider IDs. A process interrupted in creating becomes unknown after two minutes when read. Unknown requires operator review; GET never assumes paid from a provider intent state.

Native calls use fixed HTTPS PayMongo endpoints and server Basic secret authentication, reject redirects, and have bounded connect/request timeouts. Intent amount, currency, mode, allowed method and reservation metadata must match. Method must be QRPh in the correct mode. Attach must match the saved intent and return awaiting_next_action with a valid PNG. Provider bodies/errors and authenticated headers are neither logged nor returned. Scoped exception rendering also suppresses raw exception reporting to avoid leaking arguments.

## Webhook settlement

POST `/api/v1/webhooks/package-qrph` needs no integration bearer/proof. It requires a valid `Paymongo-Signature` over the raw body with a timestamp within 300 seconds. Test accepts only te and empty li; live accepts only li and empty te. Duplicate/unknown signature fields, malformed JSON, future event timestamps and mode mismatch fail closed.

`payment.paid`/`payment.failed` require a matching PI, exact integer amount/currency/livemode, payment type/status and QRPh source. Paid also requires a valid paid_at timestamp. The locked paid transition and event receipt commit together. A valid paid event wins even after failed/expired; later failed/expired events cannot reverse paid. Another payment for an already-paid intent is retained as payment_conflict rather than overwriting settlement. Receipts store only event ID/type, raw-body hash, outcome and checkout reference. Repeated identical event IDs are idempotent; changed bodies conflict.

If intent creation timed out before its ID was saved, a signed payment event may trigger one safe GET of that intent. Recovery requires its authoritative metadata reference, operation UUID and version, amount/currency/mode/QRPh allowlist to match the existing intent_reserved checkout. Event metadata alone never links a payment. A temporary/invalid provider read returns 503 for a later delivery; it does not receipt the event as processed. There is no provider-list scan or new resource creation.

qrph.expired currently accepts a matched payment_method resource with QRPh type/mode/id. The public official pages enumerate the event without providing its exact resource sample; other payload variants are retained as unmatched receipts. Independent local expiry still removes the QR on time. The actual signed provider payload must be qualified before activation.

Rate limits: offers/status 120/minute per source IP; create 30/minute globally plus 10/minute per source IP; webhooks 300/minute globally plus 120/minute per source IP. Proof rotation does not bypass these limits. Request bodies are bounded at 4096 bytes for integration and 500000 for webhooks.

## Manual fulfillment and review

Existing verified administrators/super administrators can read `/admin/package-checkouts` and `/admin/package-checkouts/{reference}`. Customer/no-role/anonymous users cannot read it. The policy forbids create/edit/delete. Staff see the encrypted customer details only through the authenticated view, exact package amount/version, paid time and safe provider references for manual verification/fulfillment. This flow sends no notifications and starts no fulfillment automatically.

## Verification

- Final corrected source: PHP 8.5.11 SQLite full suite **86 tests / 591 assertions**, Pint, Composer strict metadata validation, whitespace check, Laravel optimize and optimize:clear pass. The correction proves new Starter/Business/Growth purchases keep a 30-minute QR after December 1, new Pickleball is unavailable, and an existing Pickleball receipt still resolves/replays and accepts an authentic signed paid event after the cutoff.
- PHP 8.5.11, SQLite: full baseline plus feature suite passed 86 tests / 573 assertions before the Pickleball-only deadline correction; final correction counts recorded below.
- Disposable MariaDB 13.0.2 over an isolated /private/tmp Unix socket: same full 86 / 573 suite passed before that correction. It is distinct from MySQL qualification.
- Local bundled MySQL 8.4.0 crashed during initialize-insecure before any server/database became usable. No MySQL result is claimed.
- Both session-owned disposable HTTP and MariaDB servers were stopped. The loopback router remains available for the parent-owned Next/browser qualification. No commit was created; the Main Orchestrator owns final review, commit and scoped release workflow changes.
- Disposable SQLite migration apply, full rollback and reapply passed (five baseline migrations plus the one new migration).
- Tests block stray HTTP and cover mode/auth/proof, amount/quantity/extra-field tampering, native fake-provider calls, customer encryption/resource privacy, replay/conflict, concurrent reservation observation, each POST timeout, lost-response resolution, promo/QR expiry, stale creating, malformed provider images/intent, signature mode/timestamp/body checks, event replay/cross-mode/invariant rejection, late paid settlement, unknown-PI metadata recovery, rejected metadata/amount, rate/body limits and authenticated read-only admin access.
- A loopback-only `tests/Support/package-qrph-http-router.php` boots the real Laravel routes with Http::fake and preventStrayRequests. It requires APP_ENV=testing, DB_CONNECTION=sqlite and a DB_DATABASE matching `/private/tmp/adzbyte-package-http-<name>.sqlite`. It cannot serve as a production entrypoint. The integration token and webhook secret are the synthetic test constants in that file. Its fake PNG is a 1-pixel placeholder, not a payable QR.

## Activation requirements (still pending)

1. Independent review of this branch and Next BFF/UX; selected release promotion from the verified deploy baseline; PHP 8.3/MySQL CI and real Next-to-Laravel HTTP qualification.
2. Explicitly authorized provider sandbox qualification of the exact server-secret intent/method/attach contract (including omitted payer name/phone), real response schemas/PNG/expiry, metadata retrieval and actual signed payment.paid/payment.failed/qrph.expired payloads. No real provider call is authorized by this document.
3. Merchant QRPh capability/method limits, business terms/refund/tax obligations, manual fulfillment responsibility, monitoring and replay procedure for missed webhook delivery. The app has no automatic refund or unsupervised manual paid override.
4. Deliberate production migration/backup, stable encryption APP_KEY, strong dedicated integration token, separate mode-correct QRPH_PAYMONGO_SECRET_KEY and QRPH_PAYMONGO_WEBHOOK_SECRET, HTTPS and a registered/reachable webhook for the three events. Do not switch mode while attempts are outstanding. Keep webhook handling active until outstanding records are reconciled.
5. Set PACKAGE_CHECKOUT_ENABLED=true only after those checks; configure Next with the matching secret/origin and persistent HttpOnly proof cookie. Synthetic/test mode must remain visibly labeled in the UI. Re-enabling an endpoint does not itself recover missed provider events.

On a separately approved release, inspect the selected artifact diff against the verified deploy source and permit only these package files plus reviewed integration changes. Back up production first, retain the existing APP_KEY, and apply only `database/migrations/2026_10_22_000000_create_package_checkouts.php` using the approved migration procedure. Do not run an unreviewed whole-main migration batch. Authorized staff use the existing verified administrator login and Package checkouts list; no new role seed or account is required. Register the dedicated HTTPS webhook at `/api/v1/webhooks/package-qrph` for payment.paid, payment.failed and qrph.expired in the intended provider mode, store its signing secret, confirm successful signed deliveries, and retain the existing account payment webhook separately. These are deployment instructions, not an executed deployment.

### Synthetic HTTP fixture bootstrap

From the isolated clone, with a synthetic APP_KEY in its ignored local .env, choose a fresh matching database name:

```sh
touch /private/tmp/adzbyte-package-http-integration.sqlite
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/adzbyte-package-http-integration.sqlite php artisan migrate --force
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/private/tmp/adzbyte-package-http-integration.sqlite php -S 127.0.0.1:8984 tests/Support/package-qrph-http-router.php
```

Point only the local Next server at `http://127.0.0.1:8984` with the synthetic token declared in the fixture. The router sets the test provider secret and webhook signing secret internally and blocks stray HTTP. Stop the loopback server when finished. No production/provider credential is needed. Nine real HTTP checks passed against this fixture: offer marker, create pending, stable replay, operation resolution, wrong-proof denial, wrong-token denial, Origin rejection, synthetic signed payment and final paid status. Evidence: `/private/tmp/adzbyte-qrph-backend-http-evidence.json`; the fixture server was stopped afterward.

Official references checked 2026-10-22: [native QRPh API](https://docs.paymongo.com/docs/payment-acceptance-qr-ph-api), [create Payment Method allows public or secret key](https://docs.paymongo.com/reference/create-a-paymentmethod), [attach endpoint](https://docs.paymongo.com/reference/attach-to-paymentintent), [PI metadata/resource](https://docs.paymongo.com/reference/the-payment-intent-object), [server secret permissions](https://docs.paymongo.com/docs/developer-tools-best-practices-1), [raw-body signature and modes](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management), [payment event samples](https://docs.paymongo.com/docs/developer-tools-webhooks-events), [QRPh event list](https://docs.paymongo.com/docs/payment-acceptance-qr-ph), [webhook retry/acknowledgment contract](https://docs.paymongo.com/reference/webhook-resource). Server-secret attach is inferred from secret-key full access and the PI client-key description; the guide's worked attach example uses a public key/client key, so actual sandbox verification remains required.

### Provider and integration follow-up

The parent verified the exact adapter's three calls against PayMongo **sandbox** on October 22: create PI, create QRPh method (60 seconds), and server-secret attach each returned HTTP 200 and passed strict resource checks. No payer details were sent and no payment was made or scanned. Sanitized response-key/HTTP evidence is `/private/tmp/adzbyte-native-qrph-provider-evidence.json`. Actual signed event delivery remains pending.

The scoped release workflow now supports `scope=qrph-package` only from `codex/qrph-package-checkout` with an exact independently reviewed source SHA. It requires the unchanged deployed baseline twice, an exact changed-path allowlist, passing reusable CI including PHP 8.3/MySQL 8.4 package tests, production environment approval and a normal non-force push. Main's unrelated commerce history is excluded. Runtime Next integration credentials must be configured in Hostinger environment variables and must not be added to the Git-generated environment file.

The parent BFF retains the guest receipt/proof for seven days and offers no automatic reset after failed or locally expired states. Authentic late paid events remain visible through status checks. A cleared cookie or another browser is a guest-session recovery limit requiring reference-based staff assistance.

### Required dependency remediation

The deployed baseline failed the current mandatory dependency audit. The reviewed package branch updates only composer.lock and package-lock.json within the existing root constraints and PHP 8.3 platform. Composer and npm audits now report zero vulnerabilities. After remediation, the full SQLite suite passed 86 tests / 591 assertions; Pint, strict Composer validation, clean npm install and Vite production build passed. The exact change inventory and advisory evidence are preserved at /private/tmp/adzbyte-qrph-dependency-remediation.json. MySQL/PHP 8.3 CI is still required before promotion.
