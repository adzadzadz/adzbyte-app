# C1.2 — Shared Catalog Actions and Policies

Status: verified locally and on PHP 8.3 CI, 2026-10-18.
Parent: [backend plan](2026-10-17-backend-development.md).

Implement a `ProductPolicy` and shared `CatalogActions` before adding panel or
REST adapters. Administrator roles alone grant no catalog capabilities; regular
administrators need explicit Spatie permissions. Customers remain denied even
if mistakenly assigned a catalog permission. The existing Shield super-admin
Gate remains the trusted override. Permission definitions are seeded without
granting them to regular roles or creating catalog content.

Capabilities: `ViewAny:Product`, `View:Product`, `Create:Product`,
`Update:Product`, `Publish:Product`, `Unpublish:Product`,
`SetAvailability:Product`, `Archive:Product`, `Restore:Product`,
`RevisePrice:Product`, and `ReviseTerms:Product`.

Named actions create incomplete drafts, update only content, publish/unpublish,
set availability, archive/restore to draft, and append price/terms versions.
Each mutation requires an actor and a caller-generated UUID operation ID.
Reuse the same ID only for the same command/payload by the same actor.
Identical retries replay the original result identity; a changed command,
product, or payload is rejected. Authorization is checked again on retries.
Replayed product results reflect current persisted state, not a historical
response; immutable revision results retain their original version.

Transactions lock the parent product before reading current state or allocating
`max(version) + 1`; existing database uniqueness remains the final revision
boundary. New-product races are resolved by the unique actor/operation receipt
inside the same transaction, rolling back a losing draft before replay.
The service retries concurrency failures at most five times, with bounded
backoff after rollback; nested transactions leave recovery to their outer owner.
SQLite has no row
locks: independent-process tests must exercise actual serialized writes and
retry behavior; MySQL runtime behavior is separate evidence.

Unpublish/archive disable availability atomically; restore returns to draft and
requires a later explicit publish. Publishing never automatically enables an
offer. A new zero price or empty terms version disables an existing offer.
Earlier versions are preserved. No order snapshots or purchase authority exist
in this slice.

`product_changes` is an internal append-only audit/operation receipt containing
actor, product, operation UUID, request hash, action, result identity, timestamp,
and before/after product plus result data. Audit insertion and mutation commit
or roll back together. Product/actor foreign keys retain history; model guards
reject audit update/delete. Raw SQL and quiet/bulk writes remain trusted-code
boundaries. No public audit serialization or route is added.

Acceptance: capability isolation and denied actors; draft/content field
allowlists; lifecycle/availability rules against freshly locked records;
immutable and uniquely allocated revisions; stable retry identities and
conflicting-key rejection; transaction rollback on invalid data/audit failure;
actor/time history; concurrent revisions and duplicate creation; isolated
migration apply/rollback/reapply; existing suite and formatting.

Verification: full suite 154 tests / 660 assertions on local PHP 8.5.11,
including 45 new cases. Five concurrency cases each start four independent
processes at a common barrier against disposable SQLite: distinct price and
terms revisions allocate 1–4, identical create/price/terms commands each commit
once. The initial no-delay transaction retries exhausted SQLite locks;
post-rollback backoff fixes that reproduced failure. Pint, strict Composer
validation, and isolated audit-migration apply/rollback/reapply pass. No existing
database was migrated, and no panel, endpoint, or production change was made.
Source `cea2199` was pushed to `main` and passed
[CI run 39050287698](https://github.com/adzadzadz/adzbyte-app/actions/runs/39050287698)
on PHP 8.3.35: 154 tests / 660 assertions, both audits (0 findings), formatting,
Composer validation, frontend build, and production-cache checks.
MySQL runtime remains unverified; C1.3 is next.
