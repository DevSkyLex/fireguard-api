<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\MoveFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase MoveFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MoveFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the facility and its optional new parent within an organization.
   *
   * @access public
   *
   * @param string $organizationId organization scope for the move
   * @param string $facilityId facility to move
   * @param ?string $parentFacilityId new parent facility identifier, or null to make it a root
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $parentFacilityId = null,
  ) {
  }
  // #endregion
}
