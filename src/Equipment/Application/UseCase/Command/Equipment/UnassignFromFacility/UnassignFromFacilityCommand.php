<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UnassignFromFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnassignFromFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped equipment to remove from its current facility.
   *
   * @access public
   *
   * @param string $organizationId organization owning the equipment
   * @param string $equipmentId equipment to remove from its current facility
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
