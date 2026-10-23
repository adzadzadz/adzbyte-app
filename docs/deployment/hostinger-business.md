# Hostinger Business Deployment

The production release flow uses a machine-managed `deploy` branch:

```text
pull request -> main -> CI verification
explicit "release" or "deploy" -> manual release workflow -> deploy -> Hostinger
```

Developers work on feature branches and `main`. Do not commit to, merge into, or
rewrite `deploy` manually. Normal pushes never update it. The manual release
workflow creates and updates it only after an explicit `release` or `deploy`
directive and after the PHP tests, formatting checks, dependency audits, frontend
build, and Laravel production-cache check pass.

The `deploy` branch contains the reviewed source from `main` plus the compiled
`public/build` directory. Runtime secrets, `.env`, `vendor`, and `node_modules`
remain outside Git.

## 1. GitHub repository settings

1. Open **Settings -> Actions -> General -> Workflow permissions**.
2. Allow GitHub Actions to write repository contents so the promotion job can
   update `deploy`.
3. Create a `production` environment. Add a required reviewer if the repository's
   GitHub plan supports environment approvals.
4. Protect `main` and require the **Verify application** check before merging.
5. Do not require pull requests for `deploy`; its only writer is the promotion
   job's `GITHUB_TOKEN`.

Pushing the workflow to `main` runs CI only. The remote `deploy` branch is created
by the first explicitly authorized run of **Release to Hostinger**. Start that
workflow only through the `release-adzbyte-app` project skill or an equivalent
deliberate manual action that selects `release` or `deploy`.

## 2. Hostinger Git integration

1. In hPanel, open **Websites -> app.adzbyte.com -> Dashboard**.
2. Set the website PHP version to PHP 8.3.
3. Open **Advanced -> Git**, connect the `adzadzadz/adzbyte-app` repository, and
   select the `deploy` branch.
4. Use `public_html` as the repository root and enable automatic deployment.
5. Confirm that the first deployment log identifies the expected `deploy` commit.

The root `.htaccess` forwards requests into Laravel's `public` directory. Do not
put the production `.env` file in Git.

## 3. Production environment

Create the production `.env` through hPanel or SSH. At minimum, configure:

```dotenv
APP_NAME=Adzbyte
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.adzbyte.com

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_AFTER_COMMIT=true
DB_QUEUE_RETRY_AFTER=90
QUEUE_MONITOR_MAX_JOBS=25
QUEUE_MONITOR_STALE_AFTER_SECONDS=300
SANCTUM_STATEFUL_DOMAINS=adzbyte.com,app.adzbyte.com

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=notifications@adzbyte.com
MAIL_FROM_NAME="${APP_NAME}"
```

Generate `APP_KEY` once on the server and preserve it across every deployment:

```bash
php artisan key:generate --force
```

Changing the production application key later invalidates encrypted sessions and
can make encrypted application data unreadable.

## 4. First deployment and post-pull commands

From the deployed project root over SSH, run:

```bash
bash scripts/hostinger-after-pull.sh
```

This installs the exact locked production dependencies, applies outstanding
migrations, builds Laravel's production caches, and asks queue workers to reload.
It intentionally does not run database seeders or create the production super
administrator; those remain deliberate bootstrap operations.

Hostinger's Git integration pulls the branch, but its current documentation does
not guarantee application-specific migration, cache, or queue commands. Until an
SSH post-deploy step is added to GitHub Actions, run the script after each pull
that contains PHP dependency, migration, or configuration changes.

## 5. Cron jobs

Hostinger Business uses scheduled jobs instead of a persistent Supervisor-managed
worker. Configure these custom cron jobs with the real absolute project path:

```bash
cd /home/USER/domains/app.adzbyte.com/public_html && /usr/bin/php artisan schedule:run
```

```bash
cd /home/USER/domains/app.adzbyte.com/public_html && /usr/bin/php artisan queue:work database --stop-when-empty --tries=3 --backoff=10 --timeout=60 --max-time=50
```

Run both every minute. Verify their output in hPanel and confirm that a queued
customer activation notification is actually processed.

## 6. Release verification

For every first or changed deployment:

1. Confirm `https://app.adzbyte.com/up` returns HTTP 200.
2. Confirm `/login` and `/admin/login` load over HTTPS, and that `/` redirects unauthenticated visitors to `/login`.
3. Check `storage/logs/laravel.log` without exposing it publicly.
4. Confirm `php artisan migrate:status` has no pending migrations.
5. Confirm the queue cron drains the `jobs` table and failures appear in
   `failed_jobs`.
6. Keep a database backup before migrations. Rolling the branch back does not
   reverse a database migration.
7. Run `php artisan operations:queue-health`; investigate rather than deleting
   any failed job until its cause and safe retry behavior are understood.
8. Run `php artisan schedule:list` and confirm the idempotency prune, queue
   depth monitor, and queue health check are registered.

## 7. Production configuration checklist

Before every first deployment or infrastructure change, verify all of the
following without copying secret values into tickets, chat, logs, or Git:

- `APP_ENV=production`, `APP_DEBUG=false`, the preserved `APP_KEY`, and the
  canonical HTTPS `APP_URL` are present.
- The root rewrite forwards requests exclusively into Laravel's `public`
  directory; direct requests for `.env`, storage, source, database dumps, SSH
  keys, and Composer metadata are rejected and rechecked after hosting changes.
- Database, SMTP, and any provider credentials exist only in the protected
  server environment. The production `.env` remains mode `600`.
- Session cookies are secure, HTTP-only, SameSite Lax, and encrypted; the
  database session, cache, and queue tables are migrated.
- `CACHE_STORE=database` remains enabled while API idempotency uses distributed
  cache locks. Any future cache-store change must retain atomic-lock support.
- Queue work dispatches after commit, worker timeout is lower than
  `DB_QUEUE_RETRY_AFTER`, the worker has bounded attempts/backoff, and both cron
  jobs still run every minute.
- Daily warning-level application logs are writable and not publicly exposed;
  hPanel cron output is checked for non-zero health-command exits.
- `SANCTUM_STATEFUL_DOMAINS` contains only confirmed first-party origins. No
  service token or provider secret is shared with browser code.
- A fresh pre-migration database backup exists and its location is outside the
  web root. A branch rollback is never treated as a database rollback.
- `/up`, `/login`, `/admin/login`, migration status, queue health, and the
  current release commit are verified after promotion.

The incident, queue-recovery, backup, and credential-rotation sequence is in the
[core operations runbook](../operations/core-readiness.md).
