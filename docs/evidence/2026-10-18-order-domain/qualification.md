# O1.1 qualification

Verified source **55cbb94acff95e0f1017255036c21a2d4ea50e1f**, 2026-10-18.
[CI 39097303894](https://github.com/adzadzadz/adzbyte-app/actions/runs/39097303894)
passes **353 tests / 5,899 assertions independently on SQLite and MySQL 8.0.46,
PHP 8.3.35**. Metadata, Pint, Composer/npm audits (zero findings), production
assets and Laravel caches pass. Local PHP 8.5.11 matches 353 / 5,899; focused
order tests pass **22 / 219**, production build/format/metadata/whitespace pass.

`composer test -- --compact`; focused `php artisan test --compact --filter=
OrderDomainTest|OrderRulesTest|OrderMigrationTest|OrderConcurrencyTest` (quote the
filter as one shell argument); `vendor/bin/pint --test`; `composer validate
--strict`; `npm run build`. CI MySQL runs `phpunit.mysql.xml` against isolated
`adzbyte_test_` databases; workers assert loopback/disposable names.

All contract gates pass: immutable accepted policy/customer/product/price/terms
snapshots; exact huge-value tax and half-up calculations; freshness, availability,
terms/category conflicts and strict input; verified canonical ownership even
with staff Gate override; role/verification revocation; original/alias UUID
replay and mismatch rejection; atomic injected failure; monotonic transition
revisions; disabled payment and fulfillment actions; capability-backed versioned
settings. Four-process scenarios cover same UUID, different UUIDs, conflicting
inputs and different customers initializing one default policy. Current locking
reads protect MySQL repeatable-read snapshots after waiting for shared settings.

Clean/predecessor upgrade and empty reversal/reapply preserve existing identities
and carts. Populated policy and actual order reversal fail **before DDL**;
FK/unique constraints and full accepted-record readback pass. Populated commerce
requires a verified backup restore or forward repair; destructive reversal is
intentionally unavailable. No existing database was migrated. Test workers and
isolated databases clean themselves up; no QA server or private credential
remains. No release, vendor/payment operation or deployment occurred.

O1.1 adds no UI/REST/provider adapter, so browser/OpenAPI/provider gates are not
applicable to this card. O1.2 must verify its actual adapters/browser/HTTP consumer,
and O1.3 must verify Admin review/communication/expiry and material-change actions.
Aggregate O1 remains incomplete. Next: [O1.2 contract](../../plans/2026-10-18-checkout-contract.md).
