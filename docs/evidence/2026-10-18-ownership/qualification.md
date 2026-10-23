# C3.2 / C3 identity and B2 qualification

Verified 2026-10-18. Backend source `40ae2b78493473f15dce6fd9ab80722219bedb9e`:
[CI 39095546942](https://github.com/adzadzadz/adzbyte-app/actions/runs/39095546942),
**331 tests / 5,680 assertions independently on SQLite and MySQL 8.0.46, PHP 8.3.35**.
Both jobs pass; metadata, formatting, dependency audits (0 findings), production
assets and Laravel caches pass. Local PHP 8.5.11 suite and actual Node contract
consumers also pass. Independent four-process ownership races and populated
migration reversal/reapply/ambiguity guards run on both engines in CI.

Frontend source `52148d3eff3e92ea27696dd2aaebec711a8c287a`:
[CI 39096289880](https://github.com/adzadzadz/adzbyte-next/actions/runs/39096289880),
Node **22.23.3**, **144 tests / 31 files**, type/lint/build and audit (0 findings).
Local Node 26.11.1 full suite/type/lint/build also pass. Source is isolated in
`codex/public-catalog`, draft PR #6; no develop push/merge or deployment.

## Acceptance and proof

All applicable C3.2 gates passed. The [browser results](browser-results.json)
record **15 checks** against actual built Next 16.4.2, Laravel HTTP, database
sessions/cache/queue, and a credential-free loopback SMTP receiver. Real CSRF
was enabled (APP_ENV=local). Only synthetic catalog/customers and disposable
SQLite were used. Chrome checks at **1440 and 320px** cover:

- Next selection/handoff, native keyboard registration, real database queue ->
  SMTP receipt -> signed activation -> verified owned cart; qty one and ordinary
  category preservation. A stale storefront tab recovers the saved account cart.
- New anonymous selections after claim expose only a coarse account link;
  already-signed-in handoff shows an explicit restricted-category choice. Actual
  session preview JSON passes the independent OpenAPI schema checker. Native
  keyboard submission preserves other categories and correct exact subtotal.
- Real CSRF rejects unsigned attach (419). Logout uses a fresh server CSRF token.
  Login deduplicates a previously selected product; revised price requires
  explicit confirmation. Recovery email is received through SMTP, password is
  reset, and the preserved guest selection joins the saved cart afterward.
- New browser login restores four owned lines. Another verified customer gets
  no cart/404 and no owner contents. Original guest/service authority is revoked;
  tests cover staff overrides, unverified/roleless identities and receipt probing.
- Used handoff returns generic 410 without clearing the owned cart; fragment is
  cleared. Anonymous retirement response has no owner/target/hash. Cookie is
  HttpOnly/SameSite=Lax with rolling persistence; actual client bundles contain
  neither service token nor encryption secret.

No unexpected page/console errors or horizontal overflow in checked layouts.
The intentional replay produced the expected 410 console network error. A
Chrome full-page screenshot stitching artifact was replaced with a settled
mobile viewport capture; DOM/overflow checks passed. That storefront capture
occurs after the synthetic price revision (PHP218 new selections versus the
initial PHP193 screenshot). Screenshots and matching results are in frontend
`docs/commerce/evidence/2026-10-18-ownership`; the mobile merge was recaptured
with one DOM heading and a full-height viewport to avoid the stitching artifact.

Domain/API tests also cover exact UUID/fingerprint/choices retries, changed-state
rollback, query-scoped customer ownership independent of Gate, old proof denial,
real HTTP consumer compatibility, strict fields and hidden catalog content.
No new uploads/provider payments exist in this card, so those gates are not
applicable. Migration proof uses disposable databases only. Browser QA servers,
SMTP, private DB/state/key/token/mail/scripts are stopped and removed. Existing
DBs, `.env`, ignored Vite hot file, original frontend edits and production are
unchanged. No release occurred.

C3 identity and B2 are complete. O1 must separately implement and test verified
ownership before creating any payable order; no order or payment route exists
yet. D02/D05/D08 now have owner-authorized configurable defaults in the decision
register. Next: O1.1 order domain, then its full supported-engine gates before O1.2.
