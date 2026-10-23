# Phase Verification and Completion

Owner direction, 2026-10-18: every phase must be fully verified before it is
called complete or dependent implementation advances. This applies to task
cards and aggregate phase/milestone exit gates.

## Completion rule

Before implementation, list the acceptance criteria, affected interfaces,
runtime/database targets, and applicable checks. Record each check as
**passed**, **failed**, **pending**, or **not applicable with a reason**.
Failed, skipped, unavailable, and unrun required checks cannot count as passed.
The implementation remains **partial / verification pending** until all
applicable gates pass at the delivered source revision. A phase also requires
its integrated exit behavior, not merely completion of its individual cards.

| Surface | Required proof when affected |
|---|---|
| Domain/service/policy | Positive behavior, invalid input, denied actors, ownership/capability boundaries, regression coverage, and full relevant suite |
| Persistence/transactions | Clean apply, upgrade from the preceding schema, uniqueness/FKs/history, meaningful rollback/reapply, and real independent-process contention/retry checks on each supported database engine |
| Supported runtime | Current CI on the supported PHP version; a different local version is supplementary evidence |
| API | Implemented OpenAPI contract, actual route/middleware/request/response checks, auth/error/ownership/rate-limit/idempotency cases, and consumer compatibility |
| Panel/UI/assets | Real workflow interaction, denial/error/retry states, relevant mobile/desktop layout and accessibility checks, production build, browser/console evidence where automation cannot prove the behavior |
| Provider/queue | Failure/retry/replay/mismatch tests and actual sandbox/test-mode transport, callback, receipt, and downstream state proof; mocks alone do not qualify an external integration |
| Cross-application phase | The actual consumer-to-backend journey, with environment and revisions of both applications; a backend test client alone does not qualify Next.js integration |
| Source delivery | Formatting, metadata validation, dependency audits, applicable builds/cache checks, successful CI for the implementation revision, pushed revision/readback, and clean or explicitly accounted-for Git state |
| Release/deployment | Separately authorized promotion, production-compatible migration/recovery evidence, then actual post-deployment workflows; source CI never proves production state |

Production mutation, release, payment, and migration authorization remain
separate. Use synthetic data and disposable databases for development proof.
Do not run a production action to turn an unverified claim into a completion
claim. A deliberate product-scope deferral must be explicit and documented;
testing difficulties alone do not waive a gate.

## Required evidence record

For each card/phase, STATUS and the handoff link to an evidence record containing:

- Acceptance criteria and actual results, including forbidden/failure paths.
- Exact commands/test names, counts, runtime and database versions, environment,
  source revision, CI link, and date.
- Integration/browser/provider evidence where applicable; reasons for checks
  classified not applicable.
- Migration/recovery results, temporary data/process cleanup, remaining gaps,
  and the single next action. Preserve historical results as historical.

Re-run checks affected by code, configuration, dependency, environment, or
contract changes. Reuse unchanged verified evidence with its revision and scope;
do not substitute old results for new behavior. Before moving to a dependent
card, reconcile every required gate and close its outstanding failures.

## Database regression gates

The native HTTP contract suite renders production Blade views and requires
`npm ci` and `npm run build` before `composer test`; both CI jobs build assets
before running tests. An explicit manifest preflight gives a setup error instead
of an opaque HTTP 500. The default suite uses disposable SQLite. The MySQL configuration runs the same
suite against a disposable `adzbyte_test_` database on a loopback test server:

```sh
TEST_MYSQL_DATABASE=adzbyte_test_ci \
TEST_MYSQL_HOST=127.0.0.1 \
TEST_MYSQL_USERNAME=root \
php vendor/bin/phpunit --configuration phpunit.mysql.xml
```

Supply `TEST_MYSQL_PASSWORD`, `TEST_MYSQL_PORT`, or `TEST_MYSQL_SOCKET` through
the environment when needed. These variables must refer only to a disposable
test server/account that can create/drop isolated `adzbyte_test_catalog_`
databases. The bootstrap rejects missing/non-test database names and remote
hosts, ignores application DB credentials and existing config caches, and never
uses the development/production database. PHPUnit refreshes the designated
test database; race/migration cases create and remove additional isolated ones.

CI requires both `Verify application` (SQLite plus audits/build/cache checks) and
`Verify MySQL behavior` (PHP 8.3/MySQL 8.0 full suite). The concurrency workers
use the selected engine; the MySQL job must not silently fall back to SQLite.
`CatalogMigrationTest` proves clean apply, audit/catalog rollback, preservation
of earlier tables, and reapply. The suite also protects the MySQL test-target
guard itself. This qualifies the tested engine/version; exact hosting-version
compatibility and post-release behavior remain release gates.

## Current catalog qualification

Verified source: `6697e81abf11530af10d9ff2f666561e0e2dd032`, 2026-10-18;
[CI run 39051237331](https://github.com/adzadzadz/adzbyte-app/actions/runs/39051237331).
PHP 8.3.35; MySQL image `mysql:8.0` at digest
`sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b`.
Local supplementary runtime: PHP 8.5.11 with SQLite.

| C1.1/C1.2 gate | Outcome/evidence |
|---|---|
| Behavior/validation/policies/history | Passed: full suite **158 tests / 703 assertions on each engine**, independently SQLite and MySQL on PHP 8.3.35; `CatalogDomainTest` and `CatalogActionsTest` cover the domain and forbidden paths |
| Concurrency/retry | Passed: `CatalogConcurrencyTest`, five cases with four independent workers and a common start barrier; each job uses its selected engine, unique price/terms revisions and single identical create/revision commits |
| Migration/recovery | Passed: `CatalogMigrationTest`, clean apply, populated C1.1 → C1.2 upgrade preserving product/price/terms records, audit/catalog reversal, prior user retention, and reapply on both engines |
| Test-target isolation | Passed: explicit per-case connection names and database assertions before migration; three bootstrap guard cases reject missing/non-test/remote targets; disposable case schemas/workers/files are cleaned up |
| Formatting/metadata/audits/build/cache | Passed: CI application job; Composer/npm audits 0 findings; local Pint, Composer validation, and whitespace checks also pass |
| New panel/browser workflow | Not applicable to these cards: no catalog screens/assets added; required in C1.3 |
| New public contract/consumer | Not applicable to these cards: no new endpoints or Next.js integration; existing API regressions pass in both suites; required in C1.4 and phase C1 exit |
| External provider | Not applicable: these cards introduce no provider operation |
| Delivery | Source pushed and CI-qualified; evidence-only follow-up recorded separately |
| Production/hosting | Separate authorized release gate; no deployment, existing database migration, or production mutation performed |

The initial MySQL run failed in the new test harness because copied connection
metadata retained the original connection name. Correcting it and asserting the
target before migrations resolved the failure. No application change was
required. C1.1/C1.2 now pass their applicable development gates; C1/B1 remain
incomplete until their admin, API, and integration requirements are verified.

## Administrator catalog qualification

C1.3 is verified complete at source `7d43b13d8161ed83b0abdde6438412de9440256e`:
[CI run 39053801945](https://github.com/adzadzadz/adzbyte-app/actions/runs/39053801945)
passes 183 tests / 1,018 assertions independently on SQLite and MySQL under
PHP 8.3.35, both audits (0 findings), formatting, metadata, build, and caches. The
[management contract](plans/2026-10-18-catalog-management.md) records scope,
acceptance, and saved browser artifacts. Local PHP 8.5.11: focused 25 / 315,
full 183 / 1,018; formatting, Composer validation, build, and route checks pass.
Real desktop/mobile interactions, validation feedback, denied access, history,
role controls, and keyboard focus checks pass. No migration/dependency/provider
or public-contract change is introduced. All applicable C1.3 development gates
pass; C1.4 is next. Deployment remains separate.

## Public catalog and real storefront qualification

C1.4/B1 backend source `9b4bf7b` passes [CI 39055675151](https://github.com/adzadzadz/adzbyte-app/actions/runs/39055675151):
206 tests / 1,300 assertions separately on SQLite and MySQL/PHP 8.3.35, HTTP/Node
contract proof, both audits, formatting, metadata, build and caches. Native JSON
entry counting fixes the MySQL empty-terms filter regression; pre-boot fixture
isolation rejects inherited non-test connection settings. No schema changed.

Aggregate C1 includes actual Next.js source `805c003` in [draft PR #6](https://github.com/adzadzadz/adzbyte-next/pull/6),
with [CI 39056186126](https://github.com/adzadzadz/adzbyte-next/actions/runs/39056186126) passing
129 tests / 29 files, type/lint/build and zero-finding dependency audit. Real
desktop/mobile browser workflows pass against the actual isolated Laravel HTTP
server, including outage/retry and committed catalog mutations. The [catalog
contract](plans/2026-10-18-public-catalog.md) records sources, artifacts, environment,
applicability and cleanup. This qualifies development source across both repos.
Frontend promotion and production deployment remain separately authorized; no
existing database or production catalog was changed. C2 onward remain incomplete
until their explicit business inputs and own verification gates pass.

## Category prerequisite qualification

Category source `4859e4e` passes [both database PHP 8.3.35 CI jobs](https://github.com/adzadzadz/adzbyte-app/actions/runs/39087894642):
221 tests / 1,447 assertions on each engine, including populated category
upgrade/reversal, four-process duplicate creates, domain/policy/admin and real
HTTP/OpenAPI compatibility. Formatting/metadata/audits/build/caches pass.
Actual category admin create/toggle/assignment, validation/history/denial and
1440/320px checks pass. Next source `f202534` passes [source CI](https://github.com/adzadzadz/adzbyte-next/actions/runs/39088192681),
131 tests/29 files, type/lint/audit/build and real category read/toggle propagation
at both browser widths without unexpected page/console errors. The [contract](plans/2026-10-18-category-cart-controls.md)
records artifacts, synthetic environment and cleanup. C2 enforcement is separate
and remains next; no existing database migration or deployment occurred.

## C2.1 cart domain qualification

Source `31274f3` passes [CI 39089160730](https://github.com/adzadzadz/adzbyte-app/actions/runs/39089160730):
243 tests / 1,671 assertions independently on SQLite/MySQL 8.0.46/PHP 8.3.35,
including actual process contention, populated cart upgrade/reversal, owner/proof
denials, persistent lines, category replacement/notice receipts, availability,
current-offer review and exact subtotals. Audits, metadata, formatting, production
assets and caches pass. MySQL's canonical JSON key order exposed a test-only
strict array-order comparison; the final assertion compares JSON object values.
The [contract](plans/2026-10-18-cart-service.md) records the correction, source,
applicability and cleanup. No new HTTP/UI/provider surface exists in C2.1; these
remain required for C2.2/C2.3. C2 aggregate remains incomplete. No existing
database or production changed.

## C2.2 restricted cart REST qualification

Source `8656e31` passes [CI 39090091899](https://github.com/adzadzadz/adzbyte-app/actions/runs/39090091899):
266 tests / 4,929 assertions independently on SQLite/MySQL 8.0.46/PHP 8.3.35,
both audits, formatting, metadata, production assets and caches. Positive and
forbidden real Bearer/proof cases, strict input/ownership/UUID/review/privacy,
600-request service and 120-request cart limits, credential issuance/revocation,
stateless first-party Origin and a real HTTP/Node/OpenAPI consumer pass. The
HTTP fixture uses the disposable database cache and proves a persistent 429
across actual HTTP requests. No new migration, dependency, provider or browser
UI; existing recovery/concurrency tests still pass. The [contract](plans/2026-10-18-storefront-cart-api.md)
records proof and cleanup. C2.3 actual controls/notification/transfer remain next.

## C2.3 and aggregate C2 qualification

All applicable development gates pass at backend `0c3d606` / frontend `5467e0d`.
The [evidence record](evidence/2026-10-18-cart/qualification.md) contains PHP 8.3.35
SQLite/MySQL 292 / 5,200 each, frontend 141 tests/31 files and both CI links,
16 actual built Next/Laravel desktop/320px checks, migration/concurrency/CSRF/
privacy/retry proof and cleanup. C3 remains next; deployment is separate.

## C3.1 public registration qualification

Source `f32bc36`, [CI 39093386908](https://github.com/adzadzadz/adzbyte-app/actions/runs/39093386908),
PHP 8.3.35: **302 tests / 5,331 assertions independently on SQLite/MySQL**,
audits/format/metadata/build/caches passed. [Contract/evidence](plans/2026-10-18-public-registration.md):
shared identity/abuse/real-CSRF/Origin/privilege/duplicate tests, four-process
normalized identity race, actual database worker -> credential-free loopback
SMTP receipt -> signed activation -> verified session, ten actual desktop/320px
browser checks with no errors/overflow. Existing activation/panel routes remain
qualified; no new schema or real mail/provider configuration. All private
fixtures/servers removed/stopped. C3.1 complete; C3.2/aggregate C3/B2 pending.
