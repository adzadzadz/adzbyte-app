# Category cart-selection prerequisite

Status: in progress; no card is complete until all applicable verification gates
pass. Owner direction 2026-10-18 adds a category toggle before C2.

## Approved behavior and implementation boundary

Categories are dynamic administrator-managed records. `limit_one_per_cart`
defaults false. Enabling it permits at most one distinct service from that
category in a cart, while services from other categories can coexist. An
uncategorized product is regular. No real category is seeded and existing
products remain uncategorized. Product quantities remain one.

Cart contents have no time-based expiry. Availability and publication are
revalidated; unavailable services cannot be purchased. Current changed prices
must be reviewed/confirmed. Login merges deduplicate products and require an
explicit choice for conflicting restricted-category selections. Different-item
replacement behavior is being clarified. Handoffs use the recommended 60-second
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

Pending; start with acceptance tests, then domain/actions and adapters. Owner
decision and this card are recorded before dependent implementation begins.
