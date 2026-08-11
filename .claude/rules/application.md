---
paths:
  - 'src/*/Application/**/*.php'
---

# Application layer

> Abridgement of the `usecase-patterns` skill — **change one, change both.**

**Business logic lives in handlers.** _"Use cases are the single entry point for business logic"_ — a processor that decides anything has stolen the handler's job.

- The Application layer may import `Domain` and `SharedDomain` only. A `use` of `Infrastructure\` or `Presentation\` is blocked by a PreToolUse hook and fails `make deptrac`.
- The triad is `<Action>Command` (or `Query`) · `<Action>Handler` · `<Action>Result`, in `UseCase/{Command|Query}/<Area>/<UseCase>/`.
- `final readonly class …Handler implements CommandHandler` (or `QueryHandler`), with a single `__invoke(<Action>Command $c): <Action>Result`.
- **Constructor injects ports only** — `…Port` interfaces from `Application/Port/`. Never a Doctrine repository, an adapter, or an `EntityManagerInterface`.
- **Return a Result object, never a raw array.**
- **Dispatch domain events after the durable save.** A dispatch before it means a failed persistence leaves an event — and often a notification — describing something that never happened. Guard the idempotent path so a repeat call does not re-announce.
- Cross-module: a sibling's `Application\Port\` and `Application\Contract\` **only**. Never its `Domain\` or `Infrastructure\`. If the contract type you need does not exist, add it under the **owning** module's `Application/Contract/`.
- A best-effort side effect (notification, log) goes in a `try/catch` that logs and continues — it must not fail a use case that already committed.
- Ports are `<Capability>Port`, always interfaces, under `Port/Outbound/` (the module calls out) or `Port/Inbound/` (another module calls in). Do not create one for behaviour that never crosses a boundary.
- Buses: `CommandBusPort` for writes, `QueryBusPort` for reads, when one use case must invoke another.

Every handler ships with its unit test at the mirrored `tests/Unit/…` path, mocking every port.
