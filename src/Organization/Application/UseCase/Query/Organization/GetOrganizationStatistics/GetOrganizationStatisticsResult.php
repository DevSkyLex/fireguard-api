<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationStatisticsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationStatisticsResult class.
   *
   * @since 1.0.0
   *
   * @param int $memberCount the total number of members
   * @param int $roleCount the total number of roles
   * @param int $facilityCount the total number of facilities including archived ones
   * @param int $activeFacilityCount the total number of active facilities
   * @param int $pendingInvitationCount the number of pending invitations
   */
  public function __construct(
    public int $memberCount,
    public int $roleCount,
    public int $facilityCount,
    public int $activeFacilityCount,
    public int $pendingInvitationCount,
  ) {
  }
  // #endregion
}
