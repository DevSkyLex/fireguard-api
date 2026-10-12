# Backend patterns and examples

Apply the generic four-layer pattern to the owning module. Examples do not relax transaction, access or module-boundary rules.

**Authoritative references:** [Architecture](../../ARCHITECTURE.md) · [Doctrine mapping](../../config/packages/doctrine.yaml).

Global Doctrine configuration defines connections and managers. The selected
module's [`doctrine.yaml` fragment](../../config/modules/organization/doctrine.yaml)
owns its mapping namespace; its
[`api_platform.yaml` fragment](../../config/modules/organization/api_platform.yaml)
owns resource discovery and HTTP error translation. Service aliases and explicit
manager arguments remain in the module entry point.

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

## Owner-published catalogs

An owner publishes immutable definitions and a pure contribution contract. Its
adapter registers the supported keys and exposes any typed evaluation capability.
The engine composes registered definitions in their declared order, validates
duplicate ownership at boot and resolves new execution through the registered owner.
Hydration validates identifier syntax without requiring that owner to be installed.

For example, [Onboarding's contribution port](../../src/Onboarding/Application/Port/Inbound/OrganizationOnboardingStepContributionPort.php)
publishes a static descriptor and receives a typed session/organization context for
evaluation and execution. Facility and Equipment provide their own first-resource
checks; the flow engine and HTTP assembler contain no product catalog. The same
ownership rule governs permission vocabularies, quotas, settings policies and
other application capability catalogs.

## HTTP result snapshots

The [operation declaration](../../src/Shared/Application/Contract/Http/HttpIdempotencyOperation.php)
names the primary command/result and its owning database. Revalidate current access
before replay and complete the serialized application result before the business
commit, using the same connection. Keep root-owned media transactions separate from
bus-owned transactions. A timeout after commit is reconciled from the durable receipt;
it does not justify another execution. Secret responses follow the bounded encrypted
replay rules in [Security](../../SECURITY.md#durable-http-idempotency).
