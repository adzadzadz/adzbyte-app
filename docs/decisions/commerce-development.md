# Commerce Development Decision Register

Last reviewed: 2026-10-18. D01 is **resolved** by the dynamic-catalog direction;
D03's basic controls are **specified**, with additional purchase constraints
pending. Other items below remain pending for their dependent slices. This
register indexes development gates; the [product plan](../plans/2026-10-05-commerce-platform.md)
remains the authority for approved business rules. No proposed value is an
approval. The [backend plan](../plans/2026-10-17-backend-development.md) maps
these IDs to implementation cards.

## Decisions and remaining inputs

| ID | Required decision and recorded output | Blocks |
|---|---|---|
| D01 | Resolved: packages are dynamic administrator-managed records; no launch names, scope, amounts, or entitlements are specified. Start empty; use synthetic tests. Prices/terms are configurable versions | No C1 commercial-content blocker; former P0 gate superseded |
| D02 | Pending: tax included/excluded/other treatment and required invoice/receipt output, including snapshots and display. Do not infer policy from unset values | Before checkout/real sales and O1/O3 behavior that uses it; not empty/draft catalog development |
| D03 | Basic controls specified: new drafts, explicit publish/unpublish, independent availability, archive with referenced history retained. Category-based one-per-cart control approved on 2026-10-18; other per-customer/package sale constraints remain pending | No blocker to basic C1; resolve extra constraints before their enforcement/purchase flow |
| D04 | Approved: multiple distinct services, quantity one each; category toggle limits a cart to one service from that category while regular categories coexist. No time-based cart/item expiry; revalidate availability. Show current changed prices for customer confirmation. Merge by product without duplicates; restricted-category conflicts require explicit customer resolution. Use recommended 60-second, single-use handoffs; expiry affects the transfer link only, with fresh links available. Different-service selection behavior within a restricted category is being clarified | Category prerequisite before C2/C3; no longer waiting for generic cart defaults |
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

## Catalog content to configure later

There is no launch-service packet required before C1. Build the management
fields and controls so authorized administrators can supply these values later:

| Field | Configuration behavior |
|---|---|
| Service name / stable reference | Managed content with stable identity; no predefined packages |
| Deliverables, exclusions, customer obligations | Editable service scope and terms |
| Exact PHP price in minor units | Configurable version; may be unset in a draft; never infer free |
| Tax and receipt/invoice treatment | Pending D02 before purchase; no invented tax defaults |
| Purchased-service entitlement and manual fulfillment terms | Configurable versions; accepted snapshots retained |
| Publication, archival, availability | Explicit authorized lifecycle controls; drafts initially |
| Additional purchase limits | Decide before enforcement; no arbitrary commercial limits |

No production packages are seeded by development. Do not copy or convert prices
from Next.js without explicit instruction. Actual records are populated through
the administration workflow when their content becomes available.

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
| 2026-10-17 | D01, D02, D03 | Owner clarified: "Treat the service packages as dynamic. There is no specifics at this point whatsoever so make sure we can add/modify & control it." Dynamic catalog/content management replaces the fixed launch-catalog gate; tax/receipt is deferred to dependent purchase behavior. See the product plan's dynamic service catalog section |

| 2026-10-18 | D03, D04 | Owner requested a category toggle: one service from a restricted category may coexist with services from regular categories. Cart items must not expire by time; availability is rechecked. Owner said "go for recommended" and "Go" for the remaining proposed D04 defaults. Handoff clarified as a short-lived transfer link, independent of the persistent cart. Price review and duplicate-safe login merging approved; category conflict behavior must preserve customer choice |
