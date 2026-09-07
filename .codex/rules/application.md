# Application layer

Application owns use cases, contracts and ports. A use case is a Command or Query,
its Handler and a typed Result under `Application/UseCase/{Command|Query}/<Area>/<Action>/`.

- Handlers contain business orchestration and inject ports only. They never inject a
  Doctrine repository, adapter, processor, provider or `EntityManagerInterface`.
- Return Result objects rather than loose arrays. `void` is valid for commands with
  no result.
- Persist before dispatching domain events. Replayed or idempotent paths must not
  dispatch the same event again.
- Cross-module imports may target only another module's `Application/Port` or
  `Application/Contract` surface. Domain, Infrastructure and Presentation internals
  never cross the boundary.
- Keep vendor and framework types behind outbound ports. Put reusable cross-module
  data in explicit Application contracts.

Mirror the nearest use case in the owning module. Update its wiring and `MODULE.md`
when the flow or public contract changes. Validate with targeted tests, PHPStan,
Deptrac and the container lint.
