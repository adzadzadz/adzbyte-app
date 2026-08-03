---
name: task-issue
description: Investigate adzbyte-app bugs, regressions, crashes, incorrect data, authorization failures, broken workflows, or unexpected existing behavior. Reproduce the claim, classify it, diagnose the root cause, and implement a verified fix only when the user requests a fix.
---

# Task Issue

Use the order: reproduce, classify, diagnose, fix when authorized, verify.

## 1. Reproduce Without Editing

- Read `AGENTS.md`, `docs/STATUS.md`, and the relevant product and implementation plan sections.
- Run `git status --short --branch` and inspect recent commits.
- Search and read the current implementation, tests, configuration, and relevant history.
- Reproduce using the narrowest safe method available.
- Capture concrete evidence: failing test output, response payload, logs, query result, or UI state.

Do not use or alter production customer data during diagnosis.

## 2. Classify

Choose one:

- Confirmed bug
- Expected behavior matching the source of truth
- Already fixed in current code
- Cannot reproduce
- Environment or configuration issue
- Partially confirmed but different from the report

If the user asked only for diagnosis, stop after explaining the evidence and root cause. Do not implement a fix without authorization.

## 3. Diagnose the Root Cause

- Identify the exact failing condition and why it occurs.
- Check whether a policy, ownership scope, workflow transition, retry, queue, cache, webhook, or configuration boundary is involved.
- Use history when it clarifies intent.
- Find related call sites and explain the blast radius.
- Determine which missing or incorrect test allowed the issue through.

Prefer correcting the underlying invariant over hiding the visible symptom.

## 4. Fix When Requested

Read `.agents/skills/implement-feature/SKILL.md` before editing application code.

- Add a regression test that fails for the confirmed defect when practical.
- Make the smallest maintainable fix at the correct layer.
- Avoid broad cleanup unrelated to the root cause.
- Preserve compatibility unless the user approved a breaking change.

Ask before proceeding only when the fix requires a material product decision, destructive migration, production mutation, or scope expansion.

## 5. Verify

- Re-run the exact reproduction.
- Run the regression test and relevant focused suite.
- Run the full applicable quality gate.
- Test authorization denial, duplicate delivery, or retry behavior when relevant.
- Update `docs/STATUS.md` and plan checkboxes only if tracked state changed.

Report the defect, root cause, fix, proof, and any residual risk. Do not commit, push, deploy, or mutate production unless explicitly requested.
