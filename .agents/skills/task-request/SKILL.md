---
name: task-request
description: Handle requests for new adzbyte-app functionality, including new Filament pages, Laravel services, models, migrations, API endpoints, integrations, workflows, or behavior. Verify current state, align the request with project plans, identify decisions, implement when authorized, and verify the result.
---

# Task Request

Use this flow for new functionality.

## 1. Verify Current State

- Read `AGENTS.md`, `docs/STATUS.md`, and the relevant sections of the product plan and implementation roadmap.
- Run `git status --short --branch` and inspect recent commits.
- Search the repository for the requested capability before assuming it is absent.
- Read existing related models, services, routes, policies, resources, migrations, and tests.

Classify the request as: already present, planned and straightforward, planned but cross-cutting, partially specified, out of scope, or blocked by a decision.

## 2. Define the Change

State compactly:

- Current evidence
- Relevant plan references
- Proposed implementation boundary
- Data, authorization, API, panel, queue, or integration effects
- Open decisions and risks
- Verification plan

Ask before implementation only when the request is planning-only, a missing choice materially changes the outcome, external authority is required, or the proposed action expands beyond the user's request. Otherwise proceed with reasonable, documented assumptions.

## 3. Implement

Read and follow `.agents/skills/implement-feature/SKILL.md` before changing application code.

- Implement the smallest complete vertical slice.
- Keep Filament, API, job, and webhook behavior on shared services and policies.
- Add authorization and ownership protections with the feature.
- Add or update automated tests with the implementation.
- Keep unrelated user changes intact.

## 4. Verify and Close

- Run focused tests during development and the relevant full gates before completion.
- Verify forbidden paths as well as successful behavior.
- Update roadmap checkboxes and `docs/STATUS.md` only after verification.
- Report the outcome, files changed, verification, and remaining work.

Do not commit, push, deploy, or change production state unless explicitly requested.
