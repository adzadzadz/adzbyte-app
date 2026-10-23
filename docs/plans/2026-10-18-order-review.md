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
surfaces are O1.3b. Final source 770606b passes PHP 8.3 SQLite/MySQL CI 39099527223 (381 / 6,166 each), including all four process races and migration recovery.
All new migration activity used disposable test databases; existing DBs and
production remain untouched. Test workers/files are removed by teardown.

Lock-order follow-up: customer reads and submission retries now take the shared
policy lock before the order, matching Admin review commands. This avoids a
policy/order lock inversion introduced by current aggregate hydration. The
follow-up source 770606b passes both CI jobs; O1.3a is complete. O1.3b may begin.

## O1.3b adapter and notification acceptance

After O1.3a qualification, connect named customer reply/resubmit/accept-proposal
commands and a private review read to the existing owned request page and v1
session API. Require real CSRF/exact Origin, bounded strict JSON/bodyless reads,
actor UUID and expected order revision. Re-render fresh acceptance after every
failure. Typed corrections may restore only for the same user and request;
never restore checked acceptance. Customer conversation payloads query only
public messages; staff history and internal notes never enter their projection.

Add a capability-backed read-only Admin order resource and named action dialogs;
no generic order/state edit or delete. Add a versioned Commerce settings form
and management API, including expected settings version to reject stale edits.
Expose only named review actions in the operational v1 contract. Reauthorize
fresh actor/capability on Livewire hydration as well as service commands.

Material-event notifications use generic account links and no brief, billing,
message content or internal note text. Customer actions notify verified Admin
reviewers with ViewAny/View permissions; Admin actions notify the verified owner;
expiry notifies both. Skip the initiating actor. Public submission, reply,
resubmission, proposal acceptance, clarification, public Admin messages,
approval, rejection and expiry are covered. Internal notes and UUID replays do
not notify. Persist one delivery per event/recipient and dispatch after commit;
recover missing dispatches through a scheduled command. Successful delivery
receipts prevent normal retry duplication; SMTP acceptance followed by process
failure before the database receipt can still duplicate email (at-least-once
transport). Exhausted delivery fails visibly for operator recovery rather than
silently claiming success. Payment state never depends on email receipt.

Verify real database queue -> loopback SMTP sink, retries/rollback/private-note
exclusion and recovery, actual HTTP/OpenAPI customer and management consumers,
Admin and customer browser workflows at desktop/320px, validation/permission
revocation/keyboard/stale revision/material acceptance/expiry, no unexpected
errors or overflow, and current full supported-engine CI. No provider credentials
are needed for O1, and no notification is sent to real recipients in QA.

## O1.3b implementation checkpoint

Local full suite **397 / 6,374** on PHP 8.5.11, real HTTP/OpenAPI/native form
consumer, actual queue/SMTP success and three-attempt refusal/exhaustion/recovery,
and fifteen Chrome checks pass. Exact role/settings permissions are manageable
and do not gain default grants. Stale settings writes are rejected. Native form
recovery is actor/request-bound; checked acceptance is never restored. Explicit
root field allowlists preserve validated nested fields and reject unknown keys.
HEAD behaves as a read. Final verification found and corrected a nested-field
retention regression and a duplicate documentation operation ID before delivery.
See [qualification](../evidence/2026-10-18-order-review/qualification.md).
Pint/metadata/build/links/operation identifiers/whitespace pass. Existing DBs and
production are unchanged; all private QA state and listeners are removed. Final source e27fefa passes PHP 8.3.35 SQLite/MySQL 8.0.46 CI 39101761212:
397 / 6,374 each, audits/build/caches passed. O1.3 and aggregate O1 are complete.
The initial native HTTP CI failure was a missing pre-test asset build; both jobs
now build assets before tests, with a manifest preflight and no waived gates.
O2 implementation is blocked on owner-deferred credentials; completion also requires actual sandbox proof.
