---
name: fg-api-codex-challenge
description: "Obtain a bounded read-only second opinion on FireGuard api work when the user asks for a challenge or independent review."
---

# Read-only second opinion

Read `AGENTS.md` and `.codex/workflow.md`. Use only when the user requests a second opinion.
Do not launch recursive `codex exec` calls.

Select the actual read-only custom role from the current callable catalog. Read its native
definition in `.codex/agents/`: the role defines its model, reasoning effort, service tier
and Fast setting. Follow the availability checks in `.codex/workflow.md` before dispatch;
do not substitute models, lower effort or change the main session's settings. A generic
agent with a role-like task name does not load that role.

Give the independent reviewer bounded context: workspace, exact scope, authoritative docs,
relevant observations, allowed read-only checks and the evidence expected. Do not reveal
your binary verdict before the independent review. If native-role selection or the configured
model/effort is unavailable, disclose that limitation; a self-review is not an independent
review. Verify findings before reporting them. No further delegation.
