<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveTagFromEquipmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTagFromEquipmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $equipmentId,
    public string $tagId,
  ) {
  }
  // #endregion
}
