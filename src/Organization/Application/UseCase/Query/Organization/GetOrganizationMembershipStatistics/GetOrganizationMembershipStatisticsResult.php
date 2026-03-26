<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationMembershipStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMembershipStatisticsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationMembershipStatisticsResult class.
   *
   * @since 1.0.0
   *
   * @param int $memberCount the total number of organization members
   * @param int $activeMemberCount the total number of active organization members
   * @param int $inactiveMemberCount the total number of inactive organization members
   * @param int $roleCount the total number of organization roles
   * @param int $systemRoleCount the total number of system roles
   * @param int $customRoleCount the total number of custom roles
   * @param int $invitationCount the total number of invitations
   * @param int $pendingInvitationCount the total number of pending invitations
   * @param int $acceptedInvitationCount the total number of accepted invitations
   * @param int $revokedInvitationCount the total number of revoked invitations
   * @param int $expiredInvitationCount the total number of expired invitations
   */
  public function __construct(
    public int $memberCount,
    public int $activeMemberCount,
    public int $inactiveMemberCount,
    public int $roleCount,
    public int $systemRoleCount,
    public int $customRoleCount,
    public int $invitationCount,
    public int $pendingInvitationCount,
    public int $acceptedInvitationCount,
    public int $revokedInvitationCount,
    public int $expiredInvitationCount,
  ) {
  }
  // #endregion
}
