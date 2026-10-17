# Development Handoff

## Current checkpoint

| Field | State |
|---|---|
| Updated | 2026-10-18 |
| Priority | Backend, REST API, and management tools first |
| Active card | C1.2 — shared catalog actions and policies; unstarted |
| Implementation | C1.1 and dependency remediation pass PHP 8.3 CI; no catalog routes/admin screens yet |
| Decision gate | Fixed launch-catalog gate superseded; D01 resolved, D03 basic controls specified, D02 deferred to checkout/sales; see [register](decisions/commerce-development.md) |
| Next action | Specify/test ProductPolicy capabilities and shared actor-authorized transaction actions for content/lifecycle and price/terms; allocate revisions under parent locks and add actor/time audit records before panel/API exposure |
| Plan | [Backend task cards](plans/2026-10-17-backend-development.md) and [master roadmap](plans/2026-10-05-implementation-roadmap.md) |
| Last recorded runtime tests | 109 passed / 416 assertions on CI PHP 8.3.35 and local PHP 8.5.11; focused catalog: 38 / 71 |
| Production | Older foundation release recorded in STATUS; not rechecked, released, or changed by development/maintenance |

## Resume procedure

1. Read STATUS and this checkpoint, then the active task card and its decision
   entries. Read affected product-plan sections before implementing. Project
   workflow/authorization guidance remains in the canonical workspace framework.
2. Inspect `pwd`, `git status --short --branch`, `git log -5 --oneline`, and
   `git diff --stat`. Read existing diffs before touching changed files. Check
   staged and untracked work too; the checkpoint cannot override real Git state.
3. Confirm each task dependency and decision is resolved. Preserve unrelated
   work. An incomplete or unverified change remains partial; do not check the
   master roadmap merely because files or a commit exist.
4. Confirm the local environment before running mutating commands: PHP 8.3
   compatibility, installed dependencies, protected local configuration, test
   database isolation, and required test-mode provider access. Do not expose
   `.env` values, reset a valuable database, or run production commands.
5. Record active acceptance cases and the first concrete edit/test in this
   checkpoint. Continue that card instead of restarting a completed phase.

## Checkpoint fields for implementation sessions

Replace the current checkpoint with concrete values as work proceeds:

| Field | What to record |
|---|---|
| Active task / state | Task ID; planned, in progress, partial, blocked, or verified complete |
| Scope and acceptance | Expected result, cases/test names, and decision references |
| Finished / unfinished | Verified behavior versus remaining work, including failures |
| Paths | Session-owned edits, staged/untracked files, and unrelated pre-existing changes |
| Verification | Exact command, environment, result/count, date, and evidence location; distinguish not run from failed |
| Persistence | Migration names/status, test DB used, reversal/reapply result, compatibility or recovery constraints |
| Runtime | Any local process started and whether stopped; temporary fixtures/accounts and cleanup state |
| Git | Branch and commit(s), dirty state, push result, CI URL/result or not checked |
| Deployment | Source/deploy commits if relevant; explicit statement whether release/production changed |
| Next action | One executable command/edit or the exact unanswered decision that blocks it |

Update after a completed card, before switching tasks, before a risky operation,
and before stopping. For abrupt interruption, reconstruct missing evidence from
the checkout, tests, CI, and provider test state before continuing. A missing
result is unknown, not success. Never repeat a payment, notification, migration,
or provisioning operation just because the previous outcome was not recorded;
reconcile its identifiers/state first.

Keep a concise dated session entry below. Store durable business decisions in
the product plan and implementation progress in the master roadmap; avoid
copying their full contents into the session log.

## Session log

### 2026-10-17 — Backend development planning

- User confirmed backend, REST APIs, and user/product administration as the
  first target and requested documentation sufficient for interrupted work.
- Starting checkout: clean `main` at `e08f6b0`, matching locally recorded
  `origin/main`; no live remote/production verification used for the baseline.
- Added backend task cards, decision gates, and this continuation checkpoint;
  aligned the product plan, roadmap, first-release plan, README, and STATUS.
- Application code, databases, credentials, runtime processes, and production
  were not changed. No runtime tests were rerun for documentation-only work.
- Planning checks passed: 44 local Markdown links across 9 documents, task
  sequence and decision-reference checks, consistency review, and
  `git diff --check`. Runtime checks were not needed for these document edits.
- The owner explicitly authorized committing and pushing these documents to
  `adzadzadz/adzbyte-app` on `main` after the initial automatic approval block.
  Delivery locator: commit message `Document backend development and recovery plan`.
  This checkpoint precedes delivery; inspect Git for the actual commit and
  remote state when resuming. Live CI was not checked; no release ran.

### 2026-10-17 — Dynamic service-package clarification

- Planning commit `4f352be` was pushed to `origin/main` and its remote head was
  verified in the preceding session. This session started with a clean checkout.
- Owner clarified that no specific packages exist yet: packages must be dynamic
  and manageable through the backend. Superseded P0 / the launch-package gate;
  moved the next card to C1.1. No package names, prices, or content were invented.
- Updated the product plan, backend cards, roadmap, decision register, STATUS,
  first-release approach, README, and readiness dependency notes. Specified
  empty/draft behavior, administrator controls, and historical-version safety.
- This is a documentation change. No application code, migration, seed data,
  credential, runtime process, or production state was changed. Runtime tests
  and live CI were not rerun. Documentation checks passed: 45 local Markdown
  links across 12 documents, consistent C1.1 continuation references, stale-gate
  search/review, and `git diff --check`. Verify delivery state from Git when
  resuming; intended commit: `Plan dynamic service package management`.

### 2026-10-18 — C1.1 catalog domain and persistence

- Started from clean `main` at `a56b7fa`. Applied the confirmed dynamic-package
  requirement; no launch content or commercial amounts were invented.
- Wrote [schema/invariant specification](plans/2026-10-18-catalog-domain.md)
  and failing acceptance tests before implementation. Added `ProductStatus`,
  `Product`, `ProductPrice`, `ProductTerms`, immutable-version guards, three
  synthetic factories, and `2026_10_18_000000_create_catalog_tables`.
- Verified empty/draft behavior; publication/availability/archive transitions;
  protected identity; highest-version relations per product; append-only price/
  terms history; no-price/zero-price behavior; current-offer rechecks; strict
  integer money/revision validation; unique and foreign-key constraints.
- Commands: `php artisan test --compact --filter=CatalogDomainTest` passed
  38 tests / 71 assertions; `composer test -- --compact` passed 109 / 416;
  explicit-path Pint formatting, full `vendor/bin/pint --test`, and
  `composer validate --strict` passed. Runtime: local PHP 8.5.11, not PHP 8.3.
- Migration apply, rollback (catalog only), and reapply passed on an empty
  disposable SQLite file under `/private/tmp`; prior tables retained and no
  packages seeded. File removed. No development/production migrations ran;
  MySQL execution remains unverified. No runtime process or temporary account
  was created; no secrets/dependencies/assets were changed.
- No ProductPolicy, actor-authorized catalog actions/audit, admin resource,
  catalog endpoint, cache adapter, or purchase behavior is exposed yet. Existing
  API/auth/panel tests passed unchanged. Models are trusted primitives; bulk
  SQL/model-query writes bypass event guards. C1.2 must use authorized model
  writes under locks. `hasConfiguredOffer()` is configuration only, not payment
  authority, and must be revalidated inside future transaction services.
- Next: C1.2; C1.3/C1.4 and B1 remain incomplete. Intended source commit message:
  `Add dynamic catalog domain and versioned persistence`. Check actual Git and
  CI state when resuming; no release or production action was requested/run.
- Documentation continuity checks passed: 52 local Markdown paths across 13
  documents, C1.2 references in current checkpoint/status/plan, stale-state
  review, and whitespace checks. All session edits belong to this C1.1 slice;
  no unrelated changes were present at start or incorporated.

### 2026-10-18 — Source delivery and CI blocker

- Source `284dcbb796ac90dab7d8de39f1bd97f248452f4a` was pushed to `origin/main`;
  the remote head matched and checkout was clean before this evidence update.
- [CI run 39048141348](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048141348)
  failed at `composer audit --locked --no-interaction`: 11 advisories affecting
  five locked packages (`filament/filament`, `laravel/framework`,
  `league/commonmark`, `league/flysystem`, `symfony/http-foundation`).
- Verified `composer.json`, `composer.lock`, and CI workflow unchanged from
  the parent commit. CI installed dependencies under PHP 8.3 but skipped
  formatting, tests, frontend verification/build, and cache checks after the
  audit failure. Local PHP 8.5 results do not prove PHP 8.3 tests passed.
- Source implementation is locally verified; full CI qualification is blocked.
  No dependency updates, vulnerability suppressions, release, or production
  changes were performed. Ask the owner whether to address dependencies as a
  separate task before C1.2. Evidence-record commit message:
  `Record catalog CI dependency audit blocker`.

### 2026-10-18 — Authorized dependency remediation

- Owner authorized this follow-up with "Go". Started clean at `11a20b9`.
- Resolved only the five Composer-audited packages and required dependencies
  using `composer update filament/filament laravel/framework league/commonmark
  league/flysystem symfony/http-foundation --with-dependencies --minimal-changes
  --no-scripts --no-interaction --prefer-dist` after reviewing a dry run.
  Used a temporary Composer cache, then explicitly ran the standard post-autoload
  and post-update scripts under isolated testing environment settings.
- Updated Filament 5.7.5 → 5.10.2, Laravel 13.23.0 → 13.37.0, CommonMark 2.9.0 →
  2.10.3, Flysystem 3.35.2 → 3.36.0, HttpFoundation 7.4.15 → 7.4.21, plus necessary
  synchronized/transitive packages (16 updates, 2 additions total). PHP resolver
  platform remains 8.3.0; no root-manifest constraints changed.
- The frontend audit, skipped in the failed CI run, exposed four affected
  packages. Reviewed a dry run, then `npm audit fix --ignore-scripts` updated
  concurrently 9.2.4 → 9.2.5, nanoid 3.3.17 → 3.3.20, shell-quote 1.9.0 → 1.12.0,
  source-map-js 1.2.1 → 1.2.2. No force, overrides, or major upgrades used.
- Fresh audits: Composer 0 advisories / 0 abandoned packages; npm 0
  vulnerabilities. Reviewed maintainer/advisory references for
  [Filament MFA](https://github.com/advisories/GHSA-7m6h-rg42-m449),
  [CommonMark](https://github.com/advisories/GHSA-97jj-33gv-5xf9),
  [HttpFoundation](https://symfony.com/cve-2026-108082),
  [shell-quote](https://github.com/advisories/GHSA-pqg4-j6r4-53mv),
  [nanoid](https://github.com/advisories/GHSA-2v37-7h3g-55p8), and
  [source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q).
- Filament published assets are required generated output, not authored
  feature changes. New JS components and hashed fonts accompany the upgraded
  packages. Removed only seven tracked old fonts after confirming no repository
  references; all seven new CSS references resolve. Preserved upstream template
  strings exactly: three whitespace findings in code-editor/file-upload/
  rich-editor distribution JS were byte-checked against vendor copies. Scoped
  authored-file whitespace checks passed; no Git/audit checks were disabled.
- Existing full suite: 109 tests / 416 assertions on PHP 8.5.11; Pint, strict
  Composer validation, updated Vite build, and isolated Laravel optimize pass.
  Both login routes are present with generated caches. Cleared temporary
  config/route/event/view caches afterward. No development/production database,
  credentials, feature behavior, or release state changed; no process started.
- All changes are session-owned locks/generated assets plus status/handoff.
  Intended source commit: `Update vulnerable application dependencies`.
  Verify the new source's actual PHP 8.3 CI result before declaring the prior
  audit blocker cleared remotely. Then C1.2 remains the next product task.

### 2026-10-18 — Dependency CI qualification complete

- Maintenance source `a27f834bb05ee4d2ca5b3fd06da75f3b98a19475` was pushed to
  `origin/main`. [CI run 39048996685](https://github.com/adzadzadz/adzbyte-app/actions/runs/39048996685)
  passed all required steps on PHP 8.3.35: 109 tests / 416 assertions, Composer
  validation, Composer audit, Pint, clean npm install, npm audit, production
  asset build, and Laravel caches. Both audits reported zero findings.
- The original CI audit blocker is resolved for source development, and C1.1
  now has PHP 8.3 runtime proof. No root constraints, audit thresholds, ignores,
  application domain rules, or migrations changed in the maintenance task.
- No production deployment, release workflow, or production dependency update
  ran. Existing local database and credentials remain unchanged. Temporary test
  caches were cleared; no session process remains running.
- Current continuation task is C1.2, not C1.1 or another dependency audit.
  Admin catalog management, public API/caching, B1, and storefront integration
  are still unimplemented. This evidence-only follow-up is intended as
  `Record successful dependency CI qualification`; inspect Git for final delivery.
