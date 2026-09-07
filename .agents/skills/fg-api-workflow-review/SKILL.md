---
name: fg-api-workflow-review
description: "Review GitHub Actions changes — triggers, permissions, secret exposure, pull_request_target risks, action pinning, caching, and deployment gating. Read-only."
---

# fg-api-workflow-review

Read `AGENTS.md`, `.codex/workflow.md` and the requested GitHub Actions/composite-action
scope. Review without editing.

Inspect `pull_request_target` first: never execute PR-head code with base-repository secrets or
interpolate untrusted PR fields into shell. Then check least-privilege permissions, secret flow,
third-party action SHA pinning, deployment gating, both auth/main migrations, cache keys, matrix
ordering and composite actions.

Rank findings critical to low with exact workflow lines and exploit/failure paths. State that
repository settings, environment protections and secret values are outside the tree unless they
were independently verified. Finish with a concrete safe/changes-required verdict.
