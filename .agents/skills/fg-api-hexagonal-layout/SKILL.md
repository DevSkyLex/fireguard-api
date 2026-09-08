---
name: fg-api-hexagonal-layout
description: "Where each kind of backend file goes and what it may import — the four-layer module tree, the deptrac dependency rules, the naming scheme, and the house code style (two-space indent, regions, PHPDoc). Use before creating any file under src/."
---

# fg-api-hexagonal-layout

Read `AGENTS.md`, `ARCHITECTURE.md`, `.codex/workflow.md` and the owning module's
`MODULE.md`. Apply the dependency direction exactly:

- Presentation → Application → Domain
- Infrastructure → Application because it implements outbound ports
- Domain → only Domain and SharedDomain

Use cases live under `Application/UseCase/{Command|Query}/<Area>/<Action>/` as a typed
message, Handler and Result. Ports live under `Application/Port/{Outbound,Inbound}`;
vendor and persistence types stay in Infrastructure. Presentation Resources, DTOs,
Processors and Providers translate transport concerns. Domain models contain invariants
and have no Doctrine or HTTP metadata.

Cross-module access uses only published Application ports and contracts. Namespaces mirror
folders. Follow project naming and two-space PHP formatting. Create only folders required
by the current slice. Prove the result with targeted tests, PHPStan, Deptrac, container
lint and module documentation updates.
