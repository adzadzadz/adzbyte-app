# Adzbyte App Agent Instructions

Read `docs/STATUS.md` before resuming project work. It records the current state and the single next implementation task.

## Sources of Truth

1. The user's latest explicit instruction
2. `docs/plans/2026-08-04-experimental-launch-products.md` for product and system decisions
3. `docs/plans/2026-08-04-implementation-roadmap.md` for implementation order and acceptance gates
4. `docs/STATUS.md` for current progress, blockers, and the next task
5. `README.md` for the repository overview

Do not silently invent business rules when these sources are undecided or conflict. Surface the decision that is required and keep `docs/STATUS.md` current.

## Non-Negotiable Boundary

- `adzbyte-next` owns the anonymous campaign, product, purchase-call-to-action, and payment-return UI.
- `adzbyte-app` owns all authenticated customer and administrator management, REST APIs, payments, processing, fulfillment, and the system of record.
- Phase 1 management lives only in Filament at the authenticated root `/` customer panel and `/admin`.
- Do not add an anonymous storefront or customer-management UI to this repository.

## Local Skills

Read and follow the matching project skill in `.agents/skills/`:

- `session-start` when starting or resuming a work session
- `session-end` when wrapping up or preparing a handoff
- `task-request` for new functionality
- `task-issue` for bugs and regressions
- `implement-feature` before writing application code
- `review-docs` for documentation audits and alignment
- `release-adzbyte-app` only when the user's latest explicit instruction
  affirmatively commands `release` or `deploy`; never infer release authority
  from planning, questions, quoted text, branch names, or negated instructions

## Quality and Safety

- Keep Filament panels, REST controllers, jobs, and webhooks on shared application services and Laravel policies.
- Enforce customer ownership in queries and policies; a role alone never grants access to every customer's records.
- Treat payment redirects as informational. Only a verified, idempotently processed PayMongo webhook confirms payment.
- Add automated tests for new behavior and authorization boundaries.
- Run focused tests while developing and the full relevant verification gate before declaring a task complete.
- Preserve user changes and unrelated worktree changes.
- The project owner grants standing authorization to create scoped, verified
  commits and push them to `main`, including in future sessions. Do not ask for
  separate commit or `main`-push permission.
- This standing authorization does not authorize a release, deployment,
  publication, production mutation, migration, secret change, or data change;
  those still require the user's latest explicit instruction.
- Normal pull requests and pushes may run CI but must never update `deploy`.
  Production promotion is allowed only through the manual release workflow after
  the `release-adzbyte-app` authorization gate passes.
