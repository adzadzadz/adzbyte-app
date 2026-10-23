# Admin record management and retained deletion

The owner requested admin CRUD and recoverable deletion while keeping Filament.
They selected **Admin CRUD; preserve history**, then **Keep cart changes in
customer workflow**. This increment extends the three UX batches in draft
[PR #1](https://github.com/adzadzadz/adzbyte-app/pull/1). It does not deploy or
apply migrations to an existing database.

## Delivered behavior

Administration → Data management supplies a common entry point for 30 record
types. Active records are the default. Deleted and All records filters, visible
status badges, confirmed Delete/Restore actions, safe record details and the
latest 20 lifecycle events make retained records recoverable. The complete
lifecycle history remains stored. Existing product/category/user/role/order/
payment/package resources also expose the controls. Portal work, customer and
support details provide contextual Delete actions.

Create/Edit links lead to the relevant workflow only when the administrator has
its capability. Support staff can now create a request for a customer and edit
its subject/type/description in drawers. Existing work/milestone/customer/
catalog/role forms remain authoritative. There is no generic database editor.

| Records | Editing model |
| --- | --- |
| Customers/users, roles, projects/services, milestones, support cases/categories, products/categories | Existing authorized business forms, with missing support create/details forms added |
| Work updates, messages, notes, versioned prices/terms/policies and audit records | Read retained history; create through its business workflow; Delete/Restore visibility |
| Orders, revisions, lines, proposals and messages | Existing checkout/review workflow and immutable captured snapshots; Delete/Restore visibility |
| Payments, provider receipts, settlements, resolutions/controls, package checkouts/delivery history | Existing operational actions, read-only history and Delete/Restore visibility |
| Customer carts and checkout selections | Customer workflow, as explicitly selected by the owner |
| Credentials, sessions, queue internals, secret/provider payloads | Excluded from Data management |

## Data and authorization guarantees

Two additive metadata tables store deletion markers and immutable lifecycle
events. Original rows, relationships and business status values are retained.
An indexed marker identifies deletion separately from paid/completed/cancelled
business state. Restoring preserves the previous business state. Deleting an
order/payment does not cancel, refund or stop provider processing. Provider
verification, reconciliation, retries, captured prices and fulfillment continue
using retained records. Hidden settlement/delivery details are labelled as
hidden history rather than a reversal of confirmed payment or fulfillment.

Deleting an owning customer/project/order hides its owned descendants from
normal screens. Data management explains inherited deletion and links to the
owning record for recovery. Restoring a parent leaves individually deleted
children deleted. Classification and actor/assignee references do not cascade
visibility. A deleted category does not rewrite existing product assignments.

Only verified staff with `Manage:DataRecords`, or a super administrator, can use
these controls. Domain permissions still govern the destination forms. Deleted
identities lose browser and bearer access, password recovery and activation.
Deleted records cannot be edited through stale forms/actions. Self-deletion,
deleting the last active super administrator, deleting assigned/built-in roles,
and lower administrators deleting/restoring super administrators are rejected.
Assignment/deletion/demotion paths share transactional locks. Confirmation is
bound to record identity/version; retries do not create duplicate lifecycle
events. Concurrent stale actions cannot silently overwrite a newer transition.

The migration inserts the new permission without granting it to ordinary roles.
An empty metadata migration can reverse; reversal refuses to discard populated
deletion history. Production adoption needs its separately authorized migration
and release procedure.

## Verification

Independent read-only review approved the final scope with no unresolved
material findings, including parent recovery, destination authorization,
identity safety and financial presentation/processing separation.

Focused tests cover actual Filament Delete/Restore, stale/tampered confirmation,
all 30 allowlisted detail views, permission denials, parent recovery, real bearer
and signed activation denial, session termination, migration preservation and
reversal, and separate-process races for duplicate deletion, super-administrator
demotion/deletion and role assignment/deletion. Existing legacy migration
fixtures install only the required visibility metadata without changing their
original upgrade/rollback batch under test.

### Browser results

Actual production assets were exercised in the in-app browser against a
disposable loopback SQLite server with synthetic verified administrator and
customer identities. No existing database, real mail or provider was used.

- Staff request creation in a side drawer, customer search/selection, existing
  values prefilled for editing, required-field feedback, successful edit and
  same-page conversation refresh passed.
- Confirmed Delete removed that request from the active support list. The
  Deleted filter found it with its original subject, description, status and
  revision. Detail-header Restore returned it to Active; lifecycle history
  displayed both actors/timestamps/versions.
- Deleting the owning project hid its linked case from Active. The Deleted
  case detail explained inherited deletion and linked to the exact project.
  Restoring that project brought the case back to the normal support list.
- At 320×850, detail text wrapped and document scroll/client widths both
  measured 305px (Windows scrollbar included). Header Restore and its
  confirmation remained usable. At 1440×960, record fields and recovery links
  were readable. Escape closed the confirmation and returned focus to Restore.
- Customer sign-in showed the restored project, milestone and both support
  conversations. Direct customer access to admin Data management returned 403.
- No warnings/errors were recorded during the exercised admin flows. This is
  targeted workflow/layout proof, not a complete accessibility certification or
  cross-browser matrix.

[Mobile retained record](deleted-record-mobile.png) and
[desktop parent recovery](parent-recovery-desktop.png) are saved synthetic
browser captures. The temporary browser tab was closed, viewport reset, server
verified stopped and all seven owned fixture files removed. The existing user
development server/database were not changed.

### Source qualification

Source `9a467209b291c25551bba191c574b49713aa3ae7` (implementation `0585419` plus
the permission-contract test correction) passes
[CI 40013619074](https://github.com/adzadzadz/adzbyte-app/actions/runs/40013619074)
on 2026-10-24 in the owner's Asia/Taipei timezone:

| Gate | Result |
| --- | --- |
| SQLite / PHP 8.3.36 | **723 tests / 9,380 assertions**, zero failures; 124.16 seconds |
| MySQL 8.0.46 / PHP 8.3.36 | **723 tests / 9,380 assertions**, zero failures; 690.13 seconds |
| Migration and contention | Full suites include populated prior-schema upgrade, empty reversal/reapply, populated-history rollback rejection and all five independent-process visibility races on each engine |
| Composer validation, audits | Passed; PHP and frontend audits both report zero vulnerabilities |
| Formatting / deployment-safety tests | Full Pint passed; all 16 safety tests passed |
| Production assets / caches | Clean installs, both builds and Laravel production cache generation passed; Node 24.22.1, shared theme 724.56 KB / 75.95 KB gzip |
| Source delivery | Implementation and correction pushed to the approved branch; source readback matches `9a46720`; draft PR #1 updated, no merge |
| Independent / browser review | Approved, no unresolved material source findings; actual desktop/mobile workflows and customer denial passed as recorded above |
| Production | Not deployed; no migration applied to existing databases; no real mail, payment or provider change |

Commands are the repository CI workflow's full `composer test` (SQLite),
`php vendor/bin/phpunit --configuration phpunit.mysql.xml` (MySQL),
`vendor/bin/pint --test`, `composer validate --strict`,
`composer audit --locked --no-interaction`, `npm ci`,
`npm audit --audit-level=high`, `npm run build`, deployment unittest discovery
and `php artisan optimize`. Local focused verification/browser QA uses official
PHP 8.3.36 NTS, SQLite 3.53.4 and Node 24.14.1 on Windows. Local Pint, build and
`git diff --check` pass. There is no new external API/provider integration;
existing HTTP/OpenAPI, processing, replay and ownership regressions pass in
both full suites. The MySQL suite duration is test evidence, not a page-latency
benchmark.

Initial CI at `0585419` found one outdated expected custom-permission list in
`OrderReviewAdaptersTest` (722 passes / 1 failure). `9a46720` updates that
expectation to include `Manage:DataRecords`; its no-default-grants check is
preserved and the focused test passes (1 test / 4 assertions). No implementation
change was needed. The local full-run process was interrupted before it wrote
a result report; it is not counted as a passing full run. Both CI jobs supply
the required full-suite qualification on the supported runtime.

All applicable development/source gates pass at `9a46720`. A subsequent
evidence-only commit records these results and browser captures without changing
the tested implementation. Unrelated README/composer Windows setup edits and
the pre-existing local deployment lock remain outside this work. Next: owner
review of the combined draft PR; deployment remains explicitly on hold.

## Explicit remaining scope

There is no permanent purge or bulk deletion UI. History is intentionally
immutable, and financial state changes still require their operational actions.
Cart editing stays in customer checkout. Soft deletion retains original rows
and email uniqueness; it is not an anonymization or erasure feature.

The original UX review's optional image previews, invitation-delivery telemetry
and receipt-specific shortcuts remain outside these batches. Native attachment
uploads, support email notifications, automatic background conversation refresh
and automatic case-status transitions are not implemented. Commerce launch,
subscriptions, refunds and entitlements retain their separate roadmap gates.
Performance work reduces queried/rendered data, but no production before/after
latency measurement is claimed. Deployment, its migration and post-release
validation require a separate owner command; the owner explicitly said not to
deploy yet.
