<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveTagFromEquipmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTagFromEquipmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the equipment and tag identifiers after removing their association.
   *
   * @access public
   *
   * @param string $equipmentId equipment from which the tag was removed
   * @param string $tagId identifier of the removed tag
   *
   * @return void
   */
  public function __construct(
    public string $equipmentId,
    public string $tagId,
  ) {
  }
  // #endregion
}
