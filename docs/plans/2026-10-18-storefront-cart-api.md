# C2.2 restricted cart REST contract

Status: implemented, verification pending. C2.1 qualified at 31274f3 before this
card began. The full supported-runtime/database CI gates remain pending here.

## Authority and operations

Add `/api/v1/storefront/carts` separately from the broad integration routes.
Require an actual revocable Sanctum personal token belonging to a verified,
unprivileged service identity with no panel/customer roles or direct permissions.
Allow only the exact narrow abilities `cart:create`, `cart:read`, `cart:write`;
reject wildcard, unrelated or excessive abilities and first-party session tokens.
There is no public token issuance endpoint. A guarded console operation may
issue/revoke a scoped token for an existing dedicated service identity; production
issuance/secret changes remain separately authorized.

Service authority is insufficient alone: every cart request also requires
`X-Cart-Proof`, a server-generated 32-byte secret encoded as lowercase hex.
Construct guest CartPrincipal from that proof and scope every query before
policy/replay handling. Never accept an owner ID, total, quantity or quote amount.
No browser bundle receives the service token or guest proof. Never put proof in
URLs, request logs, response JSON or saved idempotency responses. Error responses
retain the existing stable v1 envelope and private no-store headers.

- POST `/carts`: empty JSON body, proof and UUID `Idempotency-Key`; same guest
  proof/operation retries return the same cart through CartActions.
- GET `/carts/resolve?creation_operation_id=<uuid>`: proof-scoped read-only
  recovery for a lost create response. No record creation or ownership guessing.
- GET `/carts/{public_id}`: fresh server summary, never cached.
- POST `/carts/{public_id}/items`: JSON `{product:<ULID>}` and UUID key; add or
  replace within the restricted category. Return `effects.replaced_products`
  (public reference/name only) from the committed receipt, including on retry.
- DELETE `/carts/{public_id}/items/{product}`: empty JSON body and UUID key.
- POST `/carts/{public_id}/confirm`: JSON `{versions:{<productULID>:{price:N,terms:N}}}`
  and UUID key; exact current offer review only. No order/payment is created.

Allowlist all body/query/header input, reject malformed and unknown fields, and
rate-limit both total service use and individual cart proof scopes. Apply no
generic response-cache idempotency middleware: CartActions owns durable receipts,
reauthorizes before replay and returns current availability/prices with original
command effects. Public catalog behavior remains unchanged.

## Consumer and later handoff boundary

Return an explicit Cart Resource with public reference/revision, quantity-one
safe Product Resource lines, quote version references, availability/review flags,
category conflicts, exact string PHP subtotal, completeness/selection validity
and committed replacement effects. No internal IDs, customer identity, hash,
operation UUID or arbitrary model serialization. The subtotal is not a final
tax/receipt/order total. Read responses have no replacement effect to reannounce.

C2.3 Next server routes will bootstrap an encrypted HttpOnly host-only cart
cookie containing a cryptographically generated proof and stable creation UUID
before mutations. Resolve allows recovery without writing on GET. The actual
API token stays server-only. POST/DELETE require an exact configured trusted
Origin, JSON and same-origin transport; no caller-controlled return destination.
Replacement notifications are shown only after the successful server response,
in an accessible status region that names the replaced selection. Future handoff
uses a separate opaque one-use 60-second token and an app-owned guest session;
its expiry does not expire the cart or its items.

## Required verification

Real Bearer-token happy path; absent/revoked/expired/wildcard/excessive tokens,
panel/customer identities and session fallback denial; cross-cart proof/owner
injection, UUID reuse/recovery, all input/error/cache/rate-limit boundaries;
safe serialization and exact totals/effects; OpenAPI validation and a real HTTP
Node consumer using only synthetic disposable credentials. Full regression and
both database/PHP 8.3 CI gates. No provider or browser UI is introduced in C2.2;
actual Next controls, notice and full-cart/handoff browser proof remain C2.3.

## Evidence checkpoint

Local PHP 8.5.11/SQLite full suite: **266 tests / 4,929 assertions**, passed.
Includes 17 API cases (data-provider expansions included), five credential
command cases and the independent actual HTTP/Node consumer. Tests exercise
real Sanctum Bearer tokens, wildcard/excessive/read-only scopes, revoked/expired
credentials, role/direct-permission and unverified identities, first-party
session fallback, independent cart proof, ownership/quantity/total injection,
UUID reuse, resolve recovery, no-store/ETag/cookie behavior, strict JSON objects,
bodyless GET/HEAD, current-offer review/removal, JSON schema examples and safe
projections. A configured first-party Origin with an actual Bearer token remains
stateless and creates no session/cookie. Case-insensitive dedicated-identity
creation refuses an existing customer rather than creating a roleless duplicate.

The independent Node consumer uses the actual localhost Laravel HTTP kernel and
database cache, not API mocks. It proves mixed-category replacement with stable
notice effects, exact subtotal beyond native integer range, create retry and
proof-scoped recovery, wrong proof denial, confirmation/removal/empty cart,
OpenAPI schemas/examples and a 429/Retry-After persisting across real HTTP
requests. The PHP suite also sends 600 distinct-proof requests to prove that
changing proof cannot bypass the separate service-wide limiter. All credentials
are synthetic environment-only fixtures and are destroyed with their isolated
database/server; no existing database or real secret was changed.

Resource review found a draft-data boundary: an item saved while published may
later become a draft/archive. The explicit cart projection now withholds its
current name/content/price/terms, uses an unavailable placeholder and marks the
subtotal incomplete. Replacement notices likewise withhold a now-private name.
The regression exercises unpublished content and revised draft prices. No new
schema/dependency/provider/browser surface is introduced. Existing migration,
content/API and independent-process cart/category/catalog regressions pass.

Pint, Composer strict metadata, whitespace and actual route/middleware discovery
pass. Current PHP 8.3 CI on both supported databases, audits/build/cache and
pushed-source readback remain required before C2.2 completion or C2.3 work.
See [caller/credential documentation](../api/storefront-carts.md). Actual Next
controls, customer notice rendering and handoff are intentionally unimplemented
until that gate passes; C2 aggregate is incomplete.
