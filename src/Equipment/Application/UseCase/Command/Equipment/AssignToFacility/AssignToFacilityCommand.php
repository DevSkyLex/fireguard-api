<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AssignToFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AssignToFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignToFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization-scoped request to associate equipment with a facility and optional installation date.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment and facility
   * @param string $equipmentId equipment to assign
   * @param string $facilityId facility receiving the equipment
   * @param ?string $installedAt optional installation date supplied by the caller
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $facilityId,
    public ?string $installedAt = null,
  ) {
  }
  // #endregion
}
