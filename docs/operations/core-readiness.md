# Core Operations and Readiness Runbook

This runbook covers the implemented pre-domain application only: identity,
authenticated panels, user/role administration, customer activation, the API
foundation, queues, and release infrastructure. Product, payment, upload,
fulfillment, and customer-data retention procedures must be added with those
vertical slices and their unresolved decisions.

## Automated signals

- `GET /up` boots Laravel and runs a database query. A database outage therefore
  makes the health endpoint fail instead of returning a misleading 200.
- `queue:monitor` runs every minute and logs a structured warning when the
  configured queue contains more than 25 jobs.
- `operations:queue-health` runs every five minutes and exits non-zero when a
  failed job exists or ready work has remained queued for more than five
  minutes. It logs counts and queue metadata, never job payloads.
- Terminal queue failures produce a structured critical log containing only the
  connection, queue, job identifier, and exception class.
- API idempotency records expire after 24 hours by default and are pruned daily.

On Hostinger, hPanel cron output and `storage/logs/laravel.log` are the currently
available monitoring channels. An external alert destination remains a later
notification decision; until then, check both after releases and at least once
per operating day.

## Failed or stale queue work

1. Run `php artisan operations:queue-health` and `php artisan queue:failed`.
2. Read the corresponding protected application log and identify the failed job
   class. Do not paste a serialized payload or customer data into a public tool.
3. Fix the underlying dependency, configuration, or application problem first.
4. Confirm the job is idempotent and that retrying cannot duplicate an email,
   payment, publication, or other side effect.
5. Retry one known job with `php artisan queue:retry ID`, then run the worker and
   verify the expected application state. Use `queue:retry all` only after every
   listed job has the same understood and safe failure mode.
6. Keep the failed record until verification is complete. Never use
   `queue:flush` as a monitoring or recovery shortcut.

Customer activation notifications use three attempts, 10/30-second backoff, a
45-second job timeout, and after-commit dispatch. The Hostinger worker timeout is
60 seconds and the database retry window is 90 seconds, preventing a frozen job
from being released while its original worker may still be running.

## Release or migration incident

1. Stop promotion and record the source and deploy commit identifiers.
2. Put the application into maintenance mode only if leaving traffic enabled can
   corrupt data or expose an incomplete workflow.
3. Preserve the Laravel log, cron output, failed-job list, and migration status.
4. If code is at fault and the schema is backward compatible, restore the prior
   deploy commit and run the normal post-pull cache steps.
5. If a migration or data change is at fault, take a second snapshot of the
   affected database before any repair. Restore the pre-migration backup or run
   a reviewed forward repair; never improvise `migrate:rollback` in production.
6. Re-run migration status, `/up`, authentication pages, queue health, and the
   affected workflow before ending maintenance mode.
7. Document the cause, affected interval, data impact, recovery, and a concrete
   prevention action without recording credentials or unnecessary personal data.

## Backup and restore baseline

- Take a database backup before every migration-bearing release and retain it
  outside the web root with access limited to the operator account.
- Record the timestamp, environment, pre-release commit, schema version, and
  encrypted storage location. Never commit a dump.
- A backup is not considered usable until a restore into an isolated disposable
  database succeeds and migration status plus representative record counts are
  checked.
- Do not define deletion or long-term retention periods for future orders,
  briefs, messages, uploads, payments, or sites until the pending privacy and
  product decisions are resolved.

Hostinger backup frequency, encrypted off-site storage, and the durable retention
window still require an infrastructure decision. This baseline therefore does
not close the roadmap's full backup/retention/privacy task.

## Credential exposure or account compromise

1. Revoke or rotate the affected credential at its source: mailbox, database,
   GitHub, SSH, application key, future PayMongo key, or Sanctum token.
2. Remove unauthorized sessions or tokens and inspect role assignments, access
   logs, application logs, deploy history, and relevant provider audit trails.
3. Update only the protected production environment, clear/rebuild Laravel
   configuration caches, and reload workers. Never commit the replacement.
4. Verify authentication, mail, database, queue, and release paths separately.
5. Assess whether personal or payment data was accessible and follow applicable
   notification and takedown obligations. Those obligations require a formal
   privacy/legal decision before product launch.

Changing `APP_KEY` is a last-resort incident action because it invalidates
sessions and can make encrypted application data unreadable. Preserve the old key
securely for controlled recovery whenever encrypted data may exist.

## Pre-domain audit boundary

The current audit verified that anonymous web access is limited to framework
health and authentication-bootstrap routes; customer and administrator panels
enforce role entry; user and role management use Laravel policies; the customer
API derives identity from Sanctum rather than a supplied ID; integration and
webhook route files expose no behavior before their authentication and signature
boundaries exist; normal pushes cannot update `deploy`; and production secrets
remain outside the repository.

The remaining roadmap is intentionally not implemented by this audit:

| Remaining area | Concrete dependency |
|---|---|
| D1 records, D2 states, D3 record policies | Confirmed product entitlements and the order/payment/collaboration data model |
| Customer and operational management screens | Those records, transitions, SLA interpretation, uploads, and notification behavior |
| Customer API beyond identity | The same domain models plus per-action ownership policies and request rules |
| Restricted Next.js integration | Product/catalog contract and checkout behavior; a token with no permitted endpoint has no useful capability |
| PayMongo | Enabled methods, refund policy, provider credentials, and payment/order records |
| Collaboration and uploads | Questionnaire, attachment, moderation, retention, operating-hour, and correction decisions |
| Hostinger automation | Confirmed Agency API/plan capability, domain strategy, credential delivery, and lifecycle rules |
| Full production audits and end-to-end acceptance | Implemented product, payment, upload, queue, and fulfillment vertical slices to audit |
| Retention, privacy, takedown, and external alerting | Formal business/legal retention choices and an approved notification destination |

These are product or external decisions, not missing generic framework work.
