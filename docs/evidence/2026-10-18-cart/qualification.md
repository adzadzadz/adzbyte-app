# C2.3 / aggregate C2 development qualification — 2026-10-18

All applicable C2 development gates pass. Deployment remains separate.

| Gate | Evidence |
|---|---|
| Supported backend / source | `0c3d606837f104b45762944b46d19ed1c5cde8d8`, [CI 39092045975](https://github.com/adzadzadz/adzbyte-app/actions/runs/39092045975): **292 tests / 5,200 assertions independently on SQLite and MySQL 8.0.46 / PHP 8.3.35** |
| Domain / forbidden paths | Cart/Handoff/AppCart/StorefrontCart tests cover exact quantity/money, availability/version review, durable category replacement/retry effects, proof/owner/role/scope isolation, session rotation, real CSRF and exact Origin, issuer/key/authority revocation and expiry/replay |
| Persistence / concurrency | Populated handoff migration reversal/reapply preserves carts/catalog/identity/receipts; four-process issuance and consumption races pass in both engines; only one consume succeeds; existing cart races remain green |
| Backend contract / delivery | Implemented OpenAPI app-session and narrow service surfaces, actual bearer mint HTTP test, existing independent Node HTTP cart consumer, Pint/metadata/audits/build/caches pass; source pushed/read back |
| Actual frontend / source | `5467e0d00394c77e0a159b432b0f960094296aed`, [CI 39092597589](https://github.com/adzadzadz/adzbyte-next/actions/runs/39092597589) green: **141 tests / 31 files**, type/lint/build/audit; Node 22 target, local Node 26.11.1 supplementary |
| Integrated Chrome / assets | Actual built Next + Laravel, local PHP 8.5.11/SQLite, synthetic records, 1440/320px: **16 checks** in [browser-results.json](browser-results.json). Full screenshots/results in [draft PR #6](https://github.com/adzadzadz/adzbyte-next/pull/6), docs/commerce/evidence/2026-10-18-cart |
| Customer replacement / recovery | Old service named with new selection only after commit; regular category retained. Deliberately lose committed replacement response, then retry same UUID: durable notice and quantity-one state preserved. Refresh/detail Add share the cart |
| Transfer / native full cart | Keyboard View Cart, fragment cleared, atomic session consume, fixed destination, pre-login full cart, changed-price confirmation, unavailable removal, expired/replayed grant recovery; no cart/item time expiry |
| Browser privacy / quality | Encrypted HttpOnly host-only cookie; browser requests and loaded bundles contain no service token/cookie key. Real HTTP missing-CSRF denied. No page/unexpected console errors; intentional 410/aborted transport errors classified. No document overflow after responsive layout settles |
| External provider | Not applicable: no payment/mail/provider operation introduced in C2 |
| Cleanup / scope | All synthetic DB/key/token/private scripts removed; both loopback servers stopped. Original frontend checkout/unpublished changes preserved. No real identity/catalog, existing database migration, production settings, release or deployment changed |

Next: C3.1 public registration, then C3.2 verified identity/cart ownership and
explicit login-merge conflict resolution. The C2 checklist item spanning merge
remains split: its C2 behavior is qualified here, while account merging is C3.
D02/D05–D09 remain undecided at their dependent purchase/support/launch gates.
