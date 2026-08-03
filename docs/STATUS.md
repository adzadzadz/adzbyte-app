# Project Status

**Last updated:** 2026-08-04 (M1.1 customer Home and navigation foundation completed locally)

## Current Stage

Foundation phase F, management branding phase M0, and the M1.1 customer Home foundation are complete. Both Filament panels provide login, logout, password reset, required email verification, verified email changes, and profile management without open registration. The customer panel owns the authenticated root `/` dashboard and root-level auth routes; only administration uses `/admin`. Its first-party Home now replaces the stock account widget with explicit Home navigation, a customer-specific welcome, verified account context, a profile action, and a truthful pre-domain workspace state. Both panels share the locally bundled Adzbyte theme, Poppins typography, versioned logo assets, explicit panel identity, and accessible dark-first component treatment. Shared actions continue to create provisional customers and send signed customer activation links for later verified-payment orchestration.

The previously released PHP 8.3-compatible application remains deployed to Hostinger through the machine-managed `deploy` branch. M0 and the root-route change are verified on `main` but require a future explicitly authorized release before production changes from `/account` to `/`.

## In Progress

Nothing. M1.1 implementation and local verification are complete.

## Up Next

**M2.1 first-party administrator dashboard foundation — replace the remaining
stock administrator account widget with a restrained operational shell and
navigation foundation that introduces no order, payment, SLA, catalog, or
product-specific rules.** Product/catalog discussion and D1 remain deferred until
the authenticated application core is functional.

## F2 Verification

- Both panels expose Filament login, logout, password reset, email verification, verified email-change, and profile routes; the customer routes are rooted at `/`, and neither panel exposes registration.
- Trusted provisional-customer creation normalizes identity data, assigns only `customer`, creates no usable public password, and rejects existing emails with a generic authentication-required outcome.
- Customer activation is queued by a shared action and uses a signed 24-hour URL, strong password validation, email verification, login/session rotation, role checks, replay protection, and five-attempt-per-minute throttling.
- Framework `Login` and `Verified` events and application customer-provisioned/customer-activated events are covered.
- Confirmed Sanctum stateful origins include `adzbyte.com` and `app.adzbyte.com`.
- Focused F2 tests: 14 passed, 68 assertions.
- Full PHP suite: 25 passed, 91 assertions.
- Configuration caching, Pint, Composer validation, route/middleware inspection, npm dependency audit, and the production asset build passed.

## CI/CD and Branding Plan Verification

- Remote `main` contains the release flow and the PHP 8.3 dependency correction at source commit `12ed0c81d235`.
- GitHub Actions content writes are enabled and the `production` environment exists.
- CI run 2 passed the full verification workflow after Composer was pinned to a PHP 8.3 resolution platform and Symfony packages were resolved to compatible 7.4 releases.
- The authorized release workflow run `30860097717` passed and promoted source commit `12ed0c81d235` to deploy commit `eda1d21cc641`.
- Hostinger pulled the `deploy` branch into `public_html`, installed production dependencies, and reports the deployment completed on PHP 8.3.
- A dedicated production database and mode-`600` `.env` were created without storing credentials in Git. A pre-migration dump is retained outside the web root.
- A dedicated SSH key is authorized with mode-`600` key-file permissions. Both the Laravel scheduler and stop-when-empty database queue worker are configured in hPanel at `* * * * *` and have produced cron output records.
- All five production migrations ran, Laravel production caches were built, the manual scheduler and queue checks passed, and both `jobs` and `failed_jobs` were empty after verification.
- `/up`, `/account/login`, and `/admin/login` each returned HTTP 200 over HTTPS. The only Laravel errors were expected first-boot entries created before the application key and database tables existed; later verification created no new errors.
- Production mail authenticates through the primary Hostinger mailbox and sends application mail from the `notifications@adzbyte.com` alias. Hostinger accepted a Laravel SMTP test message to the primary mailbox without exposing the credential.
- Full PHP tests remained green at 25 tests and 91 assertions; Pint and the production frontend build passed during setup verification.
- The CI, manual promotion, Hostinger post-pull, and explicit-command authorization paths were reviewed; workflow/interface YAML and shell syntax checks passed.
- The release skill passed its validator during creation and has implicit invocation disabled.
- Branding source colors, font weights, logo files, image directories, documentation links, and ownership boundaries were checked against the local `adzbyte-next` source.
- M0 now uses one shared panel configurator and compiled theme, local Fontsource Poppins weights 300–700, three checksum-verified brand assets documented in `resources/brand/assets.json`, forced dark mode, explicit customer/admin identity, and panel-specific density.
- Customer login, administrator login, activation, and both authenticated dashboards were reviewed at desktop and `320px`; no horizontal overflow or browser-console warning remained, and primary auth actions stayed inside the initial mobile viewport.
- Focused branding, routing, authentication, and panel-boundary verification passed with 26 tests and 128 assertions; the full PHP suite passed with 28 tests and 131 assertions, and the production Vite build emitted only local Poppins font assets.
- M1.1 replaces the customer panel's stock account widget with a first-party Home while retaining Filament's panel authorization on every request. Focused dashboard and panel-boundary verification passed with 15 tests and 72 assertions; the full PHP suite passed with 31 tests and 145 assertions, and the production asset build passed.
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
- Both management panels are authenticated Filament panels in this repository: customers use the root `/` panel and administrators use `/admin`.
- The REST API is built in parallel for restricted integration and later selective Next.js use.
- Roles begin with `customer`, `administrator`, and `super_admin`.
- `adzbyte-next` is live at `https://adzbyte.com` and `adzbyte-app` will be hosted at `https://app.adzbyte.com`.
- The initial super-administrator identity email is `adzbite@gmail.com`; no password or activation secret is stored in the repository.
- New buyers receive a single-use 24-hour signed activation link after verified payment; setting the password verifies the email and signs the customer into `/`.
- Product/catalog discussion and D1 implementation are deferred until the authenticated `adzbyte-app` core is functional.
- Existing account emails must sign in or reset their password before checkout can attach another order, without public account-existence disclosure.
- Laravel policies and record ownership remain authoritative across every interface.
- The 6–12-hour commitment covers the first reviewable draft or consultation outcome after payment confirmation and a complete submitted brief.

## Decisions Needed Later

These product decisions remain intentionally deferred with D1:

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

- The production super-administrator has not been bootstrapped. Its identity remains recorded, but password provisioning is a separate deliberate operation.
- Production still runs the previous release with `/account`; the new root customer route and M0 branding require an explicitly authorized release before production smoke checks change to `/login` and `/`.

## Working Tree Handoff

- F1 and F2 remain preserved in prior history.
- Commit `2ecd1c2` adds the explicit-command Hostinger CI/CD and release flow.
- Commit `8643a1a` adds the polished management UI branding plan and synchronized documentation.
- Commit `937fec5` fixes the PHP 8.3 dependency resolution mismatch; GitHub CI run 2 passed.
- The local super-administrator identity is verified; its password is not stored in the repository.
- The session-owned development server was stopped.
- Remote `main` is released from source commit `12ed0c81d235`; the generated `deploy` branch and Hostinger deployment are at `eda1d21cc641`.
- The first production database, `.env`, SSH path, pre-migration backup, migrations, scheduler cron, and queue cron were created and verified during the authorized release window.
- Hostinger SMTP is active for `notifications@adzbyte.com`; the protected production `.env` holds the mailbox credential and all temporary credential files were removed after verification.
- No database seeders ran and no production super-administrator was created.
- The local M0 slice moves the customer panel to `/`, removes the Laravel placeholder and Filament promotional widget, and keeps `/admin` unchanged; two disposable visual-QA customers were removed after verification.
- The local M1.1 slice adds the first-party customer Home and removes the remaining stock customer account widget without claiming the still-planned purchase and order dashboard is complete. A requested verified local-only `customer` demo account exists at `demo@adzbyte.com`; its password is not stored in the repository.
