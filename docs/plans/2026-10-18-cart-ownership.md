# C3.2 verified customer cart ownership and merge

Status: in progress, domain locally verified; interfaces/integration pending after C3.1 f32bc36 passes PHP 8.3 SQLite/MySQL CI, 302 / 5,331
each, real SMTP activation and desktop/mobile proof. Aggregate C3/B2 are pending.

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
