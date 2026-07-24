<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

/**
 * Port ApprovalMemberDirectoryPort.
 *
 * Cross-module outbound port resolving organization membership and role
 * satisfaction for the approval gate and decision handlers. Implemented by
 * the Organization module. Implementations must fail closed: an unresolved
 * user/member never satisfies a role requirement, so a lookup failure can
 * never bypass the four-eyes check.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalMemberDirectoryPort
{
  /**
   * Method resolveMemberId.
   *
   * Resolves the organization member identifier for a user.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?string the member identifier, or null when the user is not a member
   */
  public function resolveMemberId(string $organizationId, string $userId): ?string;

  /**
   * Method memberSatisfiesRole.
   *
   * Reports whether a member satisfies at least the given minimum role
   * (`member`|`admin`).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier
   * @param string $minRole the minimum required role
   *
   * @return bool true when the member satisfies the role requirement
   */
  public function memberSatisfiesRole(string $organizationId, string $memberId, string $minRole): bool;
}
