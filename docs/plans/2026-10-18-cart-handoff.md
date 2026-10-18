# C2.3 storefront controls and app cart handoff

Status: in progress; C2.1 and C2.2 qualified at 31274f3 / 8656e31 before this
card began. No deployment or live credentials.

The Next storefront owns Add to Cart and a compact server-returned summary.
Laravel owns the full cart and all prices, availability, category rules and
command outcomes. After a successful restricted-category replacement, show an
accessible status message naming the replaced service and new selection. Do not
announce a replacement before the server commits. Render names as plain text;
keep retry effects stable and prevent duplicate/out-of-order UI updates.

## Browser authority and retry recovery

Next server routes keep the service token server-only. Bootstrap an encrypted,
authenticated, HttpOnly host-only cookie with a cryptographically generated
guest proof and stable creation UUID before the first mutation. No guest proof
or service credential appears in response JSON, browser bundles or URLs. A lost
create response is recovered with the proof-scoped read-only resolve operation.
Missing/tampered cookies cannot select an arbitrary cart. Enforce an exact
configured trusted Origin and JSON for mutations; never infer a trusted origin
from caller Host/forwarded headers. Persist server cart contents without expiry;
browser cookie/storage loss does not delete the cart record. Account attachment
in C3 provides durable authenticated recovery across browsers.

## Transfer link lifetime

View Cart requests a separate opaque token valid for **60 seconds and one use**.
It transfers access to this cart into the app's guest session. It does not expire
the cart, items or prices. On expiry/replay, request a fresh View Cart link.
Only the fixed app `/cart` destination is allowed; no caller-controlled return
URL. Mint/consume must lock and revalidate cart authority, be retry-safe, and
consume atomically under independent-process contention.

Keep only the transfer-token hash in persistence. Bind issuance to the cart,
current guest authority and active narrow service credential. Expiry, token
revocation, ownership transfer and credential changes invalidate outstanding
grants. Store the resulting app authority only in the trusted server session;
customer identity is not attached until C3's authenticated merge/claim flow.
Never infer an owner from a public cart ID or administrator role.

Mint uses a new narrow `cart:handoff` ability. A durable per-cart command receipt
records the issuance nonce, expiry and issuing credential reference; no transfer
secret. The opaque value derives from an application-key HMAC over the issuance
nonce and bound context, so a lost mint response can recover the same value.
Expired/used/revoked issuance retries require a fresh operation. Never revive an
old token after cleanup. Only expired transfer rows older than 24 hours may be
pruned; immutable command history remains and prevents UUID reissuance. Cart
records/items are not pruned or expired by that operation.

Use a dedicated app landing page with no third-party scripts. Deliver the
short-lived token in a URL fragment so it does not enter HTTP request/referrer
logs; immediately clear it from browser history and POST it to the same-origin
CSRF-protected consume route. The page then redirects to the full cart. Failures
show a generic expired/used-link message with a fresh-link recovery instruction,
without reflecting the token. Revalidate all cart state before display/actions;
no order or payment is created by transfer.

Expose documented app-session operations under `/api/v1/cart` plus
`/api/v1/cart-handoffs/consume` through the app's web/session/CSRF middleware.
These are distinct from service-token storefront routes: app guest authority
comes only from the trusted server session created by an atomic consume. API
requests cannot submit a cart owner/reference/hash to select another cart.
App form adapters use the same services. Keep safe private Resources and hide
unpublished current content/prices just as in C2.2. The landing page has a
dedicated nonce CSP/no-referrer policy and no third-party scripts; global headers
must preserve its stricter policy. Real CSRF tests and browser fixtures must run
with the unit-test bypass disabled.

## Gates

Shared service and API positive/forbidden/retry tests; malformed/expired/replayed
and revoked grants; cross-cart and return-destination rejection; real contention
and populated migration recovery on SQLite/MySQL/PHP 8.3. Actual built Next
list/detail Add, quantity-one duplicates, replacement notice, mixed categories,
price/availability changes, error/retry, private-cookie/server-token boundaries,
View Cart transfer and app remove/review controls at desktop/mobile widths.
Audit browser bundles/network, console, keyboard/live status and document
overflow. Full suites/builds/audits/current source CI in both repositories;
production promotion remains separate. Registration/login merge belongs to C3.

## Backend implementation checkpoint

Local PHP 8.5 suite: 292 tests / 5,200 assertions, including real CSRF tests,
four-process mint/consume contention, populated migration recovery and issuer
revocation/key/authority rotation. Pint and production asset build pass.
PHP 8.3 database CI and actual integrated desktop/mobile browser proof remain
pending; this card is partial. No C3 implementation has begun.

App sessions use the configured session lifetime; session/browser storage loss
does not delete or expire persistent cart contents. Guest recovery depends on
retained authority; authenticated account recovery is a separate C3 gate.
