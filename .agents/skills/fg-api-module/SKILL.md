---
name: fg-api-module
description: "Scaffold a brand-new bounded context under src/Module — the four-layer skeleton, first use case, ports, wiring, Doctrine mapping, security rules, MODULE.md, and baseline tests."
---

# fg-api-module

Read `AGENTS.md`, `.codex/workflow.md`, `ARCHITECTURE.md` and a comparable module.
Confirm the request owns a real invariant, then choose auth or main and justify it.

Create only folders needed by the first vertical slice. Wire the module service file, Doctrine
Record mapping under the correct manager, explicit security rules and a new migration. Add the
first use case through ports, plus `MODULE.md` with its seven sections and baseline tests.

Run PHPStan, Deptrac, container/YAML lint, `debug:container` and the focused tests. Report the
module completion checklist honestly and name any remaining feature slices without fabricating
empty architecture.
