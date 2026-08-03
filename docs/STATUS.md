# Project Status

**Last updated:** 2026-08-04 (F2 complete; D1 product decision prepared for the next session)

## Current Stage

Foundation phase F is complete. In addition to the F1 framework and access foundation, both Filament panels now provide login, logout, password reset, required email verification, verified email changes, and profile management without open registration. Shared actions create provisional customers and send signed customer activation links for later verified-payment orchestration.

## In Progress

Nothing. F2 is verified and D1 has not started.

## Up Next

**Decision task — Lock the initial product catalog and entitlement baseline before D1.** Confirm or revise the recommended launch configuration:

- Idea Test Page: PHP 99
- Blog Lite: PHP 199
- Store Lite: PHP 299
- Quick Consultation: PHP 99
- First-Look Mockup and Quote: free, non-checkout lead flow
- Included hosting: 90 days; reactivation: PHP 49
- Seed current plan entitlements in D1, while deferring concrete template names until design assets exist

After confirmation, implement the D1 product and versioned-entitlement records, factories, seed data, policies, and tests from the [implementation roadmap](plans/2026-08-04-implementation-roadmap.md).

## F2 Verification

- Both panels expose Filament login, logout, password reset, email verification, verified email-change, and profile routes; neither exposes registration.
- Trusted provisional-customer creation normalizes identity data, assigns only `customer`, creates no usable public password, and rejects existing emails with a generic authentication-required outcome.
- Customer activation is queued by a shared action and uses a signed 24-hour URL, strong password validation, email verification, login/session rotation, role checks, replay protection, and five-attempt-per-minute throttling.
- Framework `Login` and `Verified` events and application customer-provisioned/customer-activated events are covered.
- Confirmed Sanctum stateful origins include `adzbyte.com` and `app.adzbyte.com`.
- Focused F2 tests: 14 passed, 68 assertions.
- Full PHP suite: 25 passed, 91 assertions.
- Configuration caching, Pint, Composer validation, route/middleware inspection, npm dependency audit, and the production asset build passed.

## F1 Verification

- Installed Filament 5.7.5, Sanctum 4.3.3, Spatie Laravel Permission 8.3.0, and Filament Shield 4.3.1.
- Both panel route sets boot and require authentication.
- Customer, administrator, super-administrator, dual-role, and no-role access boundaries are covered by tests.
- Shield role management is available only to authorized super administrators by default.
- The role seeder is repeatable; local super-administrator creation uses `php artisan shield:super-admin --panel=admin`.
- Migration apply, rollback, re-apply, and seed verification passed on a disposable SQLite database.
- Focused tests: 9 passed, 21 assertions.
- Full PHP suite: 11 passed, 23 assertions.
- Pint, Composer validation, and Composer security audit passed.

## Decisions Already Locked

- `adzbyte-next` is the only public product UI.
- `adzbyte-app` owns all customer and administrator management.
- Both management panels are authenticated Filament panels in this repository.
- The REST API is built in parallel for restricted integration and later selective Next.js use.
- Roles begin with `customer`, `administrator`, and `super_admin`.
- `adzbyte-next` is live at `https://adzbyte.com` and `adzbyte-app` will be hosted at `https://app.adzbyte.com`.
- The initial super-administrator identity email is `adzbite@gmail.com`; no password or activation secret is stored in the repository.
- New buyers receive a single-use 24-hour signed activation link after verified payment; setting the password verifies the email and signs the customer into `/account`.
- Existing account emails must sign in or reset their password before checkout can attach another order, without public account-existence disclosure.
- Laravel policies and record ownership remain authoritative across every interface.
- The 6–12-hour commitment covers the first reviewable draft or consultation outcome after payment confirmation and a complete submitted brief.

## Decisions Needed Later

The first item blocks the opening D1 product-and-entitlement slice and is the exact next-session decision:

- Exact product prices, entitlements, templates, and hosting/reactivation terms
- Elapsed hours versus published business hours for the first-draft window
- PayMongo methods and refund policy
- Attachment limits, moderation rules, retention periods, and notification behavior
- Hostinger Agency API availability and site credential-delivery rules

## Known Issues

- None recorded.

## Working Tree Handoff

- F1 and F2 application changes, PHP and npm dependency lockfile updates, documentation changes, and project-local skills are preserved in local Git history through coherent session-end commits.
- No session-owned changes remain uncommitted. Nothing has been pushed, deployed, or applied to production.
