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

The default suite uses disposable SQLite. The MySQL configuration runs the same
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
