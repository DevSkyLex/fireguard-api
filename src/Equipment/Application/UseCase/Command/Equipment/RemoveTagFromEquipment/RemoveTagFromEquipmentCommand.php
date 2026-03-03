<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveTagFromEquipmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTagFromEquipmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $tagId,
  ) {
  }
  // #endregion
}
