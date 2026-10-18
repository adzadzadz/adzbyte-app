# Storefront cart API

The implemented operations and schemas are in [OpenAPI](openapi.json).
These are server-to-server endpoints under `/api/v1/storefront/carts`. They do
not accept browser session authentication or create orders/payments.

Every request needs a narrow Sanctum Bearer token **and** `X-Cart-Proof`, a
cryptographically generated 32-byte guest secret encoded as 64 lowercase hex
characters. The token is limited to `cart:create`, `cart:read`, `cart:write` and
belongs to a verified service identity with no roles or direct permissions.
Wildcard, unrelated or excessive abilities fail closed. A public cart reference
or a service credential alone cannot read a cart. Customer/administrator accounts
cannot be used as service identities.

Mutation bodies must be JSON objects. Supply a UUID `Idempotency-Key` and reuse
it only for an identical command. No owner, quantity, total or price amount is
accepted. A cart is quantity one per product and has no time-based expiry.
Unknown body/query fields, malformed proof and keys are rejected. GET/HEAD are
bodyless. All responses are private/no-store without an ETag or session cookie,
including when a configured first-party Origin is sent.

| Operation | Ability | Result |
|---|---|---|
| POST `/carts`, `{}` | cart:create | Create/recover the same proof-bound cart for the creation UUID |
| GET `/carts/resolve?creation_operation_id=<uuid>` | cart:read | Recover a lost creation response without writing on GET |
| GET `/carts/{cart}` | cart:read | Current safe summary and review/availability flags |
| POST `/carts/{cart}/items`, `{product:<ULID>}` | cart:write | Add or replace the prior restricted-category selection |
| DELETE `/carts/{cart}/items/{product}`, `{}` | cart:write | Remove a line, including an unavailable line |
| POST `/carts/{cart}/confirm`, `{versions:{<ULID>:{price:N,terms:N}}}` | cart:write | Confirm only the exact current offer versions the customer reviewed |

`effects.replaced_products` contains only public references/names from the
committed command. It survives a mutation retry; a read returns no replacement
effect. Display an accessible notification after a successful replacement.
Availability is checked before replacing anything, and services from other
categories remain. C2.3 owns the actual Next controls and notification rendering.

Money is an exact decimal **string in PHP minor units**, including subtotals
larger than native integer range. It is not a final tax/receipt/order total.
`selection_valid` is a catalog/cart check, not permission to create or pay an
order. Changed prices/terms require current-version confirmation. Draft/archive
lines return an unavailable placeholder; their current content, price and terms
are withheld and the subtotal is incomplete. Publicly unavailable published
lines remain visible but cannot be confirmed as purchasable.

The named limiter allows 600 requests/minute per service identity and 120/minute
per service/proof. A 429 includes Retry-After. Response errors use the existing
v1 envelope; proof mismatches resolve to 404, invalid input/offer/UUID reuse to
422, and invalid service authority to 401/403. Do not cache response bodies or
use the generic identity-scoped idempotency middleware for these operations:
CartActions authorizes before durable receipt replay and returns fresh state.

## Credentials and rotation

No public issuance endpoint exists. Console commands below mutate the selected
application database and print a credential once. Source development runs them
only against disposable synthetic fixtures. Production issuance, secret changes
and deployment require their own explicit authorization.

```sh
php artisan commerce:storefront-token service-identity@example.test --create
php artisan commerce:storefront-token service-identity@example.test --name=replacement
php artisan commerce:revoke-storefront-token TOKEN_ID
```

`--create` refuses an existing identity, including a case variant of a customer
email. It creates a dedicated verified roleless identity with a random password;
it cannot enter either panel. Existing identities must already be verified and
have no roles/permissions. Optional `--abilities=cart:read` issues a narrower
token; `--days=90` defaults to 90 and accepts 1–365. Tokens are stored hashed.
Issue the replacement, store it only in the consumer server's secret settings,
verify that consumer, then revoke the identified old token. The revoke command
refuses unrelated/wildcard tokens. Audit logs contain identity/token IDs and
expiry, never the credential. Token expiry/revocation does not delete a cart.

Never place token/proof in a browser bundle, URL, log, fixture or committed env
file. The future Next proxy keeps both server-side in an encrypted HttpOnly
host-only cookie/secret configuration and enforces its own trusted Origin.
Handoff transfer is a separate C2.3 one-use 60-second capability; its expiry
does not expire the persistent cart.
