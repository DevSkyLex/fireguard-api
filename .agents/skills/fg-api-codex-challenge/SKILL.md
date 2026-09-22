---
name: fg-api-codex-challenge
description: "Obtain a bounded read-only second opinion on FireGuard api work when the user asks for a challenge or independent review."
---

# Read-only second opinion

Read `AGENTS.md` and `.codex/workflow.md`. Use only when the user requests a second opinion. Do not launch recursive
`codex exec` calls. If an independent subagent is available, resolve its category and effort
through `.codex/agent-profiles.toml` and the resolver described in `.codex/workflow.md`.
Use the current callable catalog and pass explicit parameters with bounded context;
do not guess model aliases. Give it the exact scope and authoritative docs and request evidence
without edits. Do not reveal your binary verdict before the independent review.
If delegation is unavailable, disclose that limitation; a self-review is not an
independent review. Verify findings before reporting them. No further delegation.
