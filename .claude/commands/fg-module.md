---
description: Scaffold a brand-new bounded context under src/<Module> — the four-layer skeleton, first use case, ports, wiring, Doctrine mapping, security rules, MODULE.md, and baseline tests.
argument-hint: '<Module> <purpose> — e.g. "Reporting scheduled compliance exports on the main database"'
---

Delegate to the **fg-module-builder** subagent: $ARGUMENTS

Require it to:

1. Read `ARCHITECTURE.md` end to end, then pick and read a **reference module of comparable shape** (`src/Facility/` for a full business module).
2. **Decide which database owns it — `auth` or `main` — and justify it.** That single decision drives the Doctrine mapping, every repository's wiring, and every migration afterwards.
3. Confirm it is a real bounded context. A grouping with no invariant of its own belongs inside an existing module.
4. Emit **only** the folders the first slice needs. `ARCHITECTURE.md` marks `Contract/`, `Service/`, `Cache/`, `Console/`, `DataFixtures/`, `EventSubscriber/`, `Mapper/` and the context folders optional — no speculative empty directories.
5. Wire **all four** files: `config/modules/<module>.yaml` (resource block, port aliases, explicit `$entityManager` on every repository, processor, and provider), `config/packages/doctrine.yaml` (the mapping under the correct entity manager), `config/packages/security.yaml` (access rules — a new module denies explicitly, not by omission), and the migration through **fg-migration-builder**.
6. Write `MODULE.md` with the seven required sections, mirroring a sibling.
7. Report the Module Completion Checklist **item by item**, with an honest tick or a reason.
8. Run `make cs-fix phpstan deptrac lint`, `debug:container <Module>`, and the baseline tests.

It emits skeletons with real wiring, not finished features. The slices belong to **fg-usecase-builder**, **fg-endpoint-builder**, **fg-port-builder**, **fg-domain-builder**, and **fg-test-writer** — require it to name each handoff.
