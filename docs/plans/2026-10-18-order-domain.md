# O1.1 order domain and accepted settings

Status: implemented, local gates passed; supported-engine source CI pending. Depends on fully qualified C3/B2 (40ae2b7 / 52148d3).
D02/D05/D08 configurable defaults are authorized in the decision register.
This card introduces domain persistence/services only. O1.2 adds versioned
customer/native adapters; O1.3 adds Admin settings/review/conversation tools.
No provider session, payable operation, production migration or seed is included.

## Contract

Orders belong permanently to the fresh verified customer resolved by the server.
The source must be that customer's canonical owned cart, with no guest proof or
retired link. Owner-scoped queries precede policy checks independently of staff
Gate overrides. A current cart review fingerprint binds exact cart/catalog and
active policy version, presented totals and accepted versions. Submission requires
that fingerprint, a UUID, explicit terms acceptance, validated configurable brief
and billing fields. Unknown caller owner/state/amount/tax/line attributes fail.

Immutable versioned commerce policy documents contain tax/document settings,
brief/billing requirements, approval lifetime, Terms/Privacy text and retention.
A singleton row serializes active-version allocation; first use records the
code-defined starter defaults with no invented merchant registration. Every order
revision snapshots that policy, customer-provided brief/billing, literal product
name/reference, current price/scope/terms and exact PHP totals. Policy, catalog,
profile and product availability changes cannot rewrite accepted revisions.
No public/sensitive data is seeded. Actual Admin settings UI follows in O1.3.

Use exact decimal strings for aggregate minor units, with integer basis-point
rates and explicit half-up rounding once on the order subtotal. No floats. Modes:
no additional calculated tax (merchant unconfigured), exclusive and inclusive.
Unconfigured never means tax-exempt; tax invoices require merchant qualification.
The output initially is an order summary. Arbitrary tax rates are not assumed.

A source cart revision can create only one order. Exact UUID/request retry returns
that owned order after reauthorization; changed input is 409. A different UUID
for the same unchanged cart returns the same order only for identical accepted
input; otherwise require the existing order's correction flow. Preserve the cart
and history; no time expiry. Lock fresh customer, active settings, owned cart,
and current catalog in a stable order; retry bounded transaction conflicts.

Keep order (open/cancelled/completed), approval (pending/clarification/approved/
rejected/expired), payment (unpaid/pending/paid/partially_refunded/refunded/disputed)
and fulfillment (not_started/in_progress/waiting_customer/completed/cancelled)
independent. A transition table rejects invalid transitions and combinations.
Clarification/rejection never enables payment. Approval expiry applies only to
unpaid work and never cart validity. Provider payment transitions remain O2;
manual fulfillment remains O3. Append-only events retain actor/time and request
identity; no generic HTTP state setter will exist.

## Gates before O1.2

- Positive snapshots/totals and no-tax/inclusive/exclusive rounding, huge values,
  malformed values, required configurable fields and exact accepted fingerprint.
- Fresh verified identity, canonical cart/owner scope, admin override, revoked
  roles/verification, no guest authority, unavailable/changed offers and conflicts.
- Immutable records, valid/invalid lifecycle transition matrix, FK/uniqueness,
  idempotent retries and changed-input rejection, atomic failure with no partial
  order or history. Independent-process repeated and conflicting submissions.
- Clean/apply/populated predecessor upgrade; populated order rollback fails
  closed rather than destroying accepted financial history; empty rollback/reapply.
  Exercise on SQLite and MySQL in current supported PHP 8.3 CI, plus full suite,
  formatting, metadata, audits, asset build and caches.
- No UI/API added in this card: browser/OpenAPI/provider gates not applicable
  until their adapters are implemented. Actual domain services are exercised by
  workers, not mocks. Record exact source/evidence before O1.2 starts.

## Local checkpoint

Local PHP 8.5.11: **350 tests / 5,874 assertions**, including **19 focused
order checks / 194 assertions**. Four-process same UUID, new UUID and conflicting
input cases create one order; new UUID aliases are durably bound so later reuse
cannot create another order. Policy/catalog/profile edits preserve accepted
snapshots. Injected line failure rolls back header/revision/history. Unknown
fields, unaccepted terms, stale review, revoked verification/role and cross-owner
queries (even staff Gate override) fail. Stateful order updates require monotonic
revisions; provider and fulfillment transitions remain disabled until O2/O3.

Clean/predecessor upgrade, empty rollback/reapply, populated policy/order
fail-closed rollback, database foreign keys/unique cart revision, preserved cart
and snapshot readback pass. Populated records require verified backup restore or
forward repair rather than destructive reversal. No existing DB was migrated.
Pint, strict Composer metadata, asset build and whitespace pass. Current PHP 8.3
SQLite/MySQL CI is required before O1.2 starts. No UI/API/provider adapter exists
in O1.1; those verification gates are not applicable to this domain card.
