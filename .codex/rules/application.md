# Application layer

Application owns use cases, contracts and ports. A use case is a Command or Query,
its Handler and a typed Result under `Application/UseCase/{Command|Query}/<Area>/<Action>/`.

- Handlers contain business orchestration and inject ports only. They never inject a
  Doctrine repository, adapter, processor, provider or `EntityManagerInterface`.
- Return Result objects rather than loose arrays. `void` is valid for commands with
  no result.
- Respect the owning module's event guarantee. An outbox entry and business state commit
  atomically on one owning connection; consumers receive only committed work. Never move
  that enqueue after commit. Non-outbox side effects must not observe uncommitted writes.
  Replayed commands and consumers preserve attempt/receipt identity and do not repeat effects.
- Cross-module imports may target only another module's `Application/Port` or
  `Application/Contract` surface. Domain, Infrastructure and Presentation internals
  never cross the boundary.
- Keep vendor and framework types behind outbound ports. Put reusable cross-module
  data in explicit Application contracts.

Mirror the nearest use case in the owning module. Update its wiring and `MODULE.md`
when the flow or public contract changes. Validate with targeted tests, PHPStan,
both Deptrac configurations and the container lint. For async work, read the
[use-case patterns](../../.agents/skills/fg-api-usecase/references/usecase-patterns.md).
