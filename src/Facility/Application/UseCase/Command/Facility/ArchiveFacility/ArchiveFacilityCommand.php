<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ArchiveFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped facility to archive.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to locate and authorize the facility
   * @param string $facilityId facility to archive
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
