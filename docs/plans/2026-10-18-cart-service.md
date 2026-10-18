# C2.1 persistent cart service

Status: implemented, verification pending; category prerequisite qualified at
4859e4e / frontend f202534 with green CI. No existing database
will be migrated. REST credentials, transport/cookies, handoff and full cart UI
are C2.2/C2.3, followed by login attachment in C3.

## Contract

- Anonymous/customer carts and quantity-one distinct product lines persist
  without any time-based expiry. Availability, current price/terms and category
  rules are revalidated. No real cart or catalog seed is introduced.
- A restricted category permits one product; regular/uncategorized products
  coexist. Owner clarified that a different selection automatically replaces
  the previous service in that category and notifies the customer. Record the
  replaced product references/names in immutable command effects so retries
  still explain what changed. Availability is checked before replacement; an
  invalid replacement must leave the original cart intact. Later login conflicts
  require an explicit choice. Administrative rule/product changes can invalidate a saved
  selection and must be surfaced on the next read.
- Cart authority is distinct from a future Next service credential: guest
  authority is a cryptographically generated bearer secret stored only as a
  hash, and customer authority is the authenticated customer's identity. Scope
  queries by authority; administrator roles and public references do not grant
  cross-customer access. Adapters must derive customer identity from auth.
- A current price/terms version is quoted on add. A duplicate add remains one
  line and must not silently accept later versions. Explicit confirmation must
  supply the versions the customer actually reviewed and reject intervening
  changes. Unavailable/unpublished/archived products cannot be added/confirmed.
- Calculate exact PHP minor-unit subtotals on the server, including totals
  larger than native integer range without floating-point conversion. Subtotal
  is not the tax/receipt/final order total; D02 and checkout decisions remain.
- Transactional cart-row locks serialize mutations. Per-cart operation UUID
  receipts reject conflicting reuse, prevent duplicated retries, and preserve
  actor/time history. Authorize before replay. Creation retries also retain one
  cart. Cart history does not store bearer secrets.

## Applicable verification

Positive/negative authority and role tests, no-expiry time travel, quantity-one
duplicates, category rejection/revalidation, current availability, exact money,
explicit price/terms confirmation and intervening changes, immutable history,
retry/conflict handling, independent-process races on both SQLite and MySQL,
populated upgrade and rollback/reapply preserving catalog/version/category and
earlier identity state. Full PHP 8.3 CI, audits/build/cache/format/metadata and
source readback. No new browser workflow, HTTP contract or provider is exposed
in C2.1; those gates apply when C2.2/C2.3 adapters are introduced. C2 aggregate
remains incomplete until the actual Next-to-app journey passes.

## Current evidence

Local PHP 8.5.11/SQLite: full suite **243 tests / 1,671 assertions** passes,
including 16 cart-domain/authority cases, populated upgrade/reversal and five
four-process cart race scenarios. Older catalog/category migration tests now
explicitly target their preceding migration boundary so they continue proving
their original reversals after this later schema is added. No real DB migrated.

Covered: two-year no-expiry time travel; native-max plus native-max exact
subtotal; distinct restricted/regular category coexistence and replacement
with durable notification effects; later rule changes; revoked customer roles; cross-guest/customer
denial including the SuperAdmin Gate override; immutable history and cart/offer
identity; current-price/terms confirmation, intervening change atomic rollback,
replays that cannot re-add removed products or silently confirm newer prices;
unavailable/draft/archive rejection and removable retained lines. Four independent
workers prove create retry, identical add retry, same-product deduplication,
regular-product additions and restricted-category contention. Bounded retries
handle SQLite contention and MySQL deadlocks without replaying partial writes.
Current price/terms/category reads use database locking reads rather than
potentially stale MySQL consistent snapshots after waiting for another writer.

Pint and whitespace checks pass. Both supported-engine/PHP 8.3 CI gates and
remaining source-delivery checks are pending; C2.1 remains partial. No new HTTP,
panel, browser or provider surface exists in this card; their integration gates
remain required for C2.2/C2.3. CartPrincipal stores a hash only; C2.2 must generate
the actual guest secret cryptographically and keep it out of browser bundles,
public responses and logs. Audit snapshots contain no bearer credential.

The initial service source `384406e` passed both database jobs in
[CI 39088744430](https://github.com/adzadzadz/adzbyte-app/actions/runs/39088744430).
The owner then clarified automatic replacement with notification; that updated
source requires its own CI qualification. Local full 243 / 1,671 passes after
that clarification. Simultaneous restricted-category selections now all commit
in serialized order, retain one final line, and record exactly three replacement
notices for four different adds. Identical operation retries replay the original
notice effects without reapplying or duplicating the selection. Availability is
validated before any removal. Notification rendering remains the C2.3 UI gate.

Replacement source `de764cb` passed the application CI job, but MySQL CI
39089021793 caught a test comparing JSON object key order with `assertSame`.
MySQL returned the same public reference/name in canonical member order. The
retry assertion now compares JSON object values with `assertEquals`; list
contents and all notification values remain asserted, with no gate skipped or
application behavior changed. Fresh supported-runtime CI is required after this
test correction; no dependent implementation has started.
