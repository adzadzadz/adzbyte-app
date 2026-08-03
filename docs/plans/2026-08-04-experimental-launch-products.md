# Experimental Launch Products — Product Strategy

**Date:** 2026-08-04  
**Status:** Draft — approved as a direction, implementation not started  
**Market:** Philippines  
**Acquisition:** Organic posts in social media groups and pages  
**Visibility:** Off-menu promotional campaign  
**Payment provider:** PayMongo

## Overview

Adzbyte will offer extremely low-cost, one-time web experiments for Filipino founders who have an idea but are not ready to commit to a conventional website project. The expected customer may publish the site once, share it briefly, and then abandon it.

This is not intended to be a low-priced custom-development service. It is a template-driven publishing service that makes use of existing hosting capacity, creates a source of referral traffic for Adzbyte, and gives a small number of experimental founders a path into future custom work.

## System Boundary

- `adzbyte-next` remains the public Adzbyte marketing website and hosts the off-menu `/go-live` promotional pages.
- `adzbyte-laravel` owns authentication, buyers, products, orders, content submissions, PayMongo checkout and webhooks, fulfillment workflows, sites, notifications, and customer/admin dashboards.
- PayMongo remains the payment system of record for transaction processing; Laravel stores the references and reconciled payment state needed to operate each order.
- Hostinger, WordPress, and static hosting are fulfillment targets. Their identifiers and lifecycle state are linked back to the Laravel order and site records.

This separation protects the public website from application complexity while giving Adzbyte one operational source of truth across every promotional product.

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

Recommended route structure:

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

1. Customer selects a product.
2. Customer completes a structured content form.
3. Customer chooses from two or three templates.
4. Customer pays the one-time launch fee.
5. Submitted content is inserted into the selected template.
6. Adzbyte performs a quick content and abuse review.
7. The site is published and its URL is sent to the customer.

Phase 1 fulfillment will be manual. Automation remains a later optimization after real demand, abuse patterns, and support requirements are understood.

### Customer-Facing Fulfillment Promise

- Publish within **6–12 hours** after both payment and complete content are received.
- Start the fulfillment clock only when all required text, images, contact details, and product information have been submitted.
- If information is incomplete or fails content review, pause the clock and notify the customer.
- Show the 6–12-hour window beside every purchase CTA, in checkout-supporting copy, and in the payment confirmation email.
- Do not promise instant publishing or imply that the site is generated immediately after payment.

Suggested wording:

> Your site will be reviewed, prepared, and published within 6–12 hours after payment and complete content submission.

### Centralized Adzbyte Account and Dashboard

The campaign will use `adzbyte-laravel` as the central system of record for buyers, users, products, orders, payments, submissions, sites, and fulfillment activity. WordPress and Hostinger may still run customer sites, but neither should be the authoritative buyer/order database.

The first customer dashboard should remain intentionally small:

- Register, log in, reset a password, and verify an email address.
- View every order and its current payment and fulfillment status.
- Complete or update the structured content submission while an order is awaiting information.
- See the 6–12-hour fulfillment target and any information requests from Adzbyte.
- Receive the live URL, hosting activation date, guaranteed hosting end date, and support instructions.
- Submit the correction included with an eligible product.
- View site-level access instructions when a product includes customer editing.

The dashboard is an order-and-site portal, not a general-purpose website builder. Do not expose Hostinger hPanel, SFTP, database, or unrestricted administrator access as part of the base promotional products.

Email remains the notification channel, but emails should link customers back to the Laravel dashboard so order history and operational state remain centralized.

### Internal Buyer and Order Tracking

Laravel is the authoritative buyer and order ledger. Every checkout must be associated with a Laravel user and order record before the customer is sent to PayMongo. Forms, email notifications, PayMongo, WordPress, and Hostinger are integrations around that record rather than independent sources of truth.

Use dedicated relational tables and an Adzbyte administrator interface because an order changes state throughout payment, review, provisioning, publication, and archival.

#### Buyer Identity

Use the buyer’s verified email address as the primary contact identifier and retain their mobile number as a secondary matching field. A repeat purchase can be associated with the same internal buyer record without requiring the customer to create or remember a password.

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
- Review notes and rejection reason, if applicable
- Assigned administrator
- 6–12-hour fulfillment deadline
- Hostinger website UID, when available
- Live URL
- Customer credential-delivery status, without storing a plain-text password
- Hosting activation and expiration dates
- Created and updated timestamps

#### Order Statuses

Use an explicit status flow:

```text
draft
  → awaiting_payment
  → paid_pending_review
  → needs_information | rejected | approved_for_provisioning
  → provisioning
  → quality_check
  → live
  → expiring
  → archived | reactivated
```

Refunded and cancelled should be terminal branches that can be reached from the relevant pre-publication states.

#### PayMongo Reconciliation

- Pass the public order reference to PayMongo as the Checkout Session `reference_number` and include the internal order ID in metadata.
- On a valid paid webhook, match the order using the reference and stored Checkout Session ID.
- Confirm that the amount, currency, product, and live/test mode match the internal order before marking it paid.
- Store each processed event ID so webhook retries cannot create duplicate fulfillment work.
- Never store card numbers, e-wallet credentials, or other payment credentials; PayMongo remains the payment system of record.

#### Internal Admin View

The Laravel administrator dashboard should initially provide:

- Search by order reference, buyer name, email, mobile number, or live URL
- Filters for product, payment state, fulfillment state, and overdue orders
- A visible countdown to the 6–12-hour deadline
- Submitted content and asset review
- Internal notes and assignment
- Controlled status-change actions
- PayMongo and Hostinger identifiers
- Buttons to resend confirmation, request missing information, mark live, or archive
- A chronological order event log

This internal admin screen is the team’s operational dashboard. It shares the same buyer, order, payment, and site records as the customer dashboard while exposing privileged fulfillment controls.

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

The page should feel like a small menu of experiments rather than an agency services catalog.

### Page Structure

1. **Hero:** “What do you want to try online?”
2. **Supporting copy:** “Pick a small experiment. We’ll put it online without the cost or commitment of a full website.”
3. **Intent-based product cards:**
   - I want to test an idea — ₱99
   - I want to try blogging — ₱199
   - I want to try selling online — ₱299
   - I want to ask a developer — ₱99
4. **How it works:** submit, pay, and receive the live URL within 6–12 hours
5. **What is included for free:** subdomain, experimental hosting, quote, and qualified mockup
6. **Examples:** selected experimental sites or representative demos
7. **Custom work banner:** clear path to a conventional quote
8. **FAQ:** scope, hosting, ownership, revisions, archival, and upgrades
9. **Final CTA:** “Choose an Experiment”

Each card should display the price, exact outcome, turnaround target, hosting guarantee, limitations, and a direct “Launch This” CTA. Filters are unnecessary for the initial four-product catalog.

## Social Campaign Landing Page

**Proposed route:** `/go-live`

This is the main destination for Facebook groups, pages, and other social posts.

### Page Structure

1. **Hero:** “May idea ka? Ilagay muna natin online for ₱99.”
2. **Support:** “Perfect for business ideas, side projects, experiments, and ‘tingnan lang natin kung papatok.’”
3. **Primary CTA:** “Launch My Idea”
4. **Example experiment:** show a realistic live result
5. **Exact ₱99 inclusions and limitations**
6. **Three-step flow:** submit details, pay once, receive the link
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

Hosted Checkout gives the customer a PayMongo-managed payment screen while still allowing Adzbyte to attach an internal order reference and automate fulfillment. PayMongo sends a `checkout_session.payment.paid` webhook when checkout succeeds; that webhook should move the matching internal order into the fulfillment queue.

### Recommended Payment Flow

1. Customer selects an experimental product and submits the structured content form.
2. Adzbyte creates an internal order with a unique reference and `awaiting_payment` status.
3. The server creates a PayMongo Checkout Session containing the order reference and product metadata.
4. Customer completes payment on PayMongo’s hosted page.
5. PayMongo sends `checkout_session.payment.paid` to an Adzbyte webhook endpoint.
6. The webhook verifies the `Paymongo-Signature` HMAC against the raw request body.
7. The webhook confirms the amount, currency, live/test mode, order reference, and that the event has not already been processed.
8. The order changes to `paid_pending_review`; payment success alone must not publish customer content immediately.
9. After content and abuse review, the order moves to `approved_for_provisioning` for manual phase 1 fulfillment.
10. The customer receives a confirmation and later receives the live URL and any site-level credentials.

Webhook processing must be idempotent because events can be retried. Store the PayMongo event ID, Checkout Session ID, payment ID, reference number, amount, and final order status. The success redirect page is only a customer-facing confirmation; it must not be treated as proof of payment.

### Payment Methods and Small-Order Economics

PayMongo’s published pricing as of August 2026 lists QR Ph at 1.34%, GCash at 2.23%, Maya at 1.79%, and domestic cards at 3.125% plus ₱13.39; the published rates are VAT-exclusive and must be rechecked before launch.

For ₱99–₱299 products:

- Prefer QR Ph, GCash, and Maya.
- Consider disabling cards for the ₱99 product because the fixed card fee consumes a meaningful portion of the launch fee.
- Do not use PayMongo Storefront for this campaign; Hosted Checkout keeps the Adzbyte campaign and order workflow in control.
- Use test keys and test webhooks until the complete order-to-provisioning flow passes end to end.
- Keep live secret keys and webhook secrets server-side only.

### Requirements

- Appropriate for transactions as low as ₱99
- Familiar and accessible to Filipino customers
- Reasonable fixed and percentage fees
- Easy to use from a mobile social-media browser
- Provides an order reference that can be matched to a submission
- Supports payment confirmation without excessive manual work
- Does not force customers into a subscription
- Has a clear refund and failed-payment process
- Can eventually trigger automated provisioning through an API or webhook

### Remaining Payment Decisions

- Confirm which PayMongo methods are activated for the merchant account.
- Decide whether content review happens before checkout or immediately after payment.
- Define the refund outcome when paid content is rejected.
- Decide whether customers need an account or can use email plus an order reference.
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
Customer submits content
  → internal order created
  → PayMongo Hosted Checkout
  → signed checkout_session.payment.paid webhook
  → order marked paid_pending_review
  → manual content/abuse approval
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
3. Provision, configure, review, and publish the Hostinger website manually within the 6–12-hour customer promise.
4. Send the live URL and any limited site-level credentials manually.
5. Record the manual steps and time spent so the highest-value automation opportunities are based on evidence.
6. Introduce Hostinger API provisioning later while retaining the content-approval checkpoint.

Before implementation, confirm that the existing Hostinger order is an Agency Hosting plan and that its API token can access the Agency Hosting endpoints. If the current plan is not compatible, the fallback is to publish the Idea Test Page from a shared multi-tenant static application and provision WordPress products manually until the hosting setup is changed.

### Official Research References

- [PayMongo Hosted Checkout](https://docs.paymongo.com/docs/payment-channels-hosted-checkout)
- [PayMongo webhook setup and signature verification](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management)
- [PayMongo webhook event reference](https://docs.paymongo.com/reference/webhook-resource)
- [PayMongo pricing](https://www.paymongo.com/pricing)
- [Hostinger API reference](https://developers.hostinger.com/)
- [Hostinger official OpenAPI specification](https://github.com/hostinger/api/blob/main/openapi.json)
- [Hostinger Agency Hosting overview and client access](https://support.hostinger.com/en/articles/10656861-agency-hosting-plans-how-to-get-started)
- [Hostinger account sharing behavior](https://support.hostinger.com/en/articles/1583777-how-to-share-access-to-your-account)

## Decisions Required Before Implementation

1. Confirm or revise the ₱99 / ₱199 / ₱299 launch prices.
2. Confirm the 90-day hosting guarantee and ₱49 reactivation fee.
3. Choose the experimental-site domain strategy.
4. Define the first two templates for each product.
5. Confirm whether the existing Hostinger subscription supports Agency Hosting API provisioning.
6. Confirm PayMongo account activation and enabled payment methods.
7. Define content rules, takedown terms, privacy language, and refund policy.
8. Decide whether the free mockup is available publicly or only after qualification.
9. Decide whether content review occurs before or immediately after payment.
10. Define the limited customer roles and credential-delivery method for Blog Lite and Store Lite.
11. Approve the Laravel account, administrator workflow, buyer-data retention period, and initial customer-dashboard scope.

## Out of Scope Until Approved

- Page design and implementation
- Fully automatic publication without content review
- Custom domains
- Recurring hosting subscriptions
- Full ecommerce payment processing
- Unlimited revisions or support
- Custom development within the experimental launch fee
