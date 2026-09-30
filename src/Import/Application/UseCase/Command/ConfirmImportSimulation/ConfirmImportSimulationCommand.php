<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ConfirmImportSimulation;

use Shared\Application\Message\CommandMessage;

/** Command ConfirmImportSimulationCommand. The retained simulation is the idempotency key. */
final readonly class ConfirmImportSimulationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the retained simulation and requesting user for idempotent import confirmation.
   *
   * @access public
   *
   * @param string $userId user who requested confirmation
   * @param string $simulationId retained simulation identifier used as the confirmation idempotency key
   *
   * @return void
   */
  public function __construct(public string $userId, public string $simulationId)
  {
  }
  // #endregion
}
