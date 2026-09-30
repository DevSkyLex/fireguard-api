<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\RestoreFacility;

use Shared\Application\Message\CommandMessage;

/**
 * Class RestoreFacilityCommand
 *
 * Requests restoration of an archived facility within an organization.
 *
 * @category Command
 */
final readonly class RestoreFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the owning organization and facility identifiers for restoration.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $facilityId the facility identifier to restore
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
