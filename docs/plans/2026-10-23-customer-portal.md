# Customer portal priority — 2026-10-23

## Owner-directed record management extension — 2026-10-24

Keep Filament/Livewire. Administrators manage editable business records through
authorized forms and recoverably hide/restore business and historical records.
Payment/provider/audit/version history remains immutable, and financial state
changes stay in operational workflows. Active cart changes remain in customer
checkout by the owner's explicit selection. Deletion is separately identified
from business status and must retain original database rows and lifecycle
history, including parent recovery and deleted-identity access denial.

Source `9a46720` meets the applicable development gates: both full PHP 8.3.36
SQLite/MySQL suites pass 723 tests / 9,380 assertions each, along with migration/
race, independent review and actual desktop/mobile workflow proof. See the
[implementation and evidence](../evidence/2026-10-24-filament-ux/record-management.md).
The owner explicitly holds deployment; no existing database migration occurred.

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

- Customer Dashboard summarizes current projects, services, next steps and support.
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
- Unread indicators and Dashboard activity link to the relevant record. Email
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
require the separate release gate, completed in the follow-up recorded below.


## Verified implementation

Source `d93b6b4` passes [CI 39911846713](https://github.com/adzadzadz/adzbyte-app/actions/runs/39911846713):
556 tests / 7,804 assertions independently on PHP 8.3.36 SQLite and MySQL 8.0.46,
zero audit findings, formatting/metadata/build/cache gates. Native desktop/320px,
manual staff assignment → customer visibility, linked support and staff reply,
private-note denial, real HTTP/OpenAPI/CSRF and process contention are recorded
in [qualification](../evidence/2026-10-23-customer-portal/qualification.md).
The scoped portal increment is complete in source. Deployment and existing
application database migrations have not been performed.

## Workflow polish and release follow-up

Owner requested inline linked conversations, expandable full inbox, Dashboard
navigation, manual customer provisioning and a reviewed production deployment.
The shared inbox now supports customer and capability-scoped staff context;
project/service creation and replies stay in place. General and linked cases
remain in the full inbox. Customer management creates a customer-only identity
and atomically queues its signed activation invitation on the application database
queue; no administrator-chosen password is exposed. Customer activation resend is
explicit and rate limited. Admin Dashboard links customer setup, work and support.

Production preflight discovered independently deployed native package checkout
and fulfilment at deploy `1acfad0` / source `30c8cf3`. Preserve those routes,
provider callbacks, delivery recovery and stored records during integration.
Review and release evidence will record the combined qualification. No real
customer has been provisioned or emailed during local checks.


## Deployed acceptance checkpoint

The independently approved portal/workflow source `bbf1ae5a9f4d` is deployed as
`230f2cd90d2b`; [release 39924287598](https://github.com/adzadzadz/adzbyte-app/actions/runs/39924287598)
passes both full PHP 8.3 suites (662 tests / 8,632 assertions each). Hostinger pull,
post-pull, all 22 migrations, HTTPS health and queue processing pass. The owner
separately approved access initialization and the new owner account; its secure
setup email was processed. Password choice and email verification remain owner
actions. No real customers or synthetic production fixtures were created.
Native public-site package integration and its encrypted records are preserved.
See [completed release evidence](../evidence/2026-10-23-customer-portal/polish/qualification.md).
