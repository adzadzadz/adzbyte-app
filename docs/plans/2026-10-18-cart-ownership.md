# C3.2 verified customer cart ownership and merge

Status: verified complete at backend 40ae2b7 and Next 52148d3. Both source CI
runs and the actual B2 journey pass; see [qualification](../evidence/2026-10-18-ownership/qualification.md). Historical partial checkpoints below are superseded.

Only a fresh verified customer identity plus the trusted guest session may
claim a cart. Public IDs, service credentials, staff Gate overrides and caller
user IDs never select an owner. Account registration itself does not attach it.
Retain guest context through registration, verification, password recovery and
session rotations; only successful verified authentication permits attachment.
No new browser/customer auth token or WordPress identity authority is introduced.

Use a unique customer-to-active-cart link, retaining ownership/audit history and
all prior cart records. Lock the customer before resolving the canonical target;
lock source/target and catalog rows in stable order and retry bounded conflicts.
First claim changes guest authority to that customer. Later claims merge by
product without duplicates; preserve existing quoted versions so changed offers
still require current-price/terms review. No cart/item time expiry or payment.
Retire merged sources without deleting history or reviving their guest proof.

On login/activation, merge automatically only when no restricted category
conflict exists. Otherwise show both selections and require the customer to
choose which service to keep from each conflicting group. Ordinary category
items survive. Read preview under current locks, return a state fingerprint,
and require exact current fingerprint/choices on submission. Concurrent cart,
offer/category/availability changes require a fresh review. Never silently choose
on behalf of the customer during login merge. Unpublished content remains hidden
through safe Resources, including conflict previews.

Durable claim receipts bind source guest authority, verified actor, UUID,
fingerprint and choices. Retries may recover only the same actor's current
owned target after reauthorization; changed input or another identity fails.
Old guest/service access and outstanding handoffs stop after ownership changes.
Owned app-session context is checked against fresh identity/role/verification
and the active-cart link on every operation. Account switching cannot reuse
another customer's context. Logout invalidates session access, while account
cart data remains recoverable by the same customer on another browser.

The Next guest cookie cannot read an owned cart. Provide only a proof-scoped
retirement acknowledgement (no owner, target reference, contents or future
activity), bound to the authority that performed the claim. Once confirmed,
Next may rotate to a new guest context and tell the customer that prior
selections are saved in the account. Further additions transfer/merge through
the existing app journey; never preserve anonymous access to owned records.

Expose versioned app-session preview/claim operations with strict JSON,
CSRF/Origin/UUID/state checks; native forms call the same services. Customer
login/activation resumes `/cart` when relevant; admin authentication behavior
stays scoped to its panel. Direct registration without a cart remains valid.

Acceptance: role/verification/owner/session/return restrictions and revoked
guest access; duplicate-safe merge/explicit conflict choice; changed-state
rollback and durable retries; simultaneous claims and cross-actor races in
independent processes on SQLite/MySQL; populated migration recovery and
ambiguous-existing-data fail-closed guards; current full PHP 8.3 CI and Next
tests/build/audit/CI. Actual built Next -> app -> registration/email activation
or login/password recovery -> owned cart, additional guest selection/merge,
logout/relogin/new-browser recovery, duplicate/replay/error paths, keyboard and
desktop/320px privacy/overflow/console checks. B2 completes only after this full
journey passes. Business decisions D02/D05/D08 still gate O1 afterward.

## Domain checkpoint (partial)

Implemented active customer-cart links, audited authority transfer/merge, exact
state fingerprints, explicit conflict choices, quote-preserving deduplication,
proof-scoped retirement acknowledgement and revoked guest/old-handoff access.
Migration guards ambiguous pre-existing owned carts before schema changes and
reconstructs retired links from retained ledger history on reversal/reapply.
The prior handoff migration test now stops at its historical 040000 boundary
so future migrations do not change what its rollback is testing.

Local PHP 8.5.11: full **316 tests / 5,483 assertions**, Pint and whitespace pass.
Seven ownership cases, populated upgrade/reversal/reapply/ambiguity guards and
five four-process scenarios (retry, competing actors, regular union, duplicate
product, explicit restricted conflict) pass. New schema exists only in disposable
test databases. Supported PHP 8.3 SQLite/MySQL source CI is pending.

No ownership/session/login adapters, safe merge Resource/REST/UI, Next retirement
continuation or integrated B2 journey have been implemented yet. C3.2 remains
partial. Continue within this card; do not begin O1 or claim C3/B2 complete.

## Adapter checkpoint (partial, before integrated B2 proof)

Domain `0719fb4` passed PHP 8.3.35 CI 39094211348: 316 / 5,483 on each engine.
Current source adds verified owned-session access, login/activation/verification
attachment, explicit native/API merge controls, account Cart navigation, owned
recovery across logout/password reset/stale guest sessions, and static proof-
scoped retirement REST. Owner/guest/receipt queries are scoped independently of
staff Gate overrides. Source receipts bind source reference; target history
never authorizes retirement or source replay. A negative regression prevents
incoming proofs from probing target-cart future merge activity.

Local complete backend suite: **330 / 5,679** before the additional independent
Node schema unit; that unit and actual catalog/cart HTTP consumers also pass.
Pint/whitespace and asset build pass. The test-only Livewire harness explicitly
attaches its real test session because Livewire unit requests disable middleware;
real HTTP/browser CSRF remains enabled and required.

Next working source passes **144 tests/31 files**, type/lint/build. A confirmed
retirement rotates to a fresh guest proof/UUID, retains only a coarse encrypted
account-history hint, labels new selections and links to the app account cart.
Missing/unconfirmed authority never resets silently; pending create recovery is
also covered. No owned cart read/write is delegated to Next or WordPress identity.

Current adapter source CI, current Next source CI and the complete real built
registration/login/reset/merge/new-browser B2 journey remain pending. C3.2 and
aggregate C3/B2 are incomplete; O1 remains gated by owner decisions.

## Final qualification

# C3.2 / C3 identity and B2 qualification

Verified 2026-10-18. Backend source `40ae2b78493473f15dce6fd9ab80722219bedb9e`:
[CI 39095546942](https://github.com/adzadzadz/adzbyte-app/actions/runs/39095546942),
**331 tests / 5,680 assertions independently on SQLite and MySQL 8.0.46, PHP 8.3.35**.
Both jobs pass; metadata, formatting, dependency audits (0 findings), production
assets and Laravel caches pass. Local PHP 8.5.11 suite and actual Node contract
consumers also pass. Independent four-process ownership races and populated
migration reversal/reapply/ambiguity guards run on both engines in CI.

Frontend source `52148d3eff3e92ea27696dd2aaebec711a8c287a`:
[CI 39096289880](https://github.com/adzadzadz/adzbyte-next/actions/runs/39096289880),
Node **22.23.3**, **144 tests / 31 files**, type/lint/build and audit (0 findings).
Local Node 26.11.1 full suite/type/lint/build also pass. Source is isolated in
`codex/public-catalog`, draft PR #6; no develop push/merge or deployment.


All required checks and cleanup are recorded in the linked qualification. The
owner supplied configurable D02/D05/D08 defaults; O1.1 is next.
