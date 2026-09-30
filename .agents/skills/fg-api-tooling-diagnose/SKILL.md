---
name: fg-api-tooling-diagnose
description: "Diagnose FireGuard API skill/agent discovery and model/effort availability in the current Codex session, distinguishing declared settings from observable runtime evidence."
---

# Diagnose the active session

Read `AGENTS.md`, `.codex/workflow.md` and `.codex/maintenance.md`. Keep this diagnosis
read-only. Use the session's existing tools; do not launch agents, nested Codex processes,
Serena, or modify settings to manufacture evidence. The current session may retain a
catalog from before a file change.

## Separate four observations

1. **Files:** read the native role definitions and shared configuration example. Run
   `python -B .codex/scripts/validate.py` and
   `python -B .github/scripts/check_agent_parity.py` when permitted. For an API-only
   checkout, use `--local-only` and retain `peer_definitions: not_checked` in the report.
   These checks describe files, not the active session.
2. **Discovery:** compare expected API roles/skills with the complete skill and custom-role
   catalogs actually exposed in this session. The initial session catalog or a current
   discovery tool is evidence; file existence and a role-like task name are not discovery.
   Mark incomplete or inaccessible catalogs **non observable**. Read peer instructions
   before inspecting peer definitions when the shared catalog is relevant.
3. **Availability:** use the current callable model catalog when it is exposed. If
   `model/list` is accessible, retain every response page in order until `nextCursor` is
   null. Check exact `model` values and `supportedReasoningEfforts[].reasoningEffort`.
   Request `includeHidden: true` for a complete model catalog when the interface supports
   it. A picker-only list or unknown query scope cannot prove that a model is absent.
   An interrupted/paginated partial list cannot prove availability or absence. Missing
   fields, model aliases, display labels and account-wide usage limits are not substitutes.
   See the [official model catalog contract](https://learn.chatgpt.com/docs/app-server#models).
4. **Execution:** compare declared model, effort, service tier and Fast setting only with
   actual execution/request metadata supplied by the active client. Distinguish capability
   from applied settings. A child agent's assertion or a TOML value is not execution proof.
   Do not dispatch agents just to fill this column; label missing metadata **non observable**.

## Evaluate and report evidence

The [evidence evaluator](../../../.codex/scripts/session_evidence.py) accepts sanitized
JSON on stdin and prints a report without contacting a service or writing files. Use it
when comparing supplied runtime snapshots; load its small input contract before building
the input. Preserve the source and completeness of each observation, and do not invent
an unavailable snapshot. This helper is not an autonomous CLI session probe.

Present files, discovery, availability and execution separately, with expected values,
observed values, evidence source and status (verified, mismatch, or non observable).
Report concrete missing roles or unsupported model/effort only when the evidence is
complete. Keep configured values unchanged and suggest the smallest next diagnostic step.
If fresh-session discovery is required but unavailable here, state that limitation; this
session's snapshot cannot verify a future reload.
