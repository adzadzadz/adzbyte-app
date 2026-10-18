# C2.1 persistent cart service

Status: planned; category prerequisite must qualify first. No existing database
will be migrated. REST credentials, transport/cookies, handoff and full cart UI
are C2.2/C2.3, followed by login attachment in C3.

## Contract

- Anonymous/customer carts and quantity-one distinct product lines persist
  without any time-based expiry. Availability, current price/terms and category
  rules are revalidated. No real cart or catalog seed is introduced.
- A restricted category permits one product; regular/uncategorized products
  coexist. On a conflicting add, preserve the existing selection and return a
  removal prompt. No implicit replacement. Later login conflicts require an
  explicit choice. Administrative rule/product changes can invalidate a saved
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
