# Commerce Development Decision Register

Last reviewed: 2026-10-17. All decisions below are **pending**. This register
indexes development gates; the [product plan](../plans/2026-10-05-commerce-platform.md)
remains the authority for approved business rules. No proposed value is an
approval. The [backend plan](../plans/2026-10-17-backend-development.md) maps
these IDs to implementation cards.

## Pending inputs

| ID | Required decision and recorded output | Blocks |
|---|---|---|
| D01 | Launch service packages: stable name, scope/exclusions, exact integer PHP minor-unit amount, minimal entitlement/fulfillment terms, and authoritative source | P0 → C1 |
| D02 | Tax included/excluded/other treatment and required invoice/receipt output, including what must be snapshotted and displayed | P0 → C1; checkout/receipt details before O1/O3 |
| D03 | Initial publication default, publication/archive rules, sale availability, and per-customer purchase limits | P0 → C1 |
| D04 | Single or multiple distinct services per cart; expiry; price-change behavior; authenticated merge/duplicate rules; handoff lifetime | C2 and C3 |
| D05 | Required brief/billing fields, terms acceptance, approving capability, correction/rejection behavior, material-change acceptance, approval/payment-action expiry, and notifications | O1 |
| D06 | Enabled merchant payment methods, fee handling, failed/expired attempts, cancellation, full/partial refunds, disputes, and reconciliation responsibilities | O2 |
| D07 | Manual fulfillment states/actions and customer visibility, purchased-service limits/access after refund, request/report categories, required fields, status/priority/assignment, escalation/reopening/service targets, and notification events | Fulfillment/access before O3/S2; case rules before R1 |
| D08 | Terms/privacy and retention/deletion for the records introduced by each slice; private attachment types/sizes/counts/scanning/access/retention; monitoring destination; backup frequency/storage/restore/retention and incident ownership | Relevant collection/upload slice and final Q launch qualification |
| D09 | Merchant subscription activation/methods; plans, intervals, trials, invoices, proration, plan changes, cancellation, retry/grace, suspension, and refunds | Deferred S1; does not block the one-time slice |

D07 and D08 can be resolved incrementally: record precisely which sub-rule is
settled and keep the remaining parts pending. A case or attachment deferral
requires an explicit scope decision; it is not permission to mark those tasks
complete. Tax, privacy, and legal content must come from the owner's approved
input, not an implementation assumption.

## First decision packet to prepare

Use one row per proposed launch service, with unanswered cells marked pending:

| Field | Required answer |
|---|---|
| Service name / stable reference | Pending |
| Deliverables, exclusions, customer obligations | Pending |
| Exact PHP price in minor units | Pending |
| Tax and receipt/invoice treatment | Pending |
| Purchased-service entitlement and manual fulfillment terms | Pending |
| Publication, archival, availability, purchase limits | Pending |
| Owner approval evidence and date | Pending |

This is a decision template, not a seed fixture or permission to publish. Do
not copy or convert prices from Next.js without explicit owner approval.

## Recording an answer

For each resolved item, record the ID, exact answer, owner/evidence/date, affected
slice, and product-plan section. Update that section and the register together;
then update STATUS and the handoff's next action. A conversation approval must be
transcribed into durable project documentation before dependent code begins.

If an approved rule changes, record the replacement and its effective scope.
Assess existing snapshots, migrations, API compatibility, and already-paid
services before changing implementation. Preserve historical accepted terms.

## Resolution history

| Date | IDs | Result and evidence |
|---|---|---|
| 2026-10-17 | D01–D09 | Pending inputs indexed from existing product/first-release plans; no new commercial decision made |
