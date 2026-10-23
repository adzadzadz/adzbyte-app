# Filament UX improvement review — 2026-10-24

The owner approved iterative implementation after the read-only application
review. Both panels retain Filament 5, Livewire and Laravel services/policies.
Implementation revision: `229b296dc1872d8116676ca88935f8f4530cc204`, pushed to
`codex/windows-local-development-20261024` in
[draft PR #1](https://github.com/adzadzadz/adzbyte-app/pull/1).
The abandoned React prototype dependencies are removed from the local install;
`package.json` and its lockfile contain no React, router, query, icon, React Vite
plugin, Vitest, jsdom or Testing Library dependencies. Those manifest additions
were uncommitted, so removal restores the existing tracked manifests.

## Delivered batches

| Batch | User-visible result | Review outcome |
|---|---|---|
| 1 — navigation and queues | Livewire SPA navigation within the panels; complete waiting/unread lists; staff status, assignment, customer, priority and type filters; filter-preserving return links; loading, offline and result feedback | Filtered empty states and a selected unread thread's close control corrected after independent review |
| 2 — contextual work and conversations | Customer-scoped assignment drawer; work create/edit drawers; prefilled milestone row action; compact conversation details, bounded message pane, inline composer, older/newer/latest navigation, canonical links and explicit refresh | Draft ownership, history changes, stale revision recovery and command-specific idempotency keys corrected and covered by regressions |
| 3 — account and commerce clarity | Activation rules/reveal/errors and generic expired-link recovery; clearer registration; honest commerce preview; consistent purchase terminology; recognizable customer/service/amount fields and formatted currency; product terms drawer and role helper | Reviewed with the complete integrated change; no unresolved material source findings |

### Original review closure

| Finding | Closure |
|---|---|
| Activation requirements and expired links | Visible rules/reveal/associated errors and generic recovery |
| Waiting count had no actionable destination | Complete filtered waiting lists from the attention section |
| Reply did not clear the waiting status | Saved-reply guidance explains the existing manual transition |
| Staff triage lacked useful filters | Composable scoped filters and actionable case ordering |
| Stale-write protection stranded drafts | Saved-version comparison, explicit review and retained draft/retry identity |
| Assignment interrupted customer setup | Customer-scoped drawer saves directly in the customer record |
| Milestone edits were detached from rows | Row action selects and prefills the correct milestone |
| Dense conversations and obscured reply context | Compact details, bounded thread, inline reply and separate private notes |
| Message pagination and location were ambiguous | Older/Newer/Latest controls, URL-backed page and canonical conversation link |
| Notification expectations were unclear | Explicit portal-only wording and Refresh conversation action |
| Support and purchase language conflicted | Orders/purchase terminology, preview explanations and portal return links |
| Commerce admin emphasized technical identifiers | Recognizable customer/service/total fields, currency, grouped details and terms drawer |
| Asynchronous actions lacked sufficient feedback | Loading/offline/result feedback, errors, draft protection and narrow-screen correction |

The dashboard loads four recent work records instead of twelve and no longer
fetches an unused conversation collection. The staff dashboard fetches five
cases instead of twelve. Context authorization no longer loads a complete work
history. These are verified reductions in work performed, not measured latency
claims. No production load test or before/after timing qualification is claimed.

SPA hover prefetch is disabled. Authentication and commerce navigation retain
full-page boundaries because those routes can have lifecycle effects. Panel
actions use Livewire requests, with existing shared actions, ownership queries,
policies, revisions and idempotency receipts retained. A separate React/API
frontend would add maintenance without addressing these interaction costs.

## Safety and recovery behavior

- Reply drafts remain bound to their original conversation and revision when
  selecting another case or using history. Returning restores the draft;
  discarding it is explicit. Navigation warns about an unsent draft.
- Stale actions show current saved details and require explicit review before
  retry. Milestone comparison includes identity and target date. Acknowledging
  one conflict rotates only that command's retry key; it preserves another
  operation's uncertain receipt and reply revision.
- Work assignment fixes customer identity from the authorized customer record;
  its retry UUID exists before submission. Customer filters cannot expand
  ownership. Staff-only notes and assignments remain restricted.
- Replies remain portal-only and do not automatically change case status. The
  UI explains both behaviors so customers do not resend a saved reply.
- Expired/invalid activation signatures still return HTTP 403, with a generic
  recovery view and private/no-store response. Viewing it sends no mail and
  discloses no customer identity.
- Commerce remains paused with its existing write restrictions. This effort
  does not qualify commerce launch, subscriptions, refunds or entitlements.

## Verification

| Gate | Evidence |
|---|---|
| Independent review | Approved after iterative fixes; no unresolved material findings in the final reviewed source |
| New UX regressions | `PortalUxTest` and `AccountCommerceUxTest`: 14 tests / 98 assertions passed |
| Previous full-run failure regressions | MySQL target-guard, account email lifecycle, checkout and portal UX selection: 37 tests / 403 assertions passed |
| Full local SQLite suite | Passed: 692 tests / 9,028 assertions, zero failures/errors, 487 seconds; `php vendor/bin/phpunit --no-progress --log-junit <temporary-report>` |
| Supported runtime / both databases | [CI 40004323532](https://github.com/adzadzadz/adzbyte-app/actions/runs/40004323532) passed at implementation `229b296`: 692 tests / 9,028 assertions independently on SQLite and MySQL 8.0.46, PHP 8.3.36 |
| PHP formatting | Full `vendor/bin/pint --test` passed |
| Metadata / audits | Composer validation passed; locked Composer and npm audits report zero vulnerabilities |
| Production assets | `npm run build` passed; no React application bundle; shared theme approximately 724 KB raw / 76 KB gzip |
| Source whitespace | `git diff --check` passed |
| CI supporting gates | Passed: clean installs, Composer validation, both audits, all 16 deployment-safety tests, Pint, production assets and Laravel production caches |
| Schema / API / provider | No migration, API contract or provider integration change; full existing regression suites apply |
| Production | No deployment or production data mutation performed |

Local verification uses official PHP 8.3.36 NTS on Windows with SQLite 3.53.4, and
Node 24.14.1. CI uses its configured PHP 8.3 and Node 24.22.1. The first full
Windows run exposed test harness assumptions: truncated `tempnam` prefixes,
undrained child-server pipes, native Windows cold-start duration, and a mail
fixture assuming a different application host. Test-only portability fixes keep
strict isolated database guards and all failure assertions. A temporary runtime
setting sends PHP startup errors to stderr for the guard subprocess tests.

The first final verification attempt had 680 passes and ten failures; those
failures were fixture/runtime assumptions and one outdated empty-state copy
assertion. Their focused rerun and the final complete rerun pass.

### Browser evidence

Actual built views were exercised in an isolated loopback server using a
disposable SQLite database and synthetic customer/staff accounts. No existing
application or production database was used, and no external mail was sent.

- Customer sign-in, dashboard attention links and filtered project navigation.
- Conversation expansion, older/newer pagination, inline reply submission and
  successful clear; switching conversations retains the original draft and
  offers a return action; navigation warns about unsent text.
- Customer view excludes synthetic private staff notes. Staff view retains
  distinct private-note and triage controls.
- Admin assignment drawer saves in the same customer detail screen. Milestone
  row action selects the correct milestone and prefills title/date. Escape
  closes the modal and returns focus to the originating row button.
- At 320px, the conversation pane is bounded to 288px; a shared body minimum
  width was corrected after browser inspection. Document scroll width equals
  its 305px client width with the Windows scrollbar present.
- Desktop checks at 1440px. Console warning/error log was empty during the
  exercised customer and admin journeys. This is not a formal screen-reader or browser
  compatibility certification.

The session-owned browser tab, viewport override, PHP QA server and both
synthetic browser databases were removed after verification. The existing user
development server was preserved. A clean `npm ci` initially hit a Windows
native-module file lock from that server; `npm install --ignore-scripts` repaired
the local install, and the production build passed. The SQLite CI job's fresh
`npm ci` and production build pass, supplying clean-install proof. Unrelated README/composer Windows setup edits and the
existing local deployment lock remain outside this commit.

## Explicit boundaries and follow-ups

Original material UI findings are addressed. Optional refinements remain:
invitation delivery telemetry/resend countdown, product image previews and
receipt-specific row actions. Native attachments, outbound support notifications,
background refresh and automatic case transitions require product/backend
scope and are not represented as delivered here. The existing commerce roadmap
retains its separate incomplete commercial/provider qualification gates.

All applicable implementation/source gates pass at `229b296`. This evidence-only
follow-up changes no tested implementation. Next action: owner review of draft
PR #1. No merge or deployment was performed; deployment requires its own explicit
owner command.
