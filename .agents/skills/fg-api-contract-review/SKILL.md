---
name: fg-api-contract-review
description: "Review the API Platform contract — resource metadata, DTOs, serialization groups, status codes, filters, pagination, OpenAPI output, and exception mapping — for regressions and drift. Read-only."
---

# fg-api-contract-review

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/presentation.md` and affected
module contracts. Review without editing.

Identify breaking field removals/renames, narrowed types, required inputs, enum-literal drift and
status-code changes. Check Output DTO boundaries, serialization groups, bounded pagination,
filters, reference-catalog ownership, security and centralized RFC 7807 errors. Explain 403/404
information-disclosure consequences.

Generate or inspect fresh OpenAPI and `debug:router` when possible. Rank findings with breaking
changes first, cite exact evidence and consumer impact, then classify the final contract as stable,
additive or breaking. State tools or runtime checks that were unavailable.
