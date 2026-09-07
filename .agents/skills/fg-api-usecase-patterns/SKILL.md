---
name: fg-api-usecase-patterns
description: "The Command/Query/Handler/Result shape in fireguard-sso-api — the handler template, port-only injection, domain events dispatched after the durable save, cross-module contract types, and the handler unit test. Use when adding or changing anything under Application/UseCase/."
---

# fg-api-usecase-patterns

Read `AGENTS.md`, `ARCHITECTURE.md`, `.codex/rules/application.md` and the owning
`MODULE.md`. Mirror the closest sibling use case.

Create the message, Handler and typed Result together. Handlers inject Application ports,
validate or coordinate business rules, persist through a port, then dispatch Domain events.
Queries do not mutate. Commands that replay safely return the prior result and do not emit
duplicate events. Use a transaction port when several durable writes form one invariant.

Keep exceptions in Domain or SharedDomain where appropriate; Presentation maps them.
Cross-module collaboration uses an inbound/outbound port and Application contract, never
another module's Domain or Infrastructure class. Register new aliases and explicit entity
manager wiring in the owning module configuration.

Unit-test success, every failure, exact Result values, port arguments, event order and the
idempotent path. Validate with the focused tests, PHPStan, Deptrac and container lint.
