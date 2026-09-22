---
name: fg-api-module
description: "Scaffold a backend bounded context or maintain its MODULE.md contract from observed ownership, endpoints, flows and configuration."
---

# fg-api-module

Read `AGENTS.md`, `.codex/workflow.md`, `ARCHITECTURE.md` and a comparable module.
For documentation-only work, read [module-docs.md](references/module-docs.md), inspect the
assigned source/contracts, and update only the assigned module documents. Distinguish intended
contracts from observed behavior; flag contradictions rather than inventing a guarantee.
Do not scaffold code, run migrations or hand-edit generated OpenAPI for a documentation task.

For a new module, read the [hexagonal layout](../../../.codex/references/hexagonal-layout.md),
confirm the request owns a real invariant, then choose auth or main and justify it.

Create only folders needed by the first vertical slice. Wire the module service file, Doctrine
Record mapping under the correct manager and explicit security rules. Add a migration when the
slice changes schema or persisted data. Add the first use case through ports, plus `MODULE.md`
using [module-docs.md](references/module-docs.md) and baseline tests.

Run PHPStan, Deptrac, container/YAML lint, `debug:container` and the focused tests. Report the
module completion checklist honestly and name any remaining feature slices without fabricating
empty architecture.
