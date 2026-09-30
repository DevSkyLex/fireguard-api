<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DecommissionEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DecommissionEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization and equipment to permanently decommission.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to load the equipment
   * @param string $equipmentId equipment to decommission
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
