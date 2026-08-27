---
name: usecase-patterns
description: The Command/Query/Handler/Result shape in fireguard-sso-api — the handler template, port-only injection, domain events dispatched after the durable save, cross-module contract types, and the handler unit test. Use when adding or changing anything under Application/UseCase/.
---

# Use case patterns

`ARCHITECTURE.md`, Application Layer Standard: *"Implement business logic in handlers, not in processors"* · *"Use cases are the single entry point for business logic"* · *"Return Result objects, not raw arrays."*

## The triad

```text
Application/UseCase/{Command|Query}/<Area>/<UseCase>/
  <UseCase>Command.php     # or <UseCase>Query.php — an immutable payload
  <UseCase>Handler.php     # the logic
  <UseCase>Result.php      # an immutable return shape
```

## The handler

```php
<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};

/**
 * UseCase ArchiveFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private EventDispatcherPort $eventDispatcher,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  public function __invoke(ArchiveFacilityCommand $command): ArchiveFacilityResult
  {
    // 1. Parse raw scalars into value objects; InvalidValueException -> InvalidArgumentException.
    // 2. Load through the port; missing or out-of-scope -> a domain exception.
    // 3. Apply the invariant by calling the aggregate's own method.
    // 4. Persist through the port.
    // 5. Dispatch the domain event — AFTER the save.
    // 6. Return the Result.
  }
  // #endregion
}
```

Read `src/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandler.php` in full before writing your own — it is the reference for all six steps, including the idempotence guard.

## The five rules that matter

**1. Ports only in the constructor.** `…Port` interfaces, never a Doctrine repository, an adapter, or an `EntityManagerInterface`. A hook blocks the import; `make deptrac` fails on it.

**2. One `__invoke`.** Signature `(<UseCase>Command $command): <UseCase>Result`. Helper methods are private.

**3. Result objects, never arrays.** The Result is the contract the Presentation layer maps to an Output DTO.

**4. Events after the durable save.** A dispatch before the save means a failed persistence leaves an event — and often a notification — behind, describing something that never happened. Order matters:

```php
$this->repository->save($aggregate);          // durable first
$this->eventDispatcher->dispatch(new XEvent(…));  // then announce
```

**5. Guard the idempotent path.** When an action can legitimately run twice (archive an already-archived record), capture the prior state **before** mutating and use it to suppress the second event and notification. That negative case belongs in the test too.

## Exceptions

- a value object rejecting input throws `Shared\Domain\Exception\InvalidValueException`; the handler catches it at its boundary and rethrows `InvalidArgumentException`,
- a missing or out-of-scope aggregate throws a **domain** exception with a named constructor: `FacilityNotFoundException::withId($id)`,
- the Presentation layer maps exception class → HTTP status centrally in an `EventSubscriber`. The handler never knows a status code.

## Cross-module calls

Allowed: the sibling's `Application\Port\` and `Application\Contract\`.

```php
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
```

Forbidden: its `Domain\` and `Infrastructure\`. If the contract type you need does not exist, add it under the **owning** module's `Application/Contract/`.

A best-effort side effect (a notification, a log) belongs in a `try/catch` that logs and continues — it must not fail the use case that already committed.

## Buses

`CommandBusPort` for writes, `QueryBusPort` for reads, when one use case must invoke another. Do not instantiate a sibling handler directly.

## The test

`tests/Unit/<Module>/Application/UseCase/{Command|Query}/<Area>/<UseCase>/<UseCase>HandlerTest.php` — the path mirrors `src/` exactly. PHPUnit 12 attributes.

Mock every port and assert:

- the Result, field by field,
- which ports were called and with what,
- the domain exception on each failure path,
- the event dispatched — **and the idempotent path where it must not be**.

A handler test that reaches a database is testing the wrong unit.

## Wiring

Handlers autowire through the module's resource block. You only touch `config/modules/<module>.yaml` when the handler needs a **new port** — and then you add both the `alias:` and, for anything touching Doctrine, the explicit `$entityManager` argument. See the `dual-database` skill; that omission is the silent wrong-database bug.
