---
name: fg-api-arch-review
description: "Review backend changes against the hexagonal Module Architecture Standard — layer direction, logic placement, ports, cross-module boundaries, dual-database wiring, MODULE.md currency. Read-only."
---

# fg-api-arch-review

Read `AGENTS.md`, `.codex/workflow.md`, `ARCHITECTURE.md`, matching local rules and
the affected `MODULE.md`. Review the requested scope without editing.

Check layer direction, business logic placement, handler dependencies on ports, cross-module
imports, auth/main entity-manager wiring, naming, endpoint completeness and documentation
currency. `make deptrac` runs both `deptrac.yaml` for layers and `deptrac.modules.php` for
module boundaries. The latter allows only its exact legacy class-pair exceptions; passing
the original layer gate alone is insufficient. Inspect business decisions and public API
intent manually because a dependency graph cannot prove their correctness.

For query reviews, read [query-performance.md](references/query-performance.md). For dependency
injection, aliases, tags or entity-manager reviews, read [service-wiring.md](references/service-wiring.md).

Inspect supplied PHPStan, Deptrac and container/YAML lint results when available. Request fresh
evidence from the parent if a command would write cache or other state; do not run it inside
the read-only review. Rank findings as
blocker, should-fix or nit, cite exact files/lines, explain impact and end with conforms or
changes required. State checks not run. Security, contract and test-depth findings may be routed
to their dedicated skills, but this review remains complete within its scope.
