---
name: release-adzbyte-app
description: Release or deploy adzbyte-app from verified main to the Hostinger Business production site. Use only when the user's latest explicit instruction affirmatively commands the current production action with the standalone word "release" or "deploy", such as "release", "release adzbyte-app", "deploy", or "deploy the app". Do not use for CI/CD planning, release notes, deploy-branch discussion, questions, hypotheticals, quoted examples, negated instructions such as "do not deploy", or any request that lacks current production authorization.
---

# Release Adzbyte App

Release only the current verified `main` branch through the manual GitHub workflow.
Never treat this skill as standing or reusable production authorization.

## Authorization Gate

Before any commit, push, workflow dispatch, `deploy` branch mutation, Hostinger
action, production SSH command, migration, or cache change:

1. Inspect the user's latest explicit instruction.
2. Require an affirmative command to act now containing the standalone word
   `release` or `deploy`, case-insensitively.
3. Reject negation, questions, hypotheticals, planning, documentation requests,
   branch-name mentions, quoted text, and release-note requests.
4. If the gate fails, do not perform release preparation that mutates external
   state. Explain that the exact command `release` or `deploy` is required.

Authorization applies to one release attempt. A failed or cancelled attempt needs
a fresh `release` or `deploy` instruction before retrying any external mutation.
If preflight requires a clarification or scope decision, treat authorization as
paused and require the clarified reply to repeat `release` or `deploy` before
continuing with any external mutation.

## Preflight

1. Read `AGENTS.md`, all of `docs/STATUS.md`,
   `docs/deployment/hostinger-business.md`, `.github/workflows/ci.yml`, and
   `.github/workflows/deploy.yml`.
2. Inspect `git status --short --branch`, recent commits, remotes, and the
   difference between local `main` and `origin/main`.
3. Preserve unrelated user changes. If the worktree is dirty, the intended
   release changes are uncommitted, or local and remote `main` differ, stop and
   obtain an explicit scope decision. Never silently commit or omit changes.
4. Confirm the GitHub `production` environment, Actions content-write permission,
   Hostinger `deploy` branch integration, production `.env`, database backup
   procedure, queue cron, and a usable post-pull SSH or manual execution path.
5. Confirm no unresolved CI failure or known production blocker exists.

Do not update `deploy` if post-pull migrations and cache generation cannot be
completed and verified during the same release window.

## Release

1. Fetch remote state without rewriting local work.
2. Confirm the exact `origin/main` commit to release and report its short SHA.
3. Dispatch `.github/workflows/deploy.yml` on `main`, passing the same authorized
   command:

   ```bash
   gh workflow run deploy.yml --ref main -f command=release
   ```

   Use `command=deploy` when that is the user's command.
4. Identify the new workflow run and wait for it with `gh run watch --exit-status`.
5. Stop on any failed gate. Do not bypass verification or push `deploy` manually.
6. Confirm the remote `deploy` branch contains the authorized `main` commit and
   its compiled production assets.

## Finish Hostinger Deployment

1. Confirm Hostinger pulled the new `deploy` commit through hPanel, an available
   authenticated browser session, or an equivalent deployment record.
2. From the deployed project root, run the repository's post-pull procedure:

   ```bash
   bash scripts/hostinger-after-pull.sh
   ```

3. Never run seeders, create administrators, rotate secrets, roll back the
   database, or perform other production mutations unless separately authorized.
4. Verify `/up`, `/account/login`, and `/admin/login` over HTTPS.
5. Verify `php artisan migrate:status`, Laravel logs, and queue-cron processing.
6. If code promotion succeeded but Hostinger or post-pull verification failed,
   report the partial deployment immediately. Do not claim success and do not
   retry without a fresh `release` or `deploy` instruction.

## Report

Report the source and deploy commit SHAs, GitHub workflow result, Hostinger pull,
post-pull command result, migrations, health checks, queue status, and any manual
follow-up. State explicitly whether production deployment is verified.
