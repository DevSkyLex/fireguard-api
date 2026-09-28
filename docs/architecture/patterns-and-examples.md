# Backend patterns and examples

Apply the generic four-layer pattern to the owning module. Examples do not relax transaction, access or module-boundary rules.

**Authoritative references:** [Architecture](../../ARCHITECTURE.md) · [Doctrine mapping](../../config/packages/doctrine.yaml).

## Module placement

```text
src/Example/
  MODULE.md
  Application/
    UseCase/
    Port/
    Contract/
  Domain/
    Model/
    ValueObject/
    Exception/
  Infrastructure/
    Persistence/
    Adapter/
  Presentation/Api/
    Resource/
    Dto/
    Processor/
    Provider/
```

Create only needed concerns. A handler injects application ports, owns orchestration
and returns a typed Result. Domain models enforce invariants. Adapters implement
ports; processors/providers translate HTTP input/output without deciding business
rules. Cross-module access uses the owner's published application ports/contracts.

## Explicit persistence wiring

For an illustrative main-owned repository:

```yaml
Example\Infrastructure\Persistence\Doctrine\ExampleRepository:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

The auth manager is the default; omission can query the wrong database while static
checks pass. Wire persistence and transactions to the owner explicitly. Keep each
migration history separate and name its configuration on every console command.

## Durable effects

Save business state before publishing external effects. Flows with a transactional
outbox commit business writes and enqueue together on one connection. Consumers
use durable receipts and the owning module's retry contract. Keep denial, replay,
concurrency and failure-recovery tests at the boundary they establish.
