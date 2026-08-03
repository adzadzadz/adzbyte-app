---
name: session-end
description: Close or hand off an adzbyte-app session when the user says “end session,” “wrap up,” “done for now,” “prepare for a fresh conversation,” or otherwise signals a pause. Verify relevant work, synchronize project status, organize session-owned changes into coherent local commits, and leave a precise next task and clean worktree without pushing unless requested.
---

# Session End

Leave the repository understandable to a fresh agent and clean for the next session.

## 1. Inventory the Session

- Run `git status --short --branch`, `git diff --stat`, and `git diff --check`.
- Classify every changed path as session-owned, pre-existing but intentionally incorporated, unrelated user work, required generated output, or disposable transient output.
- Identify which changes predated the session and preserve unrelated user work.
- Review the actual diff before summarizing it.
- Check staged content for credentials, secrets, local environment values, and unrelated files.

## 2. Run Proportionate Verification

Choose gates from the files changed:

- Documentation or skill metadata only: `git diff --check`, link/path checks, and the skill validator for changed skills.
- PHP behavior: focused tests, then `composer test`.
- PHP formatting: `vendor/bin/pint --test` when PHP files changed.
- Frontend assets: `npm run build` when JS, CSS, or Vite inputs changed.
- Routes/configuration: inspect the relevant Artisan output in addition to tests.
- Database changes: test migrations against the test database and exercise rollback where practical.

Never claim a gate passed unless it ran. If a gate cannot run, record why.

## 3. Synchronize Documentation

Read `docs/STATUS.md` and the relevant plan sections.

- Update **Last updated** with the actual date and a concrete summary.
- Move finished work out of **In Progress**.
- Set **Up Next** to one executable task, not a vague phase.
- Record newly locked decisions and newly discovered blockers.
- Check roadmap boxes only for work that is implemented and verified.
- Update the product plan when a durable product or architecture decision changed.

Do not rewrite history or mark planned work complete because documentation exists.

## 4. Commit Systematically

- Treat a normal “end session” request as authorization to create local commits for verified session-owned work. Respect an explicit request not to commit.
- Build a commit plan from coherent, independently understandable scopes. Prefer dependency order: project workflow or configuration, framework/data foundation, application behavior with tests, then handoff documentation.
- Keep implementation and its tests together. Keep generated package assets with the dependency or framework change that requires them. Do not split files arbitrarily to manufacture more commits.
- Stage explicit paths with `git add -- <paths>`; do not use `git add .` or `git add -A` when unrelated changes may exist.
- Before each commit, inspect `git diff --cached`, run `git diff --cached --check`, and confirm the staged file list matches the intended scope.
- Use the repository's existing commit-message style. Make each message state one completed outcome.
- After each commit, inspect `git status --short`. If a hook changes files, review, verify, and commit those changes deliberately.
- Do not stash or discard work merely to produce an empty status. Never include unrelated user changes without clear authorization.
- Finish with no staged, unstaged, or untracked session-owned changes. If preserved unrelated work prevents a fully clean tree, report the exact paths and why they remain.

## 5. Git and Production Safety

- Report the commits created and the final staged, unstaged, and untracked state.
- Do not amend or rewrite existing commits unless explicitly requested.
- Do not push unless explicitly requested.
- Do not deploy, publish, rotate secrets, or mutate production systems as part of routine closeout.

## 6. Handoff

Report:

- Done
- Verification actually run
- Next task
- Commits created and final Git state
- Remaining blockers or “none”

The final handoff must be self-contained enough for a fresh conversation to resume from `docs/STATUS.md`.
