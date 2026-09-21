<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ConfirmImportSimulation;

use Shared\Application\Message\CommandMessage;

/** Command ConfirmImportSimulationCommand. The retained simulation is the idempotency key. */
final readonly class ConfirmImportSimulationCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $simulationId)
  {
  }
}
