---
name: session-start
description: Resume work on adzbyte-app when the user says “start session,” “let's begin,” “what's next,” “continue,” or “pick up where we left off.” Provide a light status and Git orientation without starting implementation or flooding context.
---

# Session Start

Orient quickly and stop for direction.

1. Read `AGENTS.md` and all of `docs/STATUS.md`.
2. Run `git status --short --branch` and `git log --oneline -5`.
3. Confirm that the file named under **Up Next** exists. Do not read every plan or spec yet.
4. Brief the user in about five lines:
   - Last documented update
   - Current stage and anything in progress
   - The single next task
   - Branch and clean/dirty state
   - Any blocker requiring a decision
5. Stop and wait for the user to choose or confirm the work.

Do not run tests, install dependencies, edit files, or pre-read the full product plan during orientation. The matching task workflow will load the required context after the user confirms direction.

Surface uncommitted changes without assuming they are disposable. If the user's request differs from **Up Next**, mention the difference and follow the user's current instruction.

Skip this skill when the conversation already ran it, the user explicitly asks to skip orientation, or the agent has a narrow delegated task.
