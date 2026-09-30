<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CommissionEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CommissionEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CommissionEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization and equipment to move into operational service.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to load the equipment
   * @param string $equipmentId equipment to commission
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
