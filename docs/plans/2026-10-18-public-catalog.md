# C1.4 public catalog contract

Status: implemented; final local/source delivery and CI gates pending. No production data or schema
change. Builds on qualified C1.1–C1.3; D04 and later purchasing rules are not
assumed by these read-only endpoints.

## Contract and acceptance

- Stateless anonymous `GET /api/v1/products` and `GET /api/v1/products/{public_id}`.
  Published records only, including unavailable listings; drafts, archived and
  unknown references return the same 404. No public write routes.
- Stable ULID references, nullable slug, name, summary and plain-text description.
  Media exposes only safe string references named `image`, `thumbnail`, `hero`;
  HTTPS without credentials or safe `/images/` paths. Unknown/structured media
  stays private. Consumers render all text as text, never trusted HTML.
- Current price is null when unset; otherwise version, explicit PHP currency and
  exact decimal **string** minor units (native bigint can exceed JavaScript's
  safe integer range). Zero stays distinct from null and is unavailable.
- Current terms expose version and only textual scope, deliverables, exclusions,
  customer_obligations, fulfillment and entitlements. Unknown and structured
  document fields stay private. These are descriptions, not executable rights.
- Availability requires the stored flag, positive current PHP price and nonempty
  current terms. It is advisory; later purchase actions must revalidate.
- List uses ascending internal creation order, default 20 / maximum 100 records,
  page 1–10,000, optional `available=0|1`. Strict query validation rejects unknown
  fields and arrays. Relative pagination links contain only validated values.
- A dedicated anonymous IP limiter permits 120 reads/minute, shared across both
  routes. Errors use the existing stable envelope, Retry-After on 429 and no-store.
- Every successful read queries current database state, hashes the exact JSON
  representation for an ETag and sends `public, no-cache, must-revalidate`.
  Matching If-None-Match gives 304 only after visibility/state revalidation.
  There is no server payload cache, TTL window or invalidation callback race.
  Every committed content, price, terms, publication and availability change is
  reflected on the next read; existing in-flight reads may finish concurrently.
  Consumers must revalidate or use no-store; no stale-while-revalidate override.

## Required proof

Focused API tests cover empty/list/detail, every private/lifecycle boundary,
exact money, public field allowlists, bounded pagination/filter validation,
conditional reads through all shared catalog mutations, rollback, named limiter,
429 and unsupported writes. OpenAPI schemas/examples and a standalone JavaScript
consumer exercise the real HTTP contract. Full SQLite and MySQL/PHP 8.3 CI,
formatting, metadata, audits, build and caches must pass before this card closes.
No migration, new panel or provider transport is introduced, so their new-work
checks are inapplicable; existing migration, concurrency and UI regressions stay
in the full suite. C1 aggregate completion additionally requires actual Next.js
rendering at recorded revisions, separate from a standalone consumer check.

## Evidence

Implementation is locally tested. `PublicCatalogTest` covers 22 cases / 270
assertions; `PublicCatalogConsumerTest` performs actual independent HTTP/Node
checks with disposable fixtures and cleanup. Full local `composer test -- --compact`: **206 tests / 1,299 assertions**
on SQLite/PHP 8.5.11; Node 26.11.1 HTTP consumer. Full Pint, strict Composer
validation, production Vite build, route/middleware inspection and whitespace
checks pass. Delivery and both PHP 8.3/Node 24 CI jobs remain pending. No real catalog records are seeded.

Initial source `d639596` passed the application CI job but failed MySQL at
`test_latest_versions_determine_filtering_without_resurrecting_older_offers`: a
SQL string comparison did not exclude the native JSON empty document. The read
service now counts JSON entries using SQLite json_each and MySQL JSON_LENGTH.
The existing regression covers both latest-zero-price and latest-empty-terms
products; both engines must pass before this card closes.
