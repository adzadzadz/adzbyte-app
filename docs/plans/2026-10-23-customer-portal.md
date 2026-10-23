# Customer portal priority — 2026-10-23

Owner direction: pause ecommerce development; complete the customer portal,
prioritizing project and service tracking. Include support requests/reports and
customer–staff conversations. Show ecommerce as **Under development** in
production so customers can see what is being built.

## Delivery boundary

Staff create customer-owned projects and services independently of checkout.
These are operational tracking records, not purchased entitlements, billing
agreements, subscriptions, or proof of payment. The existing O2 refund blocker
and commerce qualification gates remain recorded and paused; this independent
portal work does not mark O3/S2/R1/Q complete.

- Customer Home summarizes current projects, services, next steps and support.
- Projects/services provide status, summary, target date, next step, milestones
  and a dated update history. Milestone progress is derived from completed
  milestones, with no invented percentage when none are defined.
- Staff own project/service creation, changes, milestones and updates. Records
  retain a stable owner; customers cannot modify fulfillment state or claim work.
- Customers create requests/reports with an optional owned work reference,
  follow status, and exchange messages with authorized staff. Reports about
  account, privacy, security or payment never require a purchase or work record.
- Categories are managed by staff; Normal is the default priority; assignment
  and reopening are manual. No response-time promise or SLA is introduced.
- Internal notes are stored separately from customer messages. History is
  retained; no destructive panel action or customer-supplied owner is accepted.
- Google Drive file/folder links can be shared in messages. No native uploads,
  document fetching, or public sharing requirement is introduced.
- Unread indicators and Home activity link to the relevant record. Email
  delivery is a separate qualification boundary; no email delivery claim is
  made by an in-app indicator.
- Production ecommerce screens remain reachable with an Under development banner and block
  customer checkout mutations. Provider callbacks and administration remain
  available for existing operations. Local/test commerce behavior is preserved.

## Acceptance and verification

Shared services and policies back Filament and versioned customer APIs.
Verify owned reads, cross-customer and revoked-staff denial, strict payloads,
escaped content, immutable history, idempotent retries, revision conflicts,
bounded pagination and invalid state/assignment/link inputs. Verify the full
staff → customer tracking → support → staff response journey, Home and empty
states at desktop/mobile, real CSRF/API transport, and production preview.

Apply/reverse/reapply the new schema only in disposable databases, including
preservation of earlier application records. Run relevant/full tests, PHP
formatting, production build, Composer metadata, audits and supported PHP
8.3 SQLite/MySQL CI. Production deployment and existing database migrations
require the separate release gate; this task prepares source only.
