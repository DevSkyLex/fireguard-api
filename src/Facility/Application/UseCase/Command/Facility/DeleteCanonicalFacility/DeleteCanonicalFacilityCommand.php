<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the facility identifier and expected revision for an optimistic delete request.
   *
   * @access public
   *
   * @param string $facilityId facility to delete
   * @param int $expectedRevision revision the caller read before requesting deletion
   *
   * @return void
   */
  public function __construct(
    public string $facilityId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
