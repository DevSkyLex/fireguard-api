<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalEquipmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalEquipmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $equipmentId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
