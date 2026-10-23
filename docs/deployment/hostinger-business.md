# Hostinger Business Deployment

The release workflow performs the whole deployment over restricted SSH:

```text
main / pull request -> CI (SQLite + MySQL, audits, build, deployment safety tests)
explicit release/deploy -> exact-source CI -> deploy branch + verified assets
  -> restricted SSH -> drain -> encrypted backup -> maintenance -> fast-forward
  -> no-dev dependencies -> migrations -> caches -> queue restart
  -> configuration/queue checks -> reopen traffic -> HTTPS/asset checks
```

Normal pushes run CI only. Start **Release to Hostinger** in GitHub Actions on
`main`, choose command `deploy` (scope `main`), and optionally pin the full reviewed
source SHA. No hPanel visit or manual Artisan command is needed after the one-time
setup below. The workflow rejects missing setup before publishing `deploy` and
rejects a main branch that moved during verification. The historical single-use
`qrph-package` scope is retired; its production integration is merged into main.

The machine-managed `deploy` branch contains the tested source and CI-built
`public/build`. Never push it manually. Secrets, database, uploads, `.env`, vendor
and node_modules remain outside Git. Releases briefly return 503 while changing
files; this shared-hosting design does not promise zero downtime.

## 1. One-time GitHub and restricted SSH setup

The `production` environment must restrict deployment branches to `main` and
contain:

| Kind | Name | Purpose |
| --- | --- | --- |
| Secret | `HOSTINGER_DEPLOY_SSH_KEY` | Dedicated Ed25519 key, never the operator's key |
| Secret | `HOSTINGER_KNOWN_HOSTS` | Host entry verified through existing trusted SSH |
| Variable | `HOSTINGER_SSH_AUTOMATION_READY` | Set to `true` only after all setup checks |

The workflow targets account `u463458285`, host `151.106.116.157`, port `65002`.
Its production job has the existing content-write permission to promote deploy.
PR/ordinary CI jobs do not receive deployment secrets. Keep main write access
limited; trusted source code executes as the hosting account during deployment.

Install the reviewed `scripts/deployment/ssh-entrypoint.sh` outside the web root
as `/home/u463458285/.local/share/adzbyte-app-deploy/ssh-entrypoint.sh`, mode 700,
with its directory mode 700. Add only the dedicated public key to authorized_keys
using `restrict,command="/home/u463458285/.local/share/adzbyte-app-deploy/ssh-entrypoint.sh"`.
Preserve existing keys. The new key permits only `preflight` and
`deploy <full-source-sha> <full-deploy-sha>`; shell, PTY and forwarding are denied.
The entrypoint fetches only the fixed public repository, validates deploy tip,
main/source ancestry and forward-only history, locks concurrent releases, and
extracts versioned release logic from that approved deploy object.

Configure both cron locks below, verify their commands, then create the private
`runtime-lock-enabled` setup marker. Run restricted-key `preflight` with pinned
host verification. A fresh production PHP build check requires 8.3.36+ on the
8.3 line, or Hostinger's specifically qualified 8.3.35 build dated Oct21+2026.
Fresh framework/dependency/runtime advisory review is still required before each
explicit release; keep that qualification current as PHP/security patches change.

## 2. Disable Hostinger's competing automatic deployment

Keep the existing website and domain configuration. In Hostinger Deployments,
turn **Auto-deployment off** for app.adzbyte.com after restricted SSH preflight
works. The GitHub workflow now owns fetch/checkout and all Laravel steps; having
two deployers would race. Do not click Hostinger Redeploy during a GitHub release.
No hosting-plan change or purchased service is required.

Set readiness true only after pinned-key preflight, main-only environment policy,
both actual locked cron commands and disabled Hostinger auto-pull are confirmed.
The first new pipeline release requires the separate explicit release/deploy gate.

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

## 4. Automated release and recovery

GitHub checks access before branch promotion, then calls the forced entrypoint.
The server waits up to150 seconds for active HTTP requests and cron work to drain
using one exclusive filesystem lock. New PHP requests return a no-store 503
before Composer loads, and new scheduled runs skip while the exclusive lock is
held. HTTP resources remain locked through later shutdown callbacks. The initial
cutover installs this HTTP gate without changing domain code, schema or dependencies;
subsequent releases use it to drain requests before any file replacement.

Before code changes, every release takes a single-transaction MySQL dump and
protected environment snapshot outside public_html. AES256-CBC/PBKDF2 encrypts
the dump with a fresh protected key; stream decryption must match its hash.
Plaintext dump/client credentials are removed even on failure. Backups and their
keys stay mode600 inside mode700 directories; GitHub receives no database, SMTP,
APP_KEY or backup decryption material. Record the encryption round-trip separately
from periodic isolated database restore rehearsals. Backups remain on this hosting
account; durable off-site retention is a separate operations task. No automatic
backup deletion policy is introduced.

After drain/backup the runner enters maintenance, fast-forwards only to the
expected deploy commit, preserves environment contents/key, and invokes:

```bash
COMPOSER_BINARY=/usr/local/bin/composer2 PHP_BINARY=/usr/bin/php bash scripts/hostinger-after-pull.sh
```

That installs locked no-dev dependencies, migrates forward, builds production
caches and restarts workers. No seed, account creation, secret rotation or
migration rollback runs. `.env` is restored to600; public checkout/install assets
use normal web-readable permissions. Config/schema/queue checks must pass before
traffic reopens. HTTP probes then run on-server and independently from GitHub,
including current compiled CSS/JS. The workflow reports success only afterward.

Protected `last-release.json` records source, deploy, previous SHA, phase, state
and backup location. A backup/drain failure leaves old code available. A failure
after mutation leaves maintenance enabled until an operator investigates; a
post-reopen HTTP failure is reported with new code retained. Never automatically
roll customer data back. Consult the core incident runbook and obtain a fresh
explicit release/deploy instruction before retrying a failed release.

## 5. Locked scheduler and mail-worker cron

Both Custom cron entries run every minute (`* * * * *`):

```bash
/usr/bin/flock --shared --nonblock /home/u463458285/domains/app.adzbyte.com/public_html/storage/framework/deployment.lock /usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan schedule:run
```

```bash
/usr/bin/flock --shared --nonblock /home/u463458285/domains/app.adzbyte.com/public_html/storage/framework/deployment.lock /usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan queue:work database --stop-when-empty --tries=3 --backoff=10 --timeout=60 --max-time=50
```

The shared lock is held for the entire command; the deployment's exclusive lock
waits for those processes and excludes new cron work until internal verification
finishes. Skipped work resumes at the next minute; queue jobs remain durable.
Use the exact absolute paths. Queue reservation retry-after remains180, above
worker timeout60; the configuration verifier conservatively requires >90.

The earlier absolute-Artisan cron repair and actual Gmail receipt proof remain
in [password-reset evidence](../evidence/2026-10-23-customer-portal/polish/password-reset-check.md).
The CI/CD cutover evidence records the new wrappers and actual scheduled output.

## 6. Release verification

For every first or changed deployment:

1. Confirm `https://app.adzbyte.com/up` returns HTTP 200.
2. Confirm `/login` and `/admin/login` load over HTTPS, and that `/` redirects unauthenticated visitors to `/login`.
3. Check `storage/logs/laravel-YYYY-MM-DD.log` (daily production channel) and any historical `laravel.log` without exposing them publicly.
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
  server environment. The production `.env` remains mode `600`. Hostinger automatic
  pulls can reset this mode to `644` (observed 2026-10-23); restore owner-only
  permissions with `chmod 600 .env` from the deployed root and verify the file
  contents/key are unchanged before closing the release.
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
