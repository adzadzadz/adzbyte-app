---
name: review-docs
description: Audit adzbyte-app documentation for contradictions, stale claims, ambiguous terminology, broken references, missing decisions, or drift from code. Use when reviewing docs, preparing implementation, or synchronizing the product plan, roadmap, status, README, and implementation state.
---

# Review Docs

## Source Precedence

Resolve conflicts in this order:

1. The user's latest explicit instruction
2. Locked decisions in the product and system plan
3. Verified current implementation for claims about what already exists
4. Implementation roadmap for sequencing
5. `docs/STATUS.md` for progress only
6. `README.md` as a summary

Use Git history to understand intent when two sources at the same level conflict. Do not assume the newest timestamp is correct when it changes product intent.

## Audit

Read all persistent project documentation in scope and compare:

- Application ownership and public/private boundaries
- Authentication, roles, permissions, policies, and ownership
- Workflow states and terminology
- REST route ownership, callers, and authentication modes
- Payment authority, webhook processing, and idempotency
- Brief, messaging, draft, approval, correction, and SLA rules
- Product entitlements and hosting lifecycle
- Technology/version claims and official references
- Roadmap completion versus implemented code
- Broken file links, headings, commands, and cross-references

Search for deprecated names and ambiguous uses of “complete,” “customer UI,” “Next management,” or payment redirects as proof.

## Classify Findings

- **Stale alignment:** a lower-precedence summary contradicts a locked decision.
- **Structural:** broken link, incomplete index, duplicate section, or misplaced content.
- **Implementation drift:** documentation claims code exists or is absent contrary to verified code.
- **Decision conflict:** resolving it changes product, security, data, or architecture intent.
- **Missing specification:** implementation depends on a rule that no source defines.

Fix stale alignment, structural issues, and factual implementation drift when the user requested documentation changes. Present decision conflicts and missing specifications for user choice.

## Finish

- Run `git diff --check`.
- Verify every changed local link and referenced file exists.
- Re-search deprecated or conflicting terminology.
- Update `docs/STATUS.md` only when project state or a durable decision changed.
- Summarize what was aligned and list unresolved decisions separately.

Do not change application code under this skill and do not mark planned features as implemented.
