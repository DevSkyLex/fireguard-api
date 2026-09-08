---
name: fg-api-arch-review
description: "Review backend changes against the hexagonal Module Architecture Standard — layer direction, logic placement, ports, cross-module boundaries, dual-database wiring, MODULE.md currency. Read-only."
---

# fg-api-arch-review

Read `AGENTS.md`, `.codex/workflow.md`, `ARCHITECTURE.md`, matching local rules and
the affected `MODULE.md`. Review the requested scope without editing.

Check layer direction, business logic placement, handler dependencies on ports, cross-module
imports, auth/main entity-manager wiring, naming, endpoint completeness and documentation
currency. Deptrac cannot prove that a Processor contains no business branch or that a module
imports a sibling Domain class, so inspect those manually.

Use PHPStan, Deptrac and container/YAML lint when allowed and proportionate. Rank findings as
blocker, should-fix or nit, cite exact files/lines, explain impact and end with conforms or
changes required. State checks not run. Security, contract and test-depth findings may be routed
to their dedicated skills, but this review remains complete within its scope.
