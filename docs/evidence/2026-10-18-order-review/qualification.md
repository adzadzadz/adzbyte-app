# O1.3 review qualification

O1.3a domain source `770606be1e8cf34ae143f44c1efca38430efd66c` passes
[CI 39099527223](https://github.com/adzadzadz/adzbyte-app/actions/runs/39099527223):
381 tests / 6,166 assertions on each supported engine, PHP 8.3.35,
SQLite/MySQL 8.0.46. Local supplementary PHP 8.5.11 had 6,167 assertions because
the competing-command test's successful branch originally had one additional
assertion; both branch outcomes passed. The adapter increment makes the counts
equal and additionally checks that acceptance cannot duplicate a proposal.

O1.3b local PHP 8.5.11 full suite: **397 tests / 6,374 assertions**.
Pint, strict Composer metadata, production build, documentation links, unique
OpenAPI operation identifiers and whitespace checks pass. Final implementation source `e27fefaa520e3e9407b3046f285fefe551b8dcb3` passes
[CI 39101761212](https://github.com/adzadzadz/adzbyte-app/actions/runs/39101761212):
**397 tests / 6,374 assertions independently on SQLite and MySQL 8.0.46,
PHP 8.3.35**. Both audits report zero findings; builds, formatting, metadata and
production caches pass. O1.3 and aggregate O1 are verified complete. Payment is
not enabled; O2 and downstream phases remain pending.

| Gate | Evidence |
|---|---|
| Review/domain/security | Fresh exact capabilities, immutable accepted history, clarification/reply/resubmission, proposal supersession and stale rejection, fresh acceptance/reapproval, captured-policy expiry, terminal rejection and atomic rollback. Global Gate overrides cannot bypass owner queries or capability checks. |
| Independent concurrency | Four separate processes exercise same-command retry, distinct Admin approval, expiry, and customer acceptance versus replacement proposal on each CI-selected database. Current locking reads and policy-before-order lock ordering prevent mixing stale revisions with fresh order state. |
| API/native transport | `OrderReviewAdaptersTest` plus independent Node `order-review-consumer.mjs`: actual encrypted database sessions, real CSRF/exact Origin, strict JSON/bodyless reads/256 KiB bounds, UUID/revision conflicts, owner/role denial, private-note text/count exclusion, bounded pages, explicit Resources and OpenAPI checks. Final native form proof covers missing acceptance, same-owner typed recovery with unchecked acceptance, optional blank fields and corrected resubmission over real HTTP. |
| Admin/customer UI | Fifteen actual Chrome checks through built Next 52148d3 to handoff/login/cart/checkout, Admin clarification/private notes, customer reply/correction, approval, stale material offer/refresh/keyboard acceptance/reapproval, settings stale-tab conflicts, read-only staff, expiry, rejection and foreign-owner 404. Expiry uses a scoped future test clock in the disposable CLI process; no month-long wall-clock claim is made. Roles expose the exact capabilities and an actually saved settings grant works independently of approval rights. Desktop/320px captures have no document overflow; no unexpected console/page errors. The deliberate foreign-owner 404 is tagged expected. See [browser results](browser-results.json). |
| Queue and mail | Real database queue sends generic customer/Admin account links to a credential-free loopback SMTP sink. Notes and UUID replays do not notify; normal duplicate jobs recover sent receipts. Actual refused SMTP connections exhaust the job's three attempts, retain failure history and require explicit operator recovery. Missing-dispatch recovery, suppressed revoked recipients and transaction rollback are covered. SMTP acceptance followed by a crash before receipt commit can duplicate email; delivery is at-least-once and never changes payment state. |
| Migration/recovery | Apply review/delivery migrations against populated predecessor orders; empty rollback/reapply retains accepted snapshots; populated reversal fails before DDL; FK/unique constraints retain audit/delivery receipts. Historical fixture creation selectively fakes only the new OrderEvent-created notification hook to emulate predecessor behavior, retaining all model guards. Upgrades do not broadcast old events. Only disposable test DBs are used. |
| Delivery/build | Local full suite, Pint, strict Composer metadata, production assets and whitespace checks; final supported-runtime CI passes at e27fefa, 397 / 6,374 each. Existing checkout/identity tests and transports remain part of the full suite. No new dependency. |
| Cleanup/boundary | Chrome, both loopback servers, temporary SQLite, synthetic passwords/API/session keys and private QA scripts/logs are removed. Existing application DBs, ignored Vite hot marker, frontend source/worktrees, secrets, provider accounts and production are unchanged. Source delivery is separate from deployment. |

Operator recovery after resolving the underlying transport issue:
`php artisan commerce:recover-order-notifications --retry-failed`.
Automatic recovery only redispatches pending deliveries after five minutes;
exhausted deliveries remain visible in delivery records and `failed_jobs`.
Retained failure counts and last-failure timestamps survive explicit recovery.

Next: O2 requires owner-configured PayMongo test credentials and actual sandbox proof;
the owner deferred those credentials. O3/S2 precise entitlements/refund access
and launch merchant/legal configuration remain later decisions, not completed
by the configurable starter defaults.

## CI setup correction

Initial adapter source 5c74a84 / CI 39101521962 failed the native HTTP view
read with HTTP 500 on a clean checkout: both CI jobs ran tests before production
Vite assets existed. Local qualification had built assets. The required native
HTTP consumer now asserts manifest availability explicitly. Both jobs install
and build assets before the suite; audits, formatting, full database suites and
production-cache checks remain required. No application behavior or audit gate
was bypassed. Corrected source e27fefa passes both jobs in CI 39101761212, closing this setup gap before O1 completion.
