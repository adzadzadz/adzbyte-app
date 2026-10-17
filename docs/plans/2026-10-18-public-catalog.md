# C1.4 public catalog contract

Status: C1.4/B1 and aggregate C1 development verified at backend `9b4bf7b` and frontend `805c003`. No production data or schema
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
checks pass. Source `7424a906192b646fa11a721c889c05691d1093f4` passes
[CI 39054936218](https://github.com/adzadzadz/adzbyte-app/actions/runs/39054936218):
**206 tests / 1,299 assertions independently on SQLite and MySQL**, PHP 8.3.35,
Node 24, both dependency audits (0 findings), formatting, metadata, build and
production cache generation. The native JSON predicate correction passes the
previously failing case. All C1.4 backend gates and B1 are complete; aggregate C1 qualification follows
below. No real catalog records are seeded.

Initial source `d639596` passed the application CI job but failed MySQL at
`test_latest_versions_determine_filtering_without_resurrecting_older_offers`: a
SQL string comparison did not exclude the native JSON empty document. The read
service now counts JSON entries using SQLite json_each and MySQL JSON_LENGTH.
The existing regression covers both latest-zero-price and latest-empty-terms
products; both engines must pass before this card closes.

## Aggregate C1 consumer gate and qualification

After C1.4 CI qualification, implement an isolated Next.js source branch from
its current remote `develop`, preserving unrelated local edits and unpublished
commits. Do not push `develop` (its workflow deploys) or the machine-managed
`main`. The app-source work remains independently delivered on this repo's main.

The frontend slice adds `/shop` list/detail routes, enabled only by a server-side
`COMMERCE_API_ORIGIN` setting. No navigation/public promotion or deployment is
part of this development gate. It uses uncached server reads, validates the v1
response, preserves exact string money, renders text safely, paginates and
provides loading/empty/unavailable/404 states. Existing marketing service/pricing
pages remain independent content; no packages/prices are imported or converted.
Cart/checkout controls await C2 onward and the unresolved owner decisions.

Required proof: focused consumer/UI tests, full existing frontend suite, type
check, lint, production build, frontend source CI, and real desktop/mobile
browser list/detail interaction with the actual disposable Laravel HTTP server.
Verify committed content/price/publication changes after reload, hidden drafts
and archives, empty/error states, escaped text, overflow and console/network.
Record both exact source revisions and the synthetic environment. A draft PR
may make frontend work reviewable without triggering the deployment branch.

Qualified backend source: `9b4bf7bac89c95ccc7929dd44b01e66444a93d0c`,
[CI 39055675151](https://github.com/adzadzadz/adzbyte-app/actions/runs/39055675151),
**206 tests / 1,300 assertions on each supported database/PHP 8.3.35**. The
HTTP fixture target/cache/session now override inherited settings before boot;
a regression supplies deliberately wrong inherited DB settings and proves they
are unused. All loopback HTTP test servers and fixture files are cleaned up.

Qualified frontend source: `805c0031101725e6dee86b4454976053ae3865b8`,
[draft PR #6](https://github.com/adzadzadz/adzbyte-next/pull/6),
[CI 39056186126](https://github.com/adzadzadz/adzbyte-next/actions/runs/39056186126).
129 tests / 29 files, type checking, lint, audit (0 findings) and production build
pass. Real compiled standalone Next.js → Laravel HTTP browser flows pass at
1440px and 320px: list/detail/pagination, exact prices, selected image load,
escaped text, changed content/price, unpublish/draft/archive denial, empty state,
upstream outage/retry, and keyboard focus/Enter. No unexpected console/page
errors or horizontal overflow. Synthetic results/screenshots and the dependency
remediation rationale are in the PR's `docs/commerce/2026-10-18-public-catalog.md`
and evidence folder. Backend and frontend have no duplicated product authority.

QA-only localhost authentication uses a synthetic secret; unrelated chat is
explicitly disabled to avoid vendor CORS restrictions. No real authentication
or vendor-chat transport is claimed. The first harness run exposed an inherited
DB connection; the new pre-boot guard fixes that. Initial browser environment
errors were resolved through local QA configuration, then the full required
journey passed with no console/page errors. Duplicate hidden streaming elements
required visible/ARIA selectors in the retry test. No product workaround was
added. All private QA scripts/database and ports 8814/8815 are removed/stopped.

C1 development is qualified across these source revisions. The frontend remains
unmerged because develop promotion would deploy; release/production gates are
separate. C2 requires D04 and all later slices retain their recorded decision
gates. The owner's request to finish all phases does not specify those policies.
