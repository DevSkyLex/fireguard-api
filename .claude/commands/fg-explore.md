---
description: Map an existing module before changing it — use cases, ports, records and which database, routes, wiring, tests, and the closest files to mirror. Read-only.
argument-hint: '<Module> [flow] — e.g. "Inspection" or "Intervention offline publication"'
---

Delegate to the **fg-module-explorer** subagent: $ARGUMENTS

Require a map, **not a file listing**. The output that helps is *"mirror `ArchiveFacilityHandler`, it has the shape you need including the idempotence guard"* — not a tree of 200 paths.

Nine sections, in order:

1. **Purpose and boundary** — what the module owns and what it deliberately does not, from `MODULE.md`.
2. **Which database** — `auth` or `main`, confirmed from `config/packages/doctrine.yaml` by locating the entity-manager block that maps the module's `Record` namespace. Never inferred from the module name; everything downstream depends on it.
3. **Use cases** — commands and queries by area, with handler dependencies, flagging which carry real logic.
4. **Ports and adapters** — each interface, its implementation, and the `config/modules` alias. **Flag any repository, processor, or provider missing an explicit `$entityManager`** — a latent wrong-database bug worth surfacing unasked.
5. **Persistence** — the `Record` classes, their tables, and which relationships cross module boundaries (or cannot, since the two databases cannot be joined).
6. **The API surface** — a table of route · method · operation constant · processor/provider · security, taken from `debug:router` rather than from attributes.
7. **Cross-module dependencies** — both directions. Only `Application\Port\` and `Application\Contract\` may cross; anything else is a violation.
8. **Tests** — where, what level, and **what is not covered**. A missing denial-path functional test is the finding a builder needs before touching an endpoint.
9. **The anchors** — three to five concrete files to mirror, each with one line on why.

It maps; it does not judge or build. Observations go to **fg-architecture-reviewer**, **fg-security-auditor**, or the matching builder by name.
