# Category cart-selection prerequisite

Status: verified complete for category persistence/administration/projection.
Actual cart enforcement remains C2. Owner direction 2026-10-18 adds this
category toggle prerequisite before C2.

## Approved behavior and implementation boundary

Categories are dynamic administrator-managed records. `limit_one_per_cart`
defaults false. Enabling it permits at most one distinct service from that
category in a cart, while services from other categories can coexist. An
uncategorized product is regular. No real category is seeded and existing
products remain uncategorized. Product quantities remain one.

Cart contents have no time-based expiry. Availability and publication are
revalidated; unavailable services cannot be purchased. Current changed prices
must be reviewed/confirmed. Login merges deduplicate products and require an
explicit choice for conflicting restricted-category selections. Conflicting
adds preserve the current item and prompt removal before another selection;
no automatic replacement. Handoffs use the recommended 60-second
single-use transfer token; a fresh link can be requested without expiring a cart.
Cart retention/deletion and security credential validity remain separate.

This prerequisite adds categories, authorized audited retry-safe create/update,
product assignment, administration, and a compatible public projection. Actual
cart enforcement belongs to C2; category persistence alone does not complete it.

## Schema and API compatibility

- `product_categories`: stable unique ULID, required name, boolean
  `limit_one_per_cart`, timestamps. No destructive category UI/model operations.
- Nullable `products.category_id`, restrictive FK. Existing content, prices,
  terms and audit history survive upgrade and rollback/reapply.
- Append-only `product_category_changes`: category, actor, operation UUID,
  command hash, before/after state and timestamp. Normal service commands use
  separate ViewAny/View/Create/Update category capabilities with no default
  grants. Product editors may assign a category; only category editors can
  change its rule. No new customer write endpoint.
- Public product payloads stay identical by default. `include=category` opts
  into nullable `{public_id,name,limit_one_per_cart}` only. Optional `category`
  ULID filters the published list. Unknown/internal fields remain excluded;
  include/filter validation and ETags cover category changes. No extra endpoints
  or implicit serialization of the internal category FK.
- Next consumer opts in explicitly, validates the optional projection and
  displays category/rule labels. Old consumer requests continue to work.

## Verification required before C2

Domain/policy/service invalid input, denied actors, retry/conflict/revocation,
immutable audit, independent-process identical creates, upgrade/rollback/reapply
on SQLite and MySQL. Livewire administration/assignment and forbidden paths;
real desktop/mobile category create/toggle/assignment. API opt-in/backward
compatibility/filter/ETag/validation and OpenAPI/Node consumer checks. Full
PHP 8.3 CI on both supported engines, formatting, metadata, audits, build/cache
gates and real frontend/browser consumer proof at recorded source revisions.
No existing database migration, production action or provider is introduced.

## Evidence

Backend source `4859e4e7d6b5ef9367001476a01ef7899b0cfc2b` passes
[CI 39087894642](https://github.com/adzadzadz/adzbyte-app/actions/runs/39087894642):
**221 tests / 1,447 assertions independently on SQLite and MySQL 8.0/PHP 8.3.35**,
both audits, formatting, Composer validation, production asset build and caches.
This includes the category upgrade/reversal and four-process same-command race;
all prior catalog migration/race/API/auth tests still pass. Local PHP 8.5.11:
full 221 / 1,447, Pint, Composer validation and whitespace check passed.

Real Chrome admin workflow passed: invalid blank name feedback, default-off
keyboard toggle, create and audited history, product assignment, reload
persistence, toggle off/on reflected in actual API, default API unchanged,
view-only edit denial and no document overflow at 1440/320px. Saved artifacts:
`docs/evidence/2026-10-18-category-controls`. A post-redirect component was not a
valid conflict fixture; a fresh lost-response fixture proves visible UUID
conflict handling and one committed category per command.

Next source `f2025341cf43bc0f86ccb8c5d0d3e9134d92be3d` in draft PR #6 passes
131 tests / 29 files, type/lint/build and zero-finding npm audit locally.
Actual built Next16.4.2/Node26.11.1 to Laravel HTTP/SQLite browser checks pass
at 1440/320px, including uncached category toggle changes and no page/console
errors. Test runtime uses synthetic Auth.js settings and disables optional
external chat; no browser mock replaces the commerce API. Frontend contract
and artifacts: `docs/commerce/2026-10-18-category-controls.md` and
`docs/commerce/evidence/2026-10-18-category` in that branch. Source CI run
[39088192681](https://github.com/adzadzadz/adzbyte-next/actions/runs/39088192681)
passes at that exact source revision: tests/type/lint/audit/production build.
All applicable category prerequisite development gates pass.

All synthetic databases and browser harness files were removed and ports
8814/8815 stopped. No existing database migrated, default permissions granted,
production setting changed, provider operation or deployment performed.
