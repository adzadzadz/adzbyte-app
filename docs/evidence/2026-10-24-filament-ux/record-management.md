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

Final full-suite, browser and CI results are recorded below after execution.
