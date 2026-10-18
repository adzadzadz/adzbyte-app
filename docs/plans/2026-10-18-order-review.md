# O1.3 review and clarification contract

O1.2 source 538e3ca is verified on both supported engines. O1.3 is split into
O1.3a shared domain/persistence and O1.3b panel/customer REST/notifications.
Each increment must qualify before its dependent adapter work. Aggregate O1
remains incomplete until both increments and the integrated journey pass.

Admin must be verified, retain the administrator/super_admin role, and have
the exact capability on each fresh command. View, approve, reject, clarify,
message, internal note and propose are separate capabilities. No default role
grant. Customer commands lock and query the verified owner's order regardless
of Gate overrides. Every command has an actor-scoped UUID receipt, input hash,
expected monotonic order revision, and atomic actor/time event.

Only open/unpaid requests are reviewable. Approve requires pending review and
no pending material proposal; validity uses the accepted policy's configurable
approval_days (30 by default). Rejection requires a customer-visible reason.
Clarification is the normal conversation path and immediately removes approval.
Public messages and internal notes are immutable separate visibility values;
customer projections must never load internal notes. Replies do not silently
resubmit or approve. Explicit customer resubmission from clarification/expired
requires corrected brief/billing and fresh acceptance of the accepted commercial
snapshot; it creates an immutable revision and returns to pending approval.

Admin can propose the current published/available catalog offer for the same
selected services, with the current configurable policy and a visible reason.
This first fixed-scope slice does not invent custom prices or executable scope
rules. Proposal creation invalidates approval; latest proposal replaces only
the pending proposal, retaining all prior history. The customer accepts its
exact opaque proposal reference and expected order revision with explicit terms
acceptance. Price/terms/policy/availability changes make that proposal stale and
require a new Admin proposal. Acceptance creates new immutable lines and totals,
clears the pending proposal, and requires Admin approval again. An ordinary
resubmission cannot bypass a pending material proposal.

Unpaid approval expires at its captured instant; only an explicit expiry action
or scheduled expiry command changes status, using an order lock and deterministic
UUID receipt. A payment eligibility check must independently compare the expiry
instant, so scheduler delay cannot enable payment. Carts never expire. No
payment or paid-fulfillment transitions are enabled by this increment.

O1.3a verification: positive and denied capabilities, stale actor/permission,
cross-owner/Gate override, strict payload/UUID/revision, duplicate and conflicting
receipt, atomic failure, public/private message separation, immutable history,
proposal stale acceptance, accepted-policy expiry, and independent-process
review/acceptance/expiry races. Apply against populated O1.2, empty reversal and
reapply, fail-closed populated reversal on SQLite/MySQL. Full PHP 8.3 CI,
audits/build/cache gates. UI/API/email are explicitly O1.3b and cannot yet be
claimed verified.

## O1.3a local checkpoint

Local PHP 8.5.11: full suite **381 tests / 6,167 assertions** passes, including
four real independent-process races (same command retry, distinct Admin approval,
expiry, customer acceptance versus replacement proposal). Populated O1.2 upgrade,
empty review reversal/reapply and populated fail-closed recovery preserve accepted
order snapshots. Pint, strict Composer metadata, build, schedule discovery and
whitespace checks pass. The full suite requires loopback permission for existing
HTTP/SMTP tests; a restricted run failed only on those bindings and missing test
imports, both resolved before the successful full run.

The new capabilities are defined without grants. Settings commands now check the
underlying permission directly so a global Gate override cannot bypass it. Shared
aggregate hydration uses locking reads for accepted revisions/lines/policy to
avoid mixing current order state with an older MySQL repeatable-read snapshot.
No new routes, screens, email/provider transport or dependencies in O1.3a: those
surfaces are O1.3b. Required current-source PHP 8.3 SQLite/MySQL CI is pending.
All new migration activity used disposable test databases; existing DBs and
production remain untouched. Test workers/files are removed by teardown.
