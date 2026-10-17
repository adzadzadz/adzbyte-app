# C1.1 — Dynamic Catalog Domain

Status: specified test-first and locally implemented/verified on 2026-10-18.
Follow-up: C1.2 shared actions/policies now pass local and PHP 8.3 CI; see the
[action contract](2026-10-18-catalog-actions.md). C1.1 boundaries below describe
the original domain slice; C1.3 panel and C1.4 REST adapters remain pending.
Parent: [backend plan](2026-10-17-backend-development.md). Product authority:
[dynamic catalog requirements](2026-10-05-commerce-platform.md#dynamic-service-catalog-confirmed-2026-10-17).

## Persistence contract

| Table/model | Fields and invariants |
|---|---|
| `products` / `Product` | Internal bigint ID, unique immutable ULID `public_id`, nullable unique slug, nullable draft name/summary/description, JSON media references, status (`draft`, `published`, `archived`), separate boolean `is_available`, timestamps |
| `product_prices` / `ProductPrice` | Product FK, positive integer version, integer nonnegative `amount_minor`, explicit `PHP` currency, timestamps; unique product/version; immutable after creation |
| `product_terms` / `ProductTerms` | Product FK, positive integer version, JSON `content` document, timestamps; unique product/version; immutable after creation |

No real packages, amounts, or terms are seeded. Drafts can be empty. Terms are
versioned data documents (scope, deliverables, exclusions, obligations,
fulfillment, entitlements as needed); this slice does not interpret them as
executable rules or define particular package benefits. Media contains references,
not uploads; storage, rendering, and input allowlists belong to later adapters.

The current price/terms relationship selects the highest version **within the
product**. Version numbers are internal revisions, not a price ordering. C1.2
must allocate the next revision while holding the parent lock; database unique
constraints reject competing inserts of the same product/version. This slice
does not claim multi-process revision allocation has been implemented.

Product FKs restrict deletion. Model operations reject product deletion and
version update/deletion; preserve records and archive products instead.
Eloquent bulk updates/deletes, event-suppressed writes, and raw SQL bypass model
events: later services must use normal model writes, not bulk/quiet mutation of
immutable data. Future orders will
also need restrictive FKs and independent accepted snapshots; they do not exist
in C1.1. No destructive migration or data rewrite is included.

## Lifecycle and validation

- New products are drafts and unavailable. `public_id`, status, and availability
  cannot be assigned through ordinary content mass assignment.
- Draft → published or archived; published → draft or archived; archived →
  draft only. Restoring an archive requires a separate later publish operation.
- Publishing requires a nonblank name. Draft content may be incomplete and
  unpriced. A published listing is not automatically purchasable.
- Availability may be enabled only for a published product with a positive
  current PHP price and a nonempty current terms document. Unpublish/archive
  must also disable availability. An explicit zero price may be recorded, but
  cannot enable a paid offer; missing price never means free.
- Price input is validated before integer casting, rejecting fractional,
  negative, overflowing, boolean, floating-point, and nonnumeric values. Models
  require native integers for amounts, revisions, and owner IDs; future adapters
  must normalize validated form input explicitly. Version is a positive integer;
  currency is explicit PHP. No tax policy or provider minimum is invented.
- A published-product query excludes drafts/archives and may include unavailable
  listings. `hasConfiguredOffer()` rechecks the latest price/terms even when
  relations were loaded earlier; incomplete newer revisions make it false
  regardless of the stored availability flag. Available-offer configuration is
  not payment authorization: C2/O1/O2
  must revalidate policy, price/terms, tax, approval, and ownership later.

The PHP models are trusted persistence primitives. Lifecycle fields are changed
via explicit trusted assignment for now; no route or screen exposes this.
C1.2 will add actor-authorized transaction services, capabilities, lifecycle
audit, and concurrency handling. C1.3 adds admin screens; C1.4 adds the safe
public API and cache invalidation. No new API contract is published in C1.1.

## Acceptance coverage

- Empty catalog and default seeding create no packages; incomplete draft and
  synthetic factory data persist without a price.
- Content edits retain stable identity; identifier changes and physical model
  deletion fail. Only the allowed lifecycle transitions succeed.
- Publication needs a name; availability needs published state, positive
  current price, and nonempty terms; missing/zero prices never enable an offer.
- Multiple products and price/term revisions resolve independently, and old
  records remain unchanged after content edits, new revisions, and archival.
- Invalid money/currency/revision/JSON values fail before persistence; duplicate
  revisions, duplicate slugs/identities, and orphan FKs fail.
- Version model update/deletion and SQL product deletion with referenced
  versions fail. No product route is accidentally exposed.
- Clean disposable-database apply, rollback, and reapply pass; full existing
  tests and formatting pass. Local DB and production remain unmigrated.

## Verification result — 2026-10-18

- Focused catalog suite: 38 tests / 71 assertions passed. Full suite: 109 tests /
  416 assertions passed on PHP 8.5.11. Pint and strict Composer validation passed.
- Disposable SQLite apply, catalog-only rollback, and reapply passed without
  touching existing databases; the temporary database was removed.
- No UI, REST endpoint, actor-authorized action, provider behavior, or purchase
  snapshot is claimed. PHP 8.3 CI results and MySQL execution must be recorded
  separately when available. Resume with C1.2 in the handoff.
- Source `284dcbb` was pushed. [CI run 39048141348](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048141348)
  failed on the unmodified PHP dependency lock's security audit (11 advisories /
  5 packages), so PHP 8.3 tests and subsequent checks were skipped. Local
  verification stands; full CI qualification remains blocked pending a
  separately scoped dependency update. No audit bypass or dependency change
  was added to this slice.
- Resolved by authorized dependency maintenance at `a27f834`:
  [CI run 39048996685](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048996685)
  passed on PHP 8.3.35 with 109 tests / 416 assertions, clear Composer/npm
  audits, production build, and cache checks. The domain is now CI-qualified;
  MySQL execution and production deployment remain separate, unperformed steps.
