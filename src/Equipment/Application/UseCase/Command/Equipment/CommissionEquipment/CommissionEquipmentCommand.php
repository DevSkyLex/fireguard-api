<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CommissionEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CommissionEquipmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CommissionEquipmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
