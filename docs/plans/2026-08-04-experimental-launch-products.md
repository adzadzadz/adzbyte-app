# Experimental Launch Products — Product and System Plan

| Planning field | Decision |
|---|---|
| Date | 2026-08-04 |
| Status | Implementation in progress — foundation phase F complete |
| Market | Philippines |
| Acquisition | Organic posts in social-media groups and pages |
| Visibility | Off-menu promotional campaign |
| Public product UI | `adzbyte-next` only at `https://adzbyte.com` |
| Management UI | Filament 5 `/admin` and `/account` panels in `adzbyte-app` at `https://app.adzbyte.com` |
| REST API | Versioned Laravel API built alongside management features for later `adzbyte-next` use |
| Authorization | Spatie Laravel Permission with Filament Shield and Laravel policies |
| Payment provider | PayMongo Hosted Checkout |
| Initial fulfillment | Manual, with later Hostinger automation |

## Overview

Adzbyte will offer extremely low-cost, one-time web experiments for Filipino founders who have an idea but are not ready to commit to a conventional website project. The expected customer may publish the site once, share it briefly, and then abandon it.

This is not intended to be a low-priced custom-development service. It is a template-driven publishing service that makes use of existing hosting capacity, creates a source of referral traffic for Adzbyte, and gives a small number of experimental founders a path into future custom work.

## Canonical Terminology

Use these terms consistently in interfaces, code, and documentation:

| Term | Meaning |
|---|---|
| Public product UI | Anonymous campaign and product pages rendered only by `adzbyte-next` |
| Management UI | Authenticated Filament pages rendered only by `adzbyte-app` |
| Customer panel | Filament `/account` in `adzbyte-app` |
| Admin panel | Filament `/admin` in `adzbyte-app` |
| Checkout initiated | An internal order and PayMongo Checkout Session exist; payment is not yet confirmed |
| Payment confirmed | A valid PayMongo webhook has marked the payment `paid` |
| Brief complete | Required answers and assets pass completeness validation and the customer has formally submitted them |
| Ready for review | Payment is confirmed and the brief is complete; the 6–12-hour first-draft clock starts |
| Draft ready | A preview version is available for the customer to inspect; the order is not yet delivered |
| Live / delivered | The promised outcome has passed quality check and its final URL or response is available |
| REST API | A versioned backend contract in `adzbyte-app`; it does not imply that Next.js owns phase 1 management |

Avoid the unqualified word “completed.” Use **payment confirmed**, **brief complete**, **draft ready**, or **live / delivered** so the current stage is explicit.

## System Boundary

- `adzbyte-next` owns only the public campaign and product experience. It hosts `/go-live`, product listing and detail pages, initial purchase calls to action, and customer-facing payment return pages.
- `adzbyte-app` owns all management for both customers and administrators, the versioned REST API, and trusted backend processing. It is the system of record for users, buyers, products, orders, briefs, messages, attachments, drafts, PayMongo checkout sessions and webhooks, fulfillment workflows, sites, notifications, and audit events.
- PayMongo remains the payment system of record for transaction processing; Laravel stores the references and reconciled payment state needed to operate each order.
- Hostinger, WordPress, and static hosting are fulfillment targets. Their identifiers and lifecycle state are linked back to the Laravel order and site records.

`adzbyte-app` must not serve an anonymous storefront, product catalog, campaign landing page, or other public application UI. Every human-facing management page requires authentication. The only routes reachable without an existing user session should be the minimum authentication-bootstrap routes needed to sign in, activate or recover an account, and explicitly configured machine endpoints such as the PayMongo webhook. Machine endpoints must authenticate independently through a signed webhook, a server-to-server credential, or an equivalent narrow mechanism.

The browser must never receive `adzbyte-app` service credentials, PayMongo secret keys, or webhook secrets. Anonymous product and checkout-initiation requests pass through the `adzbyte-next` server to a narrowly authenticated server-to-server interface. The browser may be redirected to a PayMongo-hosted checkout URL created by the app. After payment, customer management continues in the authenticated `adzbyte-app` customer panel. The REST API is maintained in parallel for later Next.js use, but Next.js does not own the management experience in phase 1.

This separation protects the public website from application complexity while giving Adzbyte one operational source of truth across every promotional product:

```text
Public browser
  → adzbyte-next UI
  → authenticated Next.js server-to-server request
  → adzbyte-app creates or updates the user, order, and checkout session
  → browser redirects to PayMongo Hosted Checkout
  → signed PayMongo webhook reaches adzbyte-app
  → customer manages the paid order in the authenticated adzbyte-app customer panel
  → administrator manages operations in the authenticated adzbyte-app admin panel
```

## Application UI, Authentication, and Authorization

Use **Filament 5** for both authenticated management interfaces inside `adzbyte-app`:

- `/admin` — internal operations for orders, payments, reviews, fulfillment, sites, customers, roles, and audit history.
- `/account` — customer management for purchases, briefs, messages, attachments, draft review, corrections, product controls, and sites.

Neither panel is public. Both require authentication and must keep their resources, pages, widgets, navigation, and authorization isolated. `adzbyte-app` must not render public product listings or campaign pages; those remain in `adzbyte-next`.

### Shared Brand and Media Source

The authenticated Filament panels should feel like the management side of the same Adzbyte product. Use `adzbyte-next/src/app/globals.css` as the current source of truth for Poppins typography and the shared dark, purple, cyan, teal, gold, and text colors. Adapt these source values into named management tokens so brand accents remain separate from operational success, warning, danger, information, and pending states.

The initial management experience is dark-first. Use the full Adzbyte wordmark on authentication and expanded navigation surfaces, the square mark for compact application identity, and contextual service imagery only when it helps a customer understand a purchased product or next step. Keep the administrator interface operational and visually restrained. Personal photos, certificates, testimonials, campaign artwork, and review screenshots are excluded unless a specific screen passes relevance, privacy, permission, and accessibility review.

Assets selected from `adzbyte-next/public/images` must be copied, optimized, versioned, and documented in `adzbyte-app`; do not hotlink them or create a runtime dependency on the Next.js repository. The complete [Management UI Branding Plan](2026-08-04-management-ui-branding.md) defines token usage, typography, asset governance, panel-specific direction, accessibility requirements, implementation order, and the Phase M0 acceptance gate.

Use one `users` table, one `App\Models\User` model, and Laravel's normal `web` guard for both Filament panels and future API authentication. Do not create separate `Admin` and `Customer` models or duplicate authentication stores. This preserves one identity and order history if a staff member is also a customer and keeps account activation, password reset, and email verification centralized.

The production origins are `https://adzbyte.com` for `adzbyte-next` and `https://app.adzbyte.com` for `adzbyte-app`. The initial super-administrator identity uses `adzbite@gmail.com`; its password or activation secret must be provisioned deliberately and must never be committed.

Use Filament's built-in panel authentication for phase 1 login, password reset, email verification, profile management, and optional multi-factor authentication. Do not expose open self-registration in `adzbyte-app`: checkout creates or identifies the customer account, and the app sends a signed activation or set-password path. Use **Laravel Sanctum** to protect the versioned REST API and trusted machine integrations. Do not add a second headless authentication stack until a future Next.js management client actually requires it.

For a new buyer email, trusted checkout application logic creates an unverified `customer` account with an unusable generated password before checkout. A verified paid webhook will later invoke the shared activation action, which sends a single-use signed link valid for 24 hours. The customer sets a password through that link; successful activation verifies the email, emits authentication events, signs the customer in, and opens `/account`.

If an anonymous checkout supplies an email that already belongs to any user, the app must not attach a new order or disclose publicly that the account exists. Checkout pauses with generic instructions to sign in or reset the password, and the authenticated customer then retries checkout. Open registration remains disabled, expired or altered activation links fail, and an activated link cannot be reused.

### RBAC Foundation

Use the following established packages instead of building role and permission storage from scratch:

- **Spatie Laravel Permission** is the authorization source of truth. It stores roles and permissions, integrates them with Laravel's Gate, and is usable from Filament, controllers, jobs, policies, and Blade/Livewire code.
- **Filament Shield** integrates Spatie permissions with Filament 5. Use it to generate resource policies and permissions and to provide role-management screens in the admin panel.

Shield is an adapter and management layer, not the authorization boundary by itself. Laravel policies and permission checks remain authoritative outside Filament and for custom actions.

Start with three application roles:

| Role | Panel access | Initial purpose |
|---|---|---|
| `customer` | `/account` only | Manage only the signed-in customer's orders, briefs, messages, drafts, product controls, and sites |
| `administrator` | `/admin` only | Operate orders, content review, fulfillment, customers, payments, and sites according to assigned permissions |
| `super_admin` | `/admin` only | Full system access, including role and permission administration |

Roles should group permissions; application code should normally authorize capabilities through Laravel policies or `$user->can(...)`, not through scattered role-name checks. Reserve role checks for coarse panel entry and the super-admin override.

### Interface and Record Isolation

Interface entry and record access are separate security checks:

1. `User::canAccessPanel()` checks the panel ID: customers enter only `/account`; administrators and super administrators enter only `/admin` unless they also hold the customer role.
2. Every current management route requires Filament authentication. Every future customer API route uses `auth:sanctum`, email-verification middleware where appropriate, request validation, rate limiting, and a Laravel policy.
3. Customer policy queries always scope records by ownership, such as `orders.user_id = authenticated_user_id`. The `customer` role alone must never grant access to every customer's orders, briefs, messages, drafts, payments, or sites.
4. Filament uses the same policies for its resources and actions, with additional administrator permissions where required.
5. Custom actions such as submitting a brief, approving a draft, requesting a correction, changing fulfillment state, reconciling payment, refunding, publishing, or archiving require explicit authorization in addition to resource visibility.

Create permissions around business capabilities rather than screens. The initial groups should cover:

- Orders: view, assign, request information, approve or reject content, and perform allowed status transitions.
- Payments: view, reconcile, and initiate an approved refund workflow.
- Sites: view, provision, publish, archive, and reactivate.
- Customers and briefs: view and update within operational scope.
- Administration: manage users, roles, permissions, and system settings.
- Customer self-service: view owned orders/sites, update an eligible owned brief, and request an included correction.

New customer registrations receive only the `customer` role through trusted server-side application logic. Never accept a role or permission value from a registration or checkout request. Seed the first `super_admin` deliberately, and allow only a super administrator to promote staff or change role assignments.

Both Filament panels and the REST API must call the same Laravel application services and policies so behavior cannot diverge. Filament is the phase 1 management UI; the API remains a stable parallel contract that can support selected Next.js features later without moving current management out of `adzbyte-app`.

## Future-Ready REST API Foundation

Build the Laravel REST API alongside the Filament management features so `adzbyte-next` can consume selected capabilities later without a backend redesign. The API is not the phase 1 customer-management UI; `/account` in `adzbyte-app` remains authoritative for customer interaction and controls.

### Access Modes

| Caller | Surface | Authentication |
|---|---|---|
| Customer or administrator | Filament `/account` or `/admin` | Laravel `web` session plus panel access and model policies |
| `adzbyte-next` server | Narrow `/api/v1/integration/*` routes | Dedicated, revocable Sanctum service token with explicit abilities |
| Future first-party Next.js management client | Protected `/api/v1/*` customer routes | Sanctum stateful session cookies when deployed under the same top-level domain |
| PayMongo | Dedicated webhook route | Verified `Paymongo-Signature` against the raw body; no user session |

Never accept a customer ID from the Next.js server as proof of customer identity. Current anonymous checkout integration runs as a restricted service principal; future customer-management requests must authenticate the actual customer.

### API Conventions

- Place application endpoints under `/api/v1`; breaking response changes require a new version.
- Return data through Laravel API Resources so database models and hidden fields are never serialized directly.
- Use Form Request classes for validation and Laravel policies for every model lookup and mutation.
- Return consistent validation, authorization, conflict, and rate-limit errors that Next.js can render predictably.
- Use cursor or page-based pagination for lists, ISO 8601 UTC timestamps, opaque public identifiers, and explicit status enums.
- Accept an idempotency key for checkout creation, brief submission, message creation, draft approval, correction requests, and other retry-prone mutations.
- Rate-limit authentication, messaging, uploads, checkout creation, and state-changing actions separately.
- Generate and maintain an OpenAPI contract so a later `adzbyte-next` integration can use generated types or a typed API client.
- Keep customer endpoints, trusted Next.js integration endpoints, Filament/web routes, and third-party webhooks in separate route groups with separate middleware.

Do not expose generic unrestricted CRUD for payments, order status, fulfillment, or site lifecycle. These are workflows, so the API should expose named business actions that validate the current state and record an audit event.

### Initial Endpoint Groups

The exact payloads will be defined during implementation, but the API contract should be prepared to cover:

| Area | Representative endpoints | Purpose |
|---|---|---|
| Identity | `/api/v1/me`, `/api/v1/me/profile`, `/api/v1/me/notifications` | Current customer, profile, unread counts, and notification preferences |
| Orders | `/api/v1/orders`, `/api/v1/orders/{order}`, `/api/v1/orders/{order}/timeline` | List owned purchases and show payment, requirements, fulfillment, and delivery state |
| Requirements | `/api/v1/orders/{order}/brief`, `/api/v1/orders/{order}/brief/submit` | Autosave and formally submit the structured project brief |
| Conversation | `/api/v1/orders/{order}/messages`, `/api/v1/orders/{order}/messages/{message}` | Asynchronous customer–Adzbyte communication tied to an order |
| Attachments | `/api/v1/orders/{order}/attachments`, `/api/v1/attachments/{attachment}` | Upload, inspect, download, and remove authorized files |
| Drafts | `/api/v1/orders/{order}/drafts`, `/api/v1/drafts/{draft}`, `/api/v1/drafts/{draft}/feedback`, `/api/v1/drafts/{draft}/approve` | Deliver preview versions and capture approval or actionable feedback |
| Corrections | `/api/v1/orders/{order}/correction-requests` | Use the included correction without turning chat into an unlimited revision channel |
| Purchased product controls | `/api/v1/orders/{order}/controls`, `/api/v1/orders/{order}/content-items` | Expose only the content and lifecycle controls granted by the purchased product |
| Sites | `/api/v1/orders/{order}/site`, `/api/v1/sites/{site}/reactivation-requests` | Show the live URL, hosting dates, access instructions, and permitted lifecycle controls |
| Integration | service-authenticated catalog and checkout-session endpoints | Let the Next.js server read product data and initiate checkout without exposing service credentials |
| Webhooks | dedicated PayMongo webhook route | Receive signed provider events outside customer session authentication |

All `{order}`, `{draft}`, `{attachment}`, and `{site}` bindings must be authorized against the authenticated customer. A valid identifier must never be sufficient by itself to retrieve another customer's record.

Use dedicated records rather than placing the whole collaboration history in the order row:

- `order_briefs` — questionnaire schema version, autosaved answers, completeness state, submission timestamps, and revision number.
- `order_messages` — order, sender, customer-visible message type, body, and sent/read timestamps.
- `attachments` — uploader, owning order, attached brief/message/feedback record, storage key, original name, media type, size, and security status.
- `order_drafts` — immutable version number, preview URL, delivery timestamp, status, and creator.
- `draft_feedback` — draft version, customer feedback, disposition, and resolution timestamps.
- `order_entitlements` — purchased capabilities and limits, such as post count, product count, included corrections, editable fields, hosting term, and reactivation eligibility.
- `content_items` — customer-managed blog posts, store items, or other product-specific content constrained by the order's entitlements.
- `order_events` — append-only timeline of status, SLA, payment, communication, and fulfillment events.
- `internal_notes` — staff-only operational notes stored separately from anything returned by the customer API.

Uploads require an allowlist of file types, size and count limits, private storage, authorized temporary download URLs, and a quarantine/scanning step before administrators open them.

## Customer Management and Fulfillment Workflow

The following workflow is implemented first in the authenticated Filament panels. Its application services and policies also back the REST API so a later client can reproduce the same behavior without changing the business rules.

### Structured Post-Payment Brief

Checkout should collect only the minimum information required to identify the buyer, create the order, and take payment. After PayMongo confirms payment, the authenticated Filament customer panel in `adzbyte-app` unlocks a guided brief for that order. This is where the customer can explain the requested draft in detail while Adzbyte is unavailable.

The brief should be schema-driven by product and autosave as a draft. Each order stores a snapshot of the questionnaire version so later product-form changes do not alter an existing customer's requirements. Depending on the product, collect:

- Business, project, or idea summary
- Goal of the page, blog, store, or consultation
- Intended audience and the problem or need being addressed
- Offer, products, services, pricing, and important differentiators
- Required page sections and customer-supplied copy
- Primary call to action and contact/order destination
- Preferred template, colors, logo, images, and other brand assets
- Reference sites or examples, including what the customer likes or dislikes
- Product-specific details such as posts, catalog items, prices, ordering instructions, or the consultation question
- Anything that must be included or avoided
- Confirmation that the supplied content is accurate, permitted, and within the purchased scope

Required fields and an on-screen completion checklist should help the customer reach a genuinely usable submission without waiting for a staff reply. A free-form message thread supplements the brief but does not replace required structured answers.

### Asynchronous Order Conversation

Each paid order has one customer-visible conversation shared by the customer and authorized administrators. Support these message types:

- Customer message
- Administrator reply
- Automated system update, such as payment confirmed, brief received, more information requested, draft ready, or site published

Messages may include authorized attachments and read timestamps. Email notifications should link back to the relevant authenticated `/account` order screen in `adzbyte-app`. Internal staff notes must be stored separately and must never appear in the customer panel or conversation API.

This is asynchronous messaging, not a live-chat promise. When no administrator is available, the system immediately acknowledges the message, preserves it on the order timeline, shows the current order state, and tells the customer whether any required information is still missing.

### Draft Review

Store each delivered draft as an immutable version with its preview URL, delivery timestamp, and status. The customer can:

- Open the latest preview from the Filament customer order screen.
- Submit consolidated, specific feedback against that draft.
- Approve the draft for publication.
- Use the included correction when the product permits it.

Draft feedback and correction requests must be distinct actions with clear scope. Ordinary conversation messages must not silently create unlimited revision obligations.

The purchased product's entitlements determine what the customer may request. Draft review confirms that supplied content was understood and placed correctly; it does not create a general design-revision entitlement. Requests outside the purchased scope should become a separate quote instead of changing the existing order silently.

### 6–12-Hour First-Draft Clock

**Payment confirmed** and **live / delivered** are different events. Payment confirmation unlocks the requirements workflow; the order is not delivered until the promised outcome is available.

The first-draft clock starts only when both conditions are true:

1. PayMongo payment is confirmed by a valid webhook.
2. The required brief fields and assets pass automated completeness validation and the customer formally submits the brief.

At that moment, set `ready_for_review_at`, `draft_target_from_at` (`+6 hours`), and `draft_due_at` (`+12 hours`). Show these timestamps and a live status indicator in both the Filament customer and admin panels.

If an administrator formally requests missing information, set `requirements_status=needs_information`, record the reason, and pause the clock without losing the current fulfillment history. Resume it only when the customer submits the requested information, extending the target by the recorded paused duration. Ordinary messages do not pause, restart, or extend the clock. Every start, pause, resume, and deadline change must be recorded in the order event log.

The clock ends when the first reviewable draft or consultation outcome is delivered. Final publication follows customer approval and is not part of the 6–12-hour guarantee because the approval delay is controlled by the customer. After approval, publish as soon as operationally practical and show that stage separately.

Before launch, explicitly decide whether the 6–12-hour window means elapsed clock hours or published business hours. Store timestamps in UTC and display them to the initial Philippine market in Asia/Manila time. Do not leave weekend, holiday, or after-hours behavior implicit.

## Core Positioning

**Primary message:**

> Testing lang? Put your idea online for as little as ₱99.

**Supporting message:**

> Get a real working page, free hosting, and a free subdomain. No subscription, no long-term commitment, and no pressure to build a full website.

**Alternative social-first headline:**

> May idea ka? Ilagay muna natin online for ₱99.

The offer should be described as a small experiment, not as a discounted professional website. The customer pays a one-time **launch fee** rather than a development fee.

## Campaign Isolation and Site Visibility

These products are a promotional experiment and must not appear as part of Adzbyte’s normal agency offering.

- Do not add the campaign, product listing, or individual products to the desktop or mobile navigation menus.
- Do not add them to the standard Services menu, homepage service cards, or primary footer navigation.
- Use direct campaign URLs shared through social posts, messages, and campaign-specific links.
- Give campaign pages a minimal promotional layout: Adzbyte logo, “Experimental Launch” or “Limited Promo” badge, and only essential legal/support links.
- Keep the normal agency positioning and custom-service prices separate from the experimental offer.
- Exclude campaign routes from the public sitemap during the validation phase and mark them `noindex` initially. This can be reconsidered if the campaign becomes permanent.
- Track campaign traffic separately through UTMs and dedicated analytics events.

Recommended public route structure in `adzbyte-next`:

- `/go-live` — main social campaign landing page
- `/go-live/products` — off-menu experimental product listing
- `/go-live/idea` — Idea Test Page
- `/go-live/blog` — Blog Lite
- `/go-live/store` — Store Lite
- `/go-live/consultation` — Quick Consultation

The campaign should feel like a limited, direct-response promotion discovered through a shared link—not a new top-level branch of the main website.

## Target Customer

- Filipino first-time or experimental founders
- Side-hustle owners who want to test demand
- Social-media sellers curious about having a standalone page
- People who are unwilling to pay for a conventional website
- Customers comfortable with a template and strict limitations
- Customers who may use the site only once and never maintain it

The experience must be mobile-first, understandable without technical knowledge, and fast enough to complete from a social-media link.

## Product Lineup and Pricing Hypothesis

Pricing is provisional and should be validated before implementation.

| Product | Proposed launch fee | Fixed outcome |
|---|---:|---|
| Idea Test Page | ₱99 | A one-page website for testing an idea |
| Blog Lite | ₱199 | A simple blog with up to three supplied posts |
| Store Lite | ₱299 | A five-product test store using direct ordering |
| Quick Consultation | ₱99 | An asynchronous answer to one focused question |
| First-Look Mockup | Free | One non-editable homepage preview |
| Project Quote | Free | A quote for work outside the experimental products |

### Idea Test Page — ₱99

Includes:

- One template-based responsive page
- Customer-supplied business or product description
- One primary call to action
- Contact or social-media link
- Free preview subdomain
- Included experimental hosting
- Corrections for publishing mistakes only

Does not include:

- Custom design
- Multiple pages
- Copywriting
- Design revisions
- Custom features or integrations

### Blog Lite — ₱199

Includes:

- Template-based blog homepage
- Basic About section
- Up to three customer-supplied posts
- Social and contact links
- Free preview subdomain
- Included experimental hosting

Does not include custom writing, unique branding, ongoing article publishing, advanced SEO, or custom functionality.

### Store Lite — ₱299

Includes:

- Template-based storefront
- Up to five customer-supplied products
- Product name, image, price, and description
- Direct ordering through Messenger or another agreed contact channel
- Displayed COD, GCash, bank-transfer, or pickup instructions
- Free preview subdomain
- Included experimental hosting

This is a demand-testing store, not a complete ecommerce operation. Payment gateways, automated shipping, inventory synchronization, marketplace integrations, and custom checkout are excluded and require a separate quote.

### Quick Consultation — ₱99

Includes:

- One clearly defined question
- One asynchronous response by email, chat, or recorded voice note
- One practical recommendation

Meetings, audits, implementation work, and ongoing advice are excluded. A short free fit check may still be offered for customers considering custom work.

### Free First-Look Mockup

- One homepage direction
- Non-editable preview or image
- Limited to qualified requests
- No source files
- No revisions
- May be converted into an Idea Test Page by paying the launch fee

### Free Project Quote

Quotes remain free. Requests outside the fixed product scope should use the following language:

> Need something outside the package? We’ll provide a clear custom quote before any additional work begins.

Avoid vague language such as “custom charges may apply.”

## Operating Principle

> Customers pay to publish, not to design.

At the proposed prices, fulfillment must take approximately 5–15 minutes per order. The products cannot support manual design or open-ended communication.

Recommended workflow:

1. Customer selects a product in the public `adzbyte-next` UI.
2. Customer supplies the minimum identity and checkout information; the Next.js server sends it to `adzbyte-app` through the authenticated integration interface.
3. `adzbyte-app` creates the buyer, order, and PayMongo Checkout Session.
4. Next.js redirects the customer's browser to PayMongo for the one-time launch fee.
5. `adzbyte-app` confirms payment from PayMongo's signed webhook, sets payment to `paid`, requirements to `in_progress`, and fulfillment to `waiting_for_details`.
6. The customer enters the authenticated Filament `/account` panel in `adzbyte-app`, completes the product-specific brief, chooses a template, uploads assets, and can send order messages asynchronously.
7. Formal brief submission starts the 6–12-hour first-draft window and places the order in `ready_for_review`.
8. Adzbyte reviews the content, requests missing information if necessary, and prepares a draft.
9. The customer reviews the draft, provides the permitted feedback or approval, and follows progress through the `/account` order screen.
10. The site is published and its URL and access instructions are recorded and displayed in `adzbyte-app`.

Phase 1 fulfillment will be manual. Automation remains a later optimization after real demand, abuse patterns, and support requirements are understood.

### Customer-Facing Draft and Delivery Promise

- Deliver the first reviewable draft or consultation outcome within **6–12 hours** after payment is confirmed and the brief is complete.
- Start the first-draft clock only when payment is confirmed and the customer formally submits all required text, images, contact details, and product information through the guided brief.
- If information is incomplete or fails content review, pause the clock and notify the customer.
- Show the 6–12-hour window beside every purchase CTA, in checkout-supporting copy, and in the payment confirmation email.
- Show whether the clock is waiting, active, or paused on the authenticated `/account` order screen, including the reason when more information is required.
- Explain that final publication happens after customer approval and is separate from the first-draft deadline.
- Do not promise instant drafting or publication after payment.

Suggested wording:

> We’ll prepare your first reviewable draft within 6–12 hours after your payment is confirmed and you submit all required content and files. Final publication follows your approval.

### Customer Account Panel

The campaign will use `adzbyte-app` as the central system of record for buyers, users, products, orders, payments, briefs, messages, drafts, sites, and fulfillment activity. WordPress and Hostinger may still run customer sites, but neither should be the authoritative buyer/order database. `adzbyte-next` presents public product information but does not become a second buyer, order, or payment ledger.

The customer management UI lives in the authenticated Filament `/account` panel in `adzbyte-app`. Its initial scope should remain focused:

- Activate the checkout-created account, set or reset a password, log in, and verify an email address through Filament's panel authentication.
- View every order and its current payment and fulfillment status.
- Complete, autosave, and formally submit the structured product brief and required assets.
- Exchange asynchronous customer-visible messages with Adzbyte on each paid order.
- See the 6–12-hour first-draft target and any information requests from Adzbyte.
- Review each delivered draft, submit permitted feedback, and approve it for publication.
- Use only the product controls and content limits granted by the purchase.
- Receive the live URL, hosting activation date, guaranteed hosting end date, and support instructions.
- Submit the correction included with an eligible product.
- View site-level access instructions when a product includes customer editing.

The customer panel is an order communication, draft-review, and site-control portal, not a general-purpose website builder. Do not expose Hostinger hPanel, SFTP, database, or unrestricted administrator access as part of the base promotional products.

Email remains the notification channel, but emails should link customers back to the authenticated Filament `/account` order screen.

### Internal Buyer and Order Tracking

Laravel is the authoritative buyer and order ledger. Every checkout must be associated with a Laravel user and order record before the customer is sent to PayMongo. The public Next.js forms, email notifications, PayMongo, WordPress, and Hostinger are interfaces or integrations around that record rather than independent sources of truth.

Use dedicated relational tables and an Adzbyte administrator interface because an order changes state throughout payment, review, provisioning, publication, and archival.

#### Buyer Identity

Use the buyer’s verified email address as the primary contact identifier and retain their mobile number as a secondary matching field. Associate a repeat purchase with an existing buyer only after an authenticated session or email-verification step; never attach it solely from an email address supplied in a public form. Access to the Filament customer panel still requires authentication.

Collect:

- Full name
- Email address
- Mobile number
- Business or project name
- Product selected
- Social referral source and UTM parameters
- Required consent and policy acknowledgements

#### Order Record

Create the internal order before sending the buyer to PayMongo. Each order should include:

- Internal UUID
- Human-readable public reference such as `GL-20260804-AB12`
- Buyer identifier and contact details
- Product and template selection
- Submitted content and asset references
- Price and currency
- PayMongo Checkout Session ID
- PayMongo payment and event IDs
- Payment status and paid timestamp
- Fulfillment status
- Brief status, questionnaire schema version, and requirements submission timestamp
- SLA readiness, target-from, deadline, pause, resume, and accumulated paused-duration timestamps
- Review notes and rejection reason, if applicable
- Assigned administrator
- Hostinger website UID, when available
- Live URL
- Customer credential-delivery status, without storing a plain-text password
- Hosting activation and expiration dates
- Created and updated timestamps

#### Order Statuses

Do not overload one order-status column with payment, requirements, and fulfillment state. Track them separately so the API and both management panels can explain exactly what is waiting:

```text
payment_status:
  pending → paid → refunded
          ↘ failed | cancelled

requirements_status:
  locked → in_progress → submitted → accepted
                           ↘ needs_information → submitted

fulfillment_status:
  waiting_for_payment
    → waiting_for_details
    → ready_for_review
    → draft_in_progress
    → draft_ready
    → changes_requested → draft_in_progress
    → approved_for_provisioning
    → provisioning
    → quality_check
    → live
    → expiring
    → archived | reactivated
```

Rejection and cancellation are explicit terminal order outcomes reachable from the relevant pre-publication states. Refunds change the payment track and must trigger the corresponding order outcome deliberately; they must not implicitly erase fulfillment or audit history.

#### PayMongo Reconciliation

- Pass the public order reference to PayMongo as the Checkout Session `reference_number` and include the internal order ID in metadata.
- On a valid paid webhook, match the order using the reference and stored Checkout Session ID.
- Confirm that the amount, currency, product, and live/test mode match the internal order before marking it paid.
- Store each processed event ID so webhook retries cannot create duplicate fulfillment work.
- Never store card numbers, e-wallet credentials, or other payment credentials; PayMongo remains the payment system of record.

#### Admin Panel

The Filament `/admin` panel should initially provide:

- Search by order reference, buyer name, email, mobile number, or live URL
- Filters for product, payment state, fulfillment state, and overdue orders
- A visible waiting/active/paused countdown to the 6–12-hour first-draft deadline
- Structured brief completeness, submitted answers, and asset review
- The customer-visible order conversation with reply and request-information actions
- Draft-version creation, preview delivery, feedback, and approval state
- Internal notes and assignment
- Controlled status-change actions
- PayMongo and Hostinger identifiers
- Buttons to resend confirmation, request missing information, mark live, or archive
- A chronological order event log

The admin panel is the team’s operational workspace. It shares the same buyer, order, payment, and site records as the customer panel while exposing privileged fulfillment controls.

#### Privacy and Security

- Restrict administrative order access through explicit Laravel roles and permissions.
- Record administrative status changes in an audit log.
- Store only the customer and payment metadata required to fulfill and support the order.
- Do not store reusable customer passwords in plain text.
- Define retention and deletion rules for rejected, refunded, abandoned, and archived orders.
- Keep PayMongo and Hostinger secrets in server-side Laravel configuration and outside browser-visible code.

## Hosting Lifecycle

Recommended initial policy:

- Hosting is guaranteed for 90 days.
- There is no subscription or automatic renewal.
- Inactive experiments may be archived after the guaranteed period.
- A customer can reactivate an archived experiment for ₱49.
- Active or useful experiments may remain online longer at Adzbyte’s discretion.
- Customers may request a paid upgrade, custom domain, or export before archival.

Suggested customer-facing language:

> Hosting is included for at least 90 days. Inactive experimental sites may be archived afterward, but you can reactivate or upgrade anytime.

### Resource and Reputation Protection

- Prefer static output with no database where possible.
- Do not provide email sending, file storage, user accounts, or server-side custom code in the base products.
- Apply upload, image-size, page-count, and traffic limits.
- Review submissions before publication.
- Prohibit illegal, deceptive, adult, hateful, infringing, phishing, investment-scam, and malware content.
- Include a takedown and abuse-reporting process.
- Reserve the right to archive or remove abusive sites immediately.

Experimental sites should preferably use a separate wildcard domain or isolated subdomain environment so abandoned or low-quality content cannot damage the primary `adzbyte.com` domain’s search or security reputation.

## Adzbyte Attribution and Traffic

Each experiment may include a small footer attribution:

> Launched experimentally with Adzbyte

The attribution should link to the experimental product landing page. It must be visible but should not overpower the customer’s page.

Only reviewed, legitimate, meaningful experiments should be indexable by search engines. New or questionable submissions should default to `noindex` until approved.

Primary program metrics:

- Visits from social posts
- Product-page conversion rate
- Paid launches
- Time required per launch
- Published experiments
- Referral visits from experiment attribution links
- Experiment-to-custom-project upgrades
- Abuse, refund, and archival rates

Revenue from launch fees is secondary to traffic generation, offer validation, and future custom-project opportunities.

## Product Listing Page

**Proposed route:** `/go-live/products`

This is a public `adzbyte-next` page. `adzbyte-app` may manage the underlying product records and supply data to the Next.js server, but it must not render a second product listing.

The page should feel like a small menu of experiments rather than an agency services catalog.

### Page Structure

1. **Hero:** “What do you want to try online?”
2. **Supporting copy:** “Pick a small experiment. We’ll put it online without the cost or commitment of a full website.”
3. **Intent-based product cards:**
   - I want to test an idea — ₱99
   - I want to try blogging — ₱199
   - I want to try selling online — ₱299
   - I want to ask a developer — ₱99
4. **How it works:** choose a product, pay once, complete the brief in `/account`, and receive the draft or outcome within the stated window
5. **What is included for free:** subdomain, experimental hosting, quote, and qualified mockup
6. **Examples:** selected experimental sites or representative demos
7. **Custom work banner:** clear path to a conventional quote
8. **FAQ:** scope, hosting, ownership, revisions, archival, and upgrades
9. **Final CTA:** “Choose an Experiment”

Each card should display the price, exact outcome, turnaround target, hosting guarantee, limitations, and a direct “Launch This” CTA. Filters are unnecessary for the initial four-product catalog.

## Social Campaign Landing Page

**Proposed route:** `/go-live`

This is a public `adzbyte-next` page. There is no corresponding anonymous campaign or landing page in `adzbyte-app`.

This is the main destination for Facebook groups, pages, and other social posts.

### Page Structure

1. **Hero:** “May idea ka? Ilagay muna natin online for ₱99.”
2. **Support:** “Perfect for business ideas, side projects, experiments, and ‘tingnan lang natin kung papatok.’”
3. **Primary CTA:** “Launch My Idea”
4. **Example experiment:** show a realistic live result
5. **Exact ₱99 inclusions and limitations**
6. **Four-step flow:** choose, pay once, complete the authenticated brief, and receive the draft or outcome
7. **Template selection preview**
8. **Other experimental products**
9. **Hosting and archival explanation**
10. **Frequently asked questions**
11. **Final CTA**

The page should be focused, mobile-first, lightweight, and free of unnecessary navigation. Social traffic should be able to understand the complete offer without scheduling a call.

### Suggested Social Copy

> May business idea ka pero hindi ka pa sure kung itutuloy mo? For ₱99, I’ll help you put it online with a working page, free hosting, and a free subdomain. Testing lang—no subscription and no long-term commitment.

Supporting hooks:

- “See how your idea looks online before spending on a full website.”
- “Perfect for side hustles na gusto mo munang i-test.”
- “One-time launch fee. Walang monthly commitment.”
- “May binebenta ka? Try a five-product test store.”

## Payment Acceptance — PayMongo Selected

PayMongo is the selected payment provider. The recommended implementation is **PayMongo Hosted Checkout created through the API**, rather than manually checking payment screenshots.

Hosted Checkout gives the customer a PayMongo-managed payment screen while still allowing Adzbyte to attach an internal order reference and automate processing. PayMongo sends a `checkout_session.payment.paid` webhook when checkout succeeds; that webhook should mark payment as confirmed and unlock the matching order's requirements workflow.

### Recommended Payment Flow

1. Customer selects an experimental product and submits only the minimum buyer and checkout details in `adzbyte-next`.
2. The Next.js server validates the public request and calls an authenticated `adzbyte-app` server endpoint; the browser must not call the trusted application interface with a service credential.
3. `adzbyte-app` creates or identifies the Laravel user and creates an internal order with a unique reference, `payment_status=pending`, `requirements_status=locked`, and `fulfillment_status=waiting_for_payment`.
4. `adzbyte-app` creates a PayMongo Checkout Session containing the order reference and product metadata and returns only the safe checkout URL to Next.js.
5. Next.js redirects the customer to PayMongo's hosted payment page.
6. PayMongo sends `checkout_session.payment.paid` directly to the dedicated `adzbyte-app` webhook endpoint.
7. The app verifies the `Paymongo-Signature` HMAC against the raw request body.
8. The app confirms that the amount, currency, live/test mode, order reference, and Checkout Session match and that the event has not already been processed.
9. Payment changes to `paid`, requirements change to `in_progress`, fulfillment changes to `waiting_for_details`, and the order's structured brief and conversation become available in the authenticated Filament `/account` panel.
10. The app sends an account activation or order-ready notification. Next.js may display the payment return page, but only `adzbyte-app` records authoritative payment state.
11. The customer completes and formally submits the guided brief. Once required content is complete, fulfillment changes to `ready_for_review` and the 6–12-hour first-draft clock starts.
12. After content and abuse review in the Filament admin panel, Adzbyte prepares and delivers a draft through the customer panel before the approved result proceeds to provisioning and publication. The REST API exposes the same action for later clients.

Webhook processing must be idempotent because events can be retried. Store the PayMongo event ID, Checkout Session ID, payment ID, reference number, amount, and final order status. The public success redirect page in `adzbyte-next` is only a customer-facing confirmation; it must not be treated as proof of payment.

### Payment Methods and Small-Order Economics

PayMongo’s published pricing as of August 2026 lists QR Ph at 1.34%, GCash at 2.23%, Maya at 1.79%, and domestic cards at 3.125% plus ₱13.39; the published rates are VAT-exclusive and must be rechecked before launch.

For ₱99–₱299 products:

- Prefer QR Ph, GCash, and Maya.
- Consider disabling cards for the ₱99 product because the fixed card fee consumes a meaningful portion of the launch fee.
- Do not use PayMongo Storefront for this campaign; Hosted Checkout keeps the Adzbyte campaign and order workflow in control.
- Use test keys and test webhooks until the complete order-to-provisioning flow passes end to end.
- Keep live secret keys and webhook secrets server-side only.

### Payment Provider Requirements

- Appropriate for transactions as low as ₱99
- Familiar and accessible to Filipino customers
- Reasonable fixed and percentage fees
- Easy to use from a mobile social-media browser
- Provides an order reference that can be matched to the internal order
- Supports payment confirmation without excessive manual work
- Does not force customers into a subscription
- Has a clear refund and failed-payment process
- Can eventually trigger automated provisioning through an API or webhook

### Remaining Payment Decisions

- Confirm which PayMongo methods are activated for the merchant account.
- Define the refund outcome when paid content is rejected.
- Define the exact account-enrollment and verified-email handoff from the public Next.js flow to the authenticated Filament customer panel.
- Select and rotate the narrow server-to-server authentication mechanism used between `adzbyte-next` and `adzbyte-app`.
- Reconfirm PayMongo’s fees, settlement schedule, merchant requirements, and refund behavior before launch.

## PayMongo-to-Hostinger Provisioning Research

### Conclusion

The payment-to-site workflow can be substantially automated. With a compatible **Hostinger Agency Hosting plan**, the current Hostinger API can provision an isolated website, generate a free `*.hostingersite.com` address, clone an existing template site, install WordPress with supplied administrator credentials, poll provisioning status, retrieve website details, and later delete the experimental site.

The documented API does **not** currently expose an endpoint for automatically inviting a customer into Hostinger’s Access Manager or creating a separate customer hPanel login. Hostinger documents client/site access sharing as an Agency-plan feature performed through Access Manager, with the invited customer accepting an email invitation. Therefore:

- Automation can create and deliver **website-level credentials**.
- Automatic creation of independent **Hostinger hPanel credentials** should not be assumed.
- Customers in this low-cost campaign should not receive access to the Adzbyte Hostinger account.
- If hPanel access or ownership transfer is requested, treat it as a manual paid upgrade.

### Hostinger Agency API Capabilities Relevant to the Campaign

The current official API documents these useful operations:

- Provision a new Agency Plan website asynchronously.
- Omit the custom domain to receive an automatically generated `*.hostingersite.com` preview domain.
- Provision WordPress while supplying the site title, language, administrator username, password, and email.
- Clone the new website from an existing website UID, which can serve as the campaign template.
- Provision a `node-static` website for static frontend products.
- Poll the setup job until it returns `completed` and a `website_uid`.
- Retrieve the website’s preview domain, server details, system username, and SFTP/SSH connection details.
- Link a customer domain later.
- Delete an Agency Plan website and its resources when the hosting lifecycle ends.

The API token inherits the permissions of the Hostinger account owner and must remain in a server-only secret store. It must never be exposed to a customer or browser.

### Future Automated Provisioning Workflow

```text
Customer selects product in adzbyte-next
  → internal user and order created
  → PayMongo Hosted Checkout
  → signed checkout_session.payment.paid webhook
  → order marked paid / waiting_for_details
  → customer completes brief and sends any supporting messages or files
  → brief submitted and 6–12-hour first-draft clock started
  → manual content/abuse approval
  → draft prepared and delivered through the Filament customer panel
  → customer feedback or approval
  → provisioning job queued
  → Hostinger Agency API provisions or clones website
  → poll setup until completed
  → retrieve preview domain and website details
  → apply customer content/template data
  → run smoke check
  → email live URL and site-level access
```

### Credentials by Product

- **Idea Test Page:** Prefer no credentials in phase 1. Publish a static/template page and handle corrections through the order workflow.
- **Blog Lite:** Create a least-privilege WordPress Author or Editor account for the customer rather than exposing the provisioning administrator account. This can be automated later.
- **Store Lite:** If implemented with WordPress/WooCommerce, give the customer a limited store-management role, not Hostinger access or unrestricted server credentials.
- **Quick Consultation:** No hosting or credentials are required.

Hostinger’s provisioning request can set initial WordPress administrator credentials. For safer customer access, the campaign template or post-provisioning worker should create a limited customer account and retain the provisioning administrator account privately. Avoid emailing reusable administrator passwords in plain text; prefer an account-activation or password-reset flow.

### Recommended Rollout

Use a **manual-provisioning phase 1**:

1. Use PayMongo checkout and record confirmed payments against internal order references.
2. Keep content approval manual to prevent automated spam, phishing, and prohibited-content publication.
3. Prepare and deliver the first reviewable draft within the 6–12-hour promise; after customer approval, provision, configure, review, and publish the Hostinger website manually.
4. Send the live URL and any limited site-level credentials manually.
5. Record the manual steps and time spent so the highest-value automation opportunities are based on evidence.
6. Introduce Hostinger API provisioning later while retaining the content-approval checkpoint.

Before implementation, confirm that the existing Hostinger order is an Agency Hosting plan and that its API token can access the Agency Hosting endpoints. If the current plan is not compatible, the fallback is to publish the Idea Test Page from a shared multi-tenant static application and provision WordPress products manually until the hosting setup is changed.

## Official References

- [Filament 5 installation](https://filamentphp.com/docs/5.x/introduction/installation)
- [Filament 5 PHP and Livewire architecture](https://filamentphp.com/docs/5.x/introduction/overview)
- [Filament panel users and authentication](https://filamentphp.com/docs/5.x/users/overview)
- [Filament panel access and authorization](https://filamentphp.com/docs/5.x/advanced/security)
- [Laravel Sanctum SPA and API authentication](https://laravel.com/docs/13.x/sanctum)
- [Laravel API Resources](https://laravel.com/docs/13.x/eloquent-resources)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v8/introduction)
- [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield)
- [PayMongo Hosted Checkout](https://docs.paymongo.com/docs/payment-channels-hosted-checkout)
- [PayMongo webhook setup and signature verification](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management)
- [PayMongo webhook event reference](https://docs.paymongo.com/reference/webhook-resource)
- [PayMongo pricing](https://www.paymongo.com/pricing)
- [Hostinger API reference](https://developers.hostinger.com/)
- [Hostinger official OpenAPI specification](https://github.com/hostinger/api/blob/main/openapi.json)
- [Hostinger Agency Hosting overview and client access](https://support.hostinger.com/en/articles/10656861-agency-hosting-plans-how-to-get-started)
- [Hostinger account sharing behavior](https://support.hostinger.com/en/articles/1583777-how-to-share-access-to-your-account)

## Outstanding Decisions Before Relevant Implementation

### Product and Commercial

- [ ] Confirm or revise the ₱99 / ₱199 / ₱299 launch prices.
- [ ] Confirm the 90-day hosting guarantee and ₱49 reactivation fee.
- [ ] Choose the experimental-site domain strategy.
- [ ] Define the first two templates for each product.
- [ ] Decide whether the free mockup is public or available only after qualification.

### Customer Policy and Operations

- [ ] Define content rules, takedown terms, privacy language, and refund policy.
- [ ] Approve each product's brief questions, required assets, and automated definition of a complete brief.
- [ ] Define asynchronous messaging expectations, attachment limits, moderation rules, and notification behavior.
- [ ] Decide whether the 6–12-hour first-draft window uses elapsed hours or published business hours, including weekend and holiday behavior.
- [ ] Define the limited WordPress site roles and credential-delivery method for Blog Lite and Store Lite.
- [ ] Approve the detailed administrator workflow and buyer-data retention period.

### Technical and Integration

- [ ] Confirm whether the existing Hostinger subscription supports Agency Hosting API provisioning.
- [ ] Confirm PayMongo account activation and enabled payment methods.
- [ ] Approve the initial REST API release scope and payload contract.
- [ ] Choose and rotate the restricted service credential used by `adzbyte-next`.
- [x] Use `https://adzbyte.com` for `adzbyte-next` and `https://app.adzbyte.com` for `adzbyte-app` when configuring future Sanctum session-cookie and CORS settings.

## Out of Scope Until Approved

- Page design and implementation
- Fully automatic publication without content review
- Custom domains
- Recurring hosting subscriptions
- Full ecommerce payment processing
- Unlimited revisions or support
- Custom development within the experimental launch fee
- Customer management screens in `adzbyte-next` during phase 1
