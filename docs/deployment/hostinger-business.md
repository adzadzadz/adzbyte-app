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

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SANCTUM_STATEFUL_DOMAINS=adzbyte.com,app.adzbyte.com
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
cd /home/USER/domains/app.adzbyte.com/public_html && /usr/bin/php artisan queue:work database --stop-when-empty --tries=3 --timeout=90
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
