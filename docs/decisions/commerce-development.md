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
| D02 | Owner authorized configurable common defaults on 2026-10-18. Starter: PHP amounts with no additional calculated tax while merchant tax profile is unconfigured; never label this tax-exempt. Version tax/document settings and snapshot accepted calculations. Initial output is an order summary, not a statutory tax invoice | No generic-default blocker to O1 development; actual merchant tax/document qualification before real sales |
| D03 | Basic controls specified: new drafts, explicit publish/unpublish, independent availability, archive with referenced history retained. Category-based one-per-cart control approved on 2026-10-18; other per-customer/package sale constraints remain pending | No blocker to basic C1; resolve extra constraints before their enforcement/purchase flow |
| D04 | Approved: multiple distinct services, quantity one each; category toggle limits a cart to one service from that category while regular categories coexist. No time-based cart/item expiry; revalidate availability. Show current changed prices for customer confirmation. Merge by product without duplicates; restricted-category conflicts require explicit customer resolution. Use recommended 60-second, single-use handoffs; expiry affects the transfer link only, with fresh links available. Owner clarified conflicting adds: automatically replace the previous selection and notify the customer; preserve other categories | Category prerequisite before C2/C3; no longer waiting for generic cart defaults |
| D05 | Owner approves as Admin; clarification/conversation is the normal path and rejection remains supported. Configurable brief/billing, acceptance, expiry and notifications below | Resolved for O1 development with configurable defaults |
| D06 | Enabled merchant payment methods, fee handling, failed/expired attempts, cancellation, full/partial refunds, disputes, and reconciliation responsibilities | O2 |
| D07 | Manual fulfillment states/actions and customer visibility, purchased-service limits/access after refund, request/report categories, required fields, status/priority/assignment, escalation/reopening/service targets, and notification events | Fulfillment/access before O3/S2; case rules before R1 |
| D08 | Owner authorized common configurable Terms/Privacy and retention defaults for carts, briefs and orders. Cart no-expiry takes precedence. Attachment/security/operations details remain slice-specific | No generic content/retention blocker to O1 development; actual merchant identity, live policy accuracy and operational configuration still require final Q qualification |
| D09 | Merchant subscription activation/methods; plans, intervals, trials, invoices, proration, plan changes, cancellation, retry/grace, suspension, and refunds | Deferred S1; does not block the one-time slice |

D07 and D08 can be resolved incrementally: record precisely which sub-rule is
settled and keep the remaining parts pending. A case or attachment deferral
requires an explicit scope decision; it is not permission to mark those tasks
complete. The owner authorized configurable common defaults for D02/D05/D08. This permits
starter implementation without inventing merchant registration or claiming legal
compliance certification.

## Catalog content to configure later

There is no launch-service packet required before C1. Build the management
fields and controls so authorized administrators can supply these values later:

| Field | Configuration behavior |
|---|---|
| Service name / stable reference | Managed content with stable identity; no predefined packages |
| Deliverables, exclusions, customer obligations | Editable service scope and terms |
| Exact PHP price in minor units | Configurable version; may be unset in a draft; never infer free |
| Tax and receipt/invoice treatment | Configurable D02 starter settings; actual merchant tax/document qualification before sales |
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
| 2026-10-18 | D04 clarification | Owner answered: "Replace but it should notify the customer that it has been replaced." This supersedes the temporary preserve-and-remove-first implementation default. Direct category selection now atomically replaces prior category items and returns durable notice effects. Login merging still requires explicit conflict resolution. |

## Configurable starter defaults authorized 2026-10-18

Exact owner answers: D02: "Make this configurable and use common defaults for now."
D05: "For now, it's only me who approves everything so we can say "Admin"? I will most likely not do rejections (but keep it) I will however most likely do more conversation with the clients to see how I can help them better."
D08: "Set common defaults. I don't have any specific idea about this."
These supersede the prior unanswered-input gates for O1 development. Defaults
below are implementation choices under that authorization, not verified facts
about the merchant or statutory minimums for every record.

- D02: configurable versioned tax mode/rate and tax-inclusive/exclusive calculation,
  exact minor units and explicit rounding, with accepted order snapshots. Start
  with merchant tax status **unconfigured**, no additional calculated tax, and
  an **order summary** document. Do not infer VAT/non-VAT registration, legal
  identity, registration numbers or invoice authority. Real-sale enablement must
  validate those merchant settings and the chosen invoicing process.
- D05: use an explicit Admin approval capability, initially for the owner through
  existing administration authorization. Require customer name/email, a short
  project goal and requested scope; company, phone and website optional. Collect
  billing name/email/country and address when invoicing requires it; do not assume
  a country or collect tax identifiers without purpose. Field requirements are
  configurable and versioned with submitted data. Require explicit acceptance
  of current service scope/prices and versioned commerce terms at submission;
  acknowledge the privacy notice separately from marketing consent. Admin may
  request clarification, exchange customer-visible messages, approve, or reject
  with a reason. Internal notes stay private. Clarification/resubmission preserves
  history and does not enable payment. Starter approval validity is **30 days**,
  configurable; material scope/price/terms changes invalidate approval and require
  customer acceptance and reapproval. Expired approval returns to review; it never
  expires the cart. Record actor/time and notify the affected customer/Admin on
  submission, clarification, reply, approval, rejection and expiry. Payment-link
  lifetime remains provider-bound in O2.
- D08: draft versioned starter Terms/Privacy explaining manual service review,
  approved scope/price, payment before fulfillment, communication, customer
  responsibilities, data purposes/recipients, access/correction/deletion requests,
  and retention. Do not promise a refund rule before D06 is resolved, invent
  merchant contact details, or make unsupported compliance claims. Preserve the
  exact accepted versions. Active carts have **no time-based item/cart expiry**;
  deletion is an explicit customer/admin privacy workflow, subject to records
  that must be retained. Starter unsubmitted briefs: **90 days after inactivity**;
  withdrawn/rejected requests and their conversations: **12 months after closure**;
  financial orders/payment evidence: **5 years after the applicable tax filing
  deadline or actual filing, whichever is later**, with configured legal/dispute
  holds overriding disposal. Nonfinancial security/audit events: **12 months**;
  financial audit/accepted terms follow the financial record. Never automatically
  delete before the retention anchor is known. Use configurable dry-run disposal,
  retain only necessary accounting data, remove unrelated personal content, and
  audit deletion. No existing database cleanup is authorized by these defaults.

Reference checks for the starter design: [NPC Data Privacy Act, Section 11](https://privacy.gov.ph/data-privacy-act-/)
requires purpose-limited retention; [BIR RR 7-2024](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%207-%202024.pdf)
provides the five-year accounting-record basis and filing anchor. These sources
do not establish this merchant's tax status or make every record a financial record.
