# Commerce lock release preflight — 2026-10-24

The owner requested production deployment after implementation and confirmed
proceeding with the completed UI/admin CRUD work while only ecommerce is locked.
The release includes six previously held commits across 144 files beyond main,
including an additive record-deletion metadata migration. The commerce lock
itself introduces no migration. At this preflight checkpoint no deployment
attempt or production mutation has occurred.

## Source boundary

- Main: `dcb429d3ad458912e91630edc8b161bdff6e980a` (confirmed by current PR metadata).
- Pending branch: `codex/windows-local-development-20261024`,
  `69bc0664190179389df6a89989b3fde848cc66ce`, draft PR #1.
- Commerce lock remains uncommitted on top of that branch.
- Existing README/Composer Windows setup edits and the local deployment lock
  predate this task and remain separate from the commerce change.
- Authorized scope: include the completed UI/admin CRUD branch and its migration
  alongside the ecommerce navigation lock. Other portal/CRUD features stay open.

## Fresh checks

- Composer locked audit, including development dependencies: no advisories,
  abandoned packages or filters. Strict Composer metadata validation passes.
- npm audit, including development dependencies: zero vulnerabilities. The
  sandboxed audit could not reach the registry; the network-enabled rerun passed.
- Focused commerce lock tests initially passed 3 tests / 163 assertions after adding
  `COMMERCE_FEATURES_LOCKED=false` to the MySQL test configuration as well as
  SQLite. Local MySQL execution is not claimed; its guarded bootstrap correctly
  requires an explicit isolated loopback test database. Exact-source CI must run
  both databases before release.
- Previously completed focused portal/account/navigation coverage: 12 tests /
  304 assertions, formatting and production build passed, with actual renderer
  browser click/keyboard tooltip verification.
- Final release preparation additionally locks the Package checkouts admin
  screen and verifies Data management and project management remain accessible
  while ecommerce is locked. Focused navigation/account/portal/CRUD coverage
  passes 23 tests / 458 assertions; full PHP formatting passes.
- The 16 Linux deployment-safety tests were attempted locally but did not pass
  on native Windows: the selected `bash` invokes WSL, which reports
  `Bash/Service/CreateInstance/E_ACCESSDENIED`, and required Unix runtime paths
  are unavailable. This is not clean deployment verification. The existing
  Linux CI gate must pass all 16 tests before production promotion.

## Official advisory review

The unchanged lock files contain Laravel 13.38.0, Filament 5.10.3, Livewire
4.4.8, Symfony HTTP Foundation 7.4.21, CommonMark 2.10.4 and Vite 8.2.4.

- [Laravel TokenGuard advisory](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6):
  the installed framework exceeds the 13.29.0 patched threshold.
- [Filament tooltip advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-9f9v-vm6w-3wx6)
  and [resource scope advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-6p72-cx74-63f5):
  the installed version exceeds patched 5.10.2 and 5.10.0 thresholds.
- [Livewire authorization advisory](https://github.com/livewire/livewire/security/advisories/GHSA-mhf7-xp6r-6qrc):
  the installed 4.4.8 is the patched release.
- [Vite development server advisory](https://github.com/vitejs/vite/security/advisories/GHSA-8g5g-mxqh-grgj):
  affects 6.4.4, not this Vite 8.2.4 build toolchain. Production serves compiled
  assets rather than a Vite development server.
- [PHP official notices](https://www.php.net/archive/2026.php): PHP 8.3.36 fixes
  the October 21 critical issues. The existing restricted deployment preflight
  checks the server runtime/backported build; it has not been rerun in this
  pending release. The October 23 session-upload-progress notice requires
  assessment of runtime serializer switching before promotion; this application
  must not be assumed exempt based solely on Composer/npm audits.

## Remaining release gate

After scope selection: prepare and review the exact source on main, complete
fresh SQLite/MySQL CI and production runtime/configuration preflight, dispatch
`deploy.yml` pinned to that SHA, and verify its protected backup/post-pull,
migrations, caches, worker restart, release record and HTTPS checks. No manual
deploy-branch push or hPanel redeploy is authorized as a replacement.
