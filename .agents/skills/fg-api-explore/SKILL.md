---
name: fg-api-explore
description: "Map an existing module before changing it — use cases, ports, records and which database, routes, wiring, tests, and the closest files to mirror. Read-only."
---

# fg-api-explore

Read `AGENTS.md`, `.codex/workflow.md`, `ARCHITECTURE.md` and the target `MODULE.md`.
This workflow is read-only. Produce a usable map rather than a directory listing:

1. purpose and boundary;
2. auth/main ownership proven from Doctrine mapping;
3. use cases and handler dependencies;
4. ports, adapters, aliases and explicit entity managers;
5. Records/tables and cross-module constraints;
6. routes, operations, processors/providers and security;
7. cross-module dependencies;
8. test coverage and denial gaps;
9. three to five concrete files worth mirroring.

Use `debug:router` when available and symbol references for code relationships. Report latent
wrong-manager or boundary defects, but do not modify code or present a file catalog as analysis.
