# C1.3 — Filament Catalog Management

Status: verified complete, locally, in browser, and on both database CI jobs,
2026-10-18. Source `7d43b13`;
[CI run 39053801945](https://github.com/adzadzadz/adzbyte-app/actions/runs/39053801945).
Parent: [backend cards](2026-10-17-backend-development.md).
Completion follows the [phase gates](../verification.md). The preceding source
at `afac72d` passed [both CI jobs](https://github.com/adzadzadz/adzbyte-app/actions/runs/39051435393).

Add an administrator-only Products resource with list, create draft, view, and
content edit pages. The list supports search/publication/availability filters.
Incomplete drafts remain valid. Customers and administrators without explicit
catalog capabilities cannot access these routes or actions. View-only staff can
inspect products/history; price/terms/lifecycle permissions remain independent
from content editing. No public API, customer listing, cart, or purchase behavior
is added. Phase C1/B1 remain incomplete until C1.4 and integration are verified.

Use CatalogActions for every mutation. Lifecycle and availability have named,
confirmed actions; history has no create/edit/delete controls. Create/edit and
modal commands retain locked UUIDs across a lost-response retry and rotate them
only after successful commands. Validation failures allow correction without a
receipt; conflicting reuse asks the user to reload rather than reapplying a
different mutation. Recheck authorization on every request and service call.

Content fields: optional draft name, slug, summary, plain description, and named
image references. Image values accept HTTPS URLs or `/images/` paths, without
uploads or remote fetches. Existing structured media is preserved. Price inputs
use PHP decimal strings converted exactly to integer minor units without floats;
missing, negative, fractional-cent, scientific, and overflowing inputs fail.
Zero can be recorded but cannot enable an offer.

New terms versions provide textual scope, deliverables, exclusions, customer
obligations, fulfillment, and entitlement descriptions. No values or business
benefits are invented; existing textual sections are prefilled from the current
version. Existing unknown/structured document fields are retained;
entering text can explicitly replace a structured section, while leaving its
empty textual input alone preserves it. Earlier versions are read-only. These
are documentation fields, not executable entitlement rules or tax/receipt policy.

The view shows current publication/availability/price/terms plus read-only price,
terms, and actor/time history. Escaped text is used throughout. Shield exposes
the exact ProductCapability set and grants none automatically. No destructive
product or version actions are exposed, including for super administrators.

Acceptance: empty catalog, multiple draft creation, stable identity and content
editing, valid/invalid images and money, version preservation, full lifecycle,
availability rejection, narrow capability access, forged/denied actions,
retry/no-duplicate behavior, safe text output, history-only tables, and role
permission discovery. Verify Livewire interactions and route boundaries, full
SQLite/MySQL CI, formatting/build, and actual browser workflows/layout at
desktop and 320px. Use a disposable local test database/session for browser QA;
do not migrate the existing application database or modify production.

## Verification checkpoint

- `AdminCatalogManagementTest`: 25 tests / 315 assertions; full
  `composer test -- --compact`: 183 tests / 1,018 assertions, local PHP 8.5.11.
  Pint, strict Composer validation, Vite build, route/middleware discovery,
  and whitespace checks pass. Both source CI jobs pass on PHP 8.3.35: 183 tests /
  1,018 assertions independently on SQLite and MySQL, clear audits, build, and
  cache gates. C1.4 is next; production promotion remains separate.
- Includes guest/customer/regular staff denial, narrow view/create/price roles,
  revoked access, direct history authorization, filters, incomplete drafts,
  lifecycle, invalid inputs, identity injection, escaped text, existing versions,
  structured data, all four create/edit/price/terms replay cases, and visible
  conflicting-replay feedback. Native integers store and display the maximum
  amount without floats. No destructive product/history controls exist.
- Chrome with real HTTP/Livewire, native forms/dialogs, compiled assets,
  disposable SQLite database sessions/cache, and mail array transport passed.
  Desktop viewport 1440×1000; mobile 320×800. Browser step results are saved
  below. No document overflow or unexpected console/page errors remained.
- A stale Vite hot marker was bypassed only by the temporary QA router. Early
  harness errors (zero-height modal wrapper, array-cache notification loss,
  missing resource ViewAny fixture) were corrected; no product workaround or
  existing-database change was needed. QA server, database, fixture credentials,
  and scripts are removed. Production/release is unchanged.

## Browser evidence

All records shown are disposable synthetic fixtures, not real offerings.

- [Step results](../evidence/2026-10-18-catalog-management/browser-result.json)
- [Desktop product view](../evidence/2026-10-18-catalog-management/view-desktop.png)
- [Mobile create](../evidence/2026-10-18-catalog-management/create-mobile.png)
- [Mobile product view](../evidence/2026-10-18-catalog-management/view-mobile.png)
- [Mobile price dialog](../evidence/2026-10-18-catalog-management/price-mobile.png)
- [Role capability controls](../evidence/2026-10-18-catalog-management/role-permissions-desktop.png)
