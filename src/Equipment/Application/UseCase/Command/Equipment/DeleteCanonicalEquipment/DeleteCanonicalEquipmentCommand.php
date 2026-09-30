<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the equipment identifier and expected revision required for an optimistic delete.
   *
   * @access public
   *
   * @param string $equipmentId equipment to delete
   * @param int $expectedRevision revision the caller read before requesting deletion
   *
   * @return void
   */
  public function __construct(
    public string $equipmentId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
