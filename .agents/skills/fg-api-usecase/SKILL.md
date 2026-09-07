---
name: fg-api-usecase
description: "Add a command or query use case to an existing module — the Command/Query, Handler, Result, ports, wiring, and the handler unit test."
---

# fg-api-usecase

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/application.md`,
`ARCHITECTURE.md` and the owning `MODULE.md`. Mirror the closest sibling and implement the
Command/Query, Handler and typed Result in the documented use-case folder.

Inject ports only. Persist before dispatching events and make retries idempotent when the flow
requires it. Cross modules through Application ports/contracts only. Add aliases and explicit
entity-manager wiring for any new persistence port.

Write the mirrored handler unit test with every port mocked, all failure paths, exact Result
fields, event assertions and a no-event replay case. Run the focused tests, PHPStan, Deptrac
and container lint. Update `MODULE.md` when the flow or contract changes.
