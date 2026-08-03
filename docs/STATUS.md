# Project Status

**Last updated:** 2026-08-04 (Hostinger release flow and management branding plan committed locally)

## Current Stage

Foundation phase F is complete. In addition to the F1 framework and access foundation, both Filament panels now provide login, logout, password reset, required email verification, verified email changes, and profile management without open registration. Shared actions create provisional customers and send signed customer activation links for later verified-payment orchestration. The repository-side Hostinger release flow and the management UI branding direction are committed locally but have not been pushed or activated externally.

## In Progress

Nothing. The session-owned repository changes are verified and committed locally.

## Up Next

**CI/CD activation task — complete the first explicitly authorized Hostinger
release.** Push the local `main` commits, allow GitHub Actions content writes,
create the GitHub `production` environment, and connect Hostinger Git
auto-deployment after the manual workflow creates `deploy`. Verify the Hostinger
pull and execute the documented post-pull script over SSH or manually during the
same release window.

## F2 Verification

- Both panels expose Filament login, logout, password reset, email verification, verified email-change, and profile routes; neither exposes registration.
- Trusted provisional-customer creation normalizes identity data, assigns only `customer`, creates no usable public password, and rejects existing emails with a generic authentication-required outcome.
- Customer activation is queued by a shared action and uses a signed 24-hour URL, strong password validation, email verification, login/session rotation, role checks, replay protection, and five-attempt-per-minute throttling.
- Framework `Login` and `Verified` events and application customer-provisioned/customer-activated events are covered.
- Confirmed Sanctum stateful origins include `adzbyte.com` and `app.adzbyte.com`.
- Focused F2 tests: 14 passed, 68 assertions.
- Full PHP suite: 25 passed, 91 assertions.
- Configuration caching, Pint, Composer validation, route/middleware inspection, npm dependency audit, and the production asset build passed.

## CI/CD and Branding Plan Verification

- Full PHP tests remained green at 25 tests and 91 assertions; Pint and the production frontend build passed during setup verification.
- The CI, manual promotion, Hostinger post-pull, and explicit-command authorization paths were reviewed; workflow/interface YAML and shell syntax checks passed.
- The release skill passed its validator during creation and has implicit invocation disabled.
- Branding source colors, font weights, logo files, image directories, documentation links, and ownership boundaries were checked against the local `adzbyte-next` source.
- Repository diffs passed whitespace checks, and no supplied local password, production credential, or private key was found in the committed files.

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
- The [management branding plan](plans/2026-08-04-management-ui-branding.md) adapts the palette, Poppins typography, wordmark, square mark, and context-appropriate media from `adzbyte-next` into a dark-first Filament system; `adzbyte-app` must copy, optimize, and version what it uses without hotlinks or runtime repository coupling.
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

After CI/CD activation, the first item blocks the opening D1
product-and-entitlement slice:

- Exact product prices, entitlements, templates, and hosting/reactivation terms.
  The current recommendation is Idea Test Page at PHP 99, Blog Lite at PHP 199,
  Store Lite at PHP 299, Quick Consultation at PHP 99, First-Look Mockup and
  Quote as a free non-checkout lead flow, 90 days of included hosting, and PHP 49
  reactivation. Seed the confirmed entitlements in D1 while deferring concrete
  template names until design assets exist.
- Elapsed hours versus published business hours for the first-draft window
- PayMongo methods and refund policy
- Attachment limits, moderation rules, retention periods, and notification behavior
- Hostinger Agency API availability and site credential-delivery rules

## Known Issues

- None recorded.

## Working Tree Handoff

- F1 and F2 remain preserved in prior local history.
- Commit `2ecd1c2` adds the explicit-command Hostinger CI/CD and release flow.
- Commit `8643a1a` adds the polished management UI branding plan and synchronized documentation.
- The local super-administrator identity is verified; its password is not stored in the repository.
- The session-owned development server was stopped.
- Nothing has been pushed, deployed, or applied to production.
