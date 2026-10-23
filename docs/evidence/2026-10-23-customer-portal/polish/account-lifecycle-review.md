# Account lifecycle review — 2026-10-23

Owner reported no verification email after signing in and requested a complete
review of the affected account experience. Ecommerce remains paused. This review
covers both panels, their authentication emails, activation and profile identity
changes, plus full application regression checks.

## Live incident and mitigation

At 11:07:34 UTC the owner was unverified, with no queued or failed jobs. The
scheduler/worker cron repair remained healthy. Stock Filament login and the
verification prompt's mount do not send verification mail; the prompt nevertheless
says one was sent. Owner bootstrap via password reset does not emit registration
verification, so this is reproducible on first admin login.

The actual admin prompt Resend action was invoked. The cron worker delivered the
verification email into the owner's Gmail INBOX at **19:08 PHT** (11:08:04 UTC).
Only metadata/search-row evidence was read; no signed link/token was copied or
opened by the agent. The owner subsequently completed verification. A read-only
production check at 11:22:49 UTC confirms **owner verified, jobs zero, failures
zero**. No customer fixture, password, role, identity or email-address change was
made in production by this review.

## Confirmed defects and source correction

1. Both panels used a prompt that claimed mail was sent without dispatching it.
   The shared custom prompt now queues the first notification on entry, explicitly
   distinguishes queued/recent/busy/failed states, and shares a one-minute
   user/current-email cooldown across refreshes, sessions and panels. A cache
   lock prevents duplicate dispatch; a fresh user row lock checks verification
   and panel access before assigning the correct signed Filament URL and queuing
   on the same application database connection. Cooldown starts only after
   commit. Queue failure remains retryable; ancillary cache/logging cleanup
   cannot report a committed notification as unqueued.
2. Stock reset requests create/replace a shared reset token before checking panel
   access inside a callback. A customer-panel request for an admin-only account
   could invalidate its valid recovery token without sending replacement mail.
   Request and completion now scope the broker's user lookup to the panel's
   current roles before any token creation/consumption. Public request responses
   and cleared form state are generic. Signed URL, token expiry, password rules
   and rate limits remain enforced.

No schema, dependencies, permissions, commerce contracts or account records are
changed by the source fix. No automatic production bypass or account verification
is introduced.

## Verification

- Four initial regression cases reproduced both defects in both panel directions.
- **15 new tests / 221 assertions** pass with exit 0. They cover actual login to
  first verification prompt, refresh and shared resend cooldown, enqueue failure
  with immediate retry, lock contention/cleanup failure, verified/wrong-role
  denial, wrong-panel token preservation, and actual password change plus token
  consumption in disposable accounts.
- Independent queue processes deliver real serialized notifications into a
  credential-free loopback SMTP sink. Captured URLs are followed through signed
  verification/reset routes for each panel; wrong-user, modified and expired
  verification links are denied. Profile email remains unchanged until new-mailbox
  verification; the old-mailbox block prevents the pending change, and consumed
  email-change links cannot be reused. Existing registration/activation SMTP and
  ownership/retention tests also pass.
- Test worker defines the parent fixture's connection alias only after validating
  the disposable database, so serialized model restoration reaches that same
  isolated database rather than development or production.
- Full local PHP 8.5.11 SQLite: **677 tests / 8,853 assertions**, exit 0.
  Full formatting, Composer metadata, production asset build and whitespace pass.
  Fresh Composer audit including dev: zero advisories/abandoned packages; npm
  audit including build tooling: zero vulnerabilities. Dependency/runtime
  advisory review from this day's portal release remains unchanged; CI uses
  patched Node 24.22.1/PHP 8.3.36.
- Independent reviewer approves the revised implementation and reproduced
  **9 tests / 87 assertions**, exit 0. Source `7caae7db9d7314e2fa51f28b17a1b3559539776f`
  passes [CI 39945788709](https://github.com/adzadzadz/adzbyte-app/actions/runs/39945788709):
  **677 tests / 8,853 assertions per engine**, PHP 8.3.36 SQLite/MySQL, Node
  24.22.1, both jobs successful. The release workflow repeats all gates.

The live resend mitigation is verified. The automatic-send and scoped-reset
source corrections require a newly authorized release; production remains deploy
`230f2cd90d2b11ea12a03a00640fe35df66f260b` until that release completes.

Live authenticated browser review after owner verification: Dashboard, Customers,
Add customer form, Projects & services, Customer support and Profile all load
correctly. Cancel closes onboarding without creating a customer and returns from
Profile to Dashboard. No production form was submitted other than the requested
verification resend. Customer mutation/communication journeys remain covered by
the synthetic browser qualification and the full suite; no production fixtures
were introduced.
