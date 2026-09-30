<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveTagFromEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTagFromEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped equipment and tag association to remove.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to verify the equipment and tag association
   * @param string $equipmentId equipment losing the tag
   * @param string $tagId tag association to remove
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $tagId,
  ) {
  }
  // #endregion
}
