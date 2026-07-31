---
description: Add a command or query use case to an existing module — the Command/Query, Handler, Result, ports, wiring, and the handler unit test.
argument-hint: '<Module> <command|query> <Action> — e.g. "Equipment command AssignToFacility"'
---

Delegate to the **fg-usecase-builder** subagent: $ARGUMENTS

Require it to:

1. Mirror the closest sibling use case in the same module before writing anything.
2. Emit the triad in `Application/UseCase/{Command|Query}/<Area>/<UseCase>/` — `<Action>Command`, `<Action>Handler`, `<Action>Result`.
3. Inject **ports only** in the constructor — never a Doctrine repository, an adapter, or an `EntityManagerInterface`. A hook blocks the import, and `make deptrac` fails on it.
4. Return a **Result object**, never an array.
5. Dispatch domain events through `EventDispatcherPort` **after** the durable save, and guard the idempotent path so a repeat call does not re-announce.
6. Cross module boundaries only through a sibling's `Application\Port\` and `Application\Contract\` — never its `Domain\` or `Infrastructure\`.
7. Add the `config/modules/<module>.yaml` entries any **new port** needs: the `alias:` **and** the explicit `$entityManager` argument for anything touching Doctrine.
8. Write the handler unit test at the mirrored `tests/Unit/…` path, mocking every port and covering each failure path plus the no-event idempotent case.
9. Run `make cs-fix phpstan deptrac lint` and the targeted `phpunit`.
