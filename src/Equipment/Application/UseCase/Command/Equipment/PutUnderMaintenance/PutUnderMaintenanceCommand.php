<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PutUnderMaintenanceCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PutUnderMaintenanceCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
