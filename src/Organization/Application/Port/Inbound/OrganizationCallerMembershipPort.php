<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationCallerRoleResult;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\OrganizationId;

/**
 * Port OrganizationCallerMembershipPort.
 *
 * Single source of the "caller membership" projection (`isOwner` /
 * `roles` on {@see \Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult})
 * so every use case that resolves it — {@see \Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsHandler}
 * and {@see \Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationHandler}
 * (the latter re-read by every mutation processor's `buildOutput()`) —
 * agrees on the same rule. Split into three narrow methods rather than one
 * that always resolves membership itself, because
 * `ListUserOrganizationsHandler` already bulk-loads the caller's
 * memberships in one `findByUserId` call and must be able to reuse that
 * row instead of paying a `findByOrganizationAndUser` lookup per list item.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationCallerMembershipPort
{
  // #region Methods
  /**
   * Method isOwner.
   *
   * Tells whether the caller owns the organization. Ownership is a plain
   * field on the `Organization` aggregate, never membership-dependent — the
   * owner stays the owner even with an inactive or missing membership row.
   *
   * @since 1.0.0
   *
   * @param string $organizationOwnerUserId the organization's current owner user identifier
   * @param string $callerUserId the requesting user identifier
   *
   * @return bool true when the caller is the organization's current owner
   */
  public function isOwner(string $organizationOwnerUserId, string $callerUserId): bool;

  /**
   * Method findActiveCallerMembership.
   *
   * Resolves the caller's own ACTIVE membership in the organization, for
   * callers that have not already loaded it. Returns null both when no
   * membership row exists and when it exists but is inactive (a removed
   * member), matching {@see ListUserOrganizationsHandler}'s own active
   * filter so the two use cases can never disagree on who counts as a
   * member.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $callerUserId the requesting user identifier
   *
   * @return ?OrganizationMember the caller's active membership, or null
   */
  public function findActiveCallerMembership(OrganizationId $organizationId, string $callerUserId): ?OrganizationMember;

  /**
   * Method resolveRoles.
   *
   * Resolves the organization roles assigned to the caller's membership,
   * joining `OrganizationMemberRepositoryPort::findRoleIdsForMember()` then
   * `OrganizationRoleRepositoryPort::findByIdsInOrganization()`. Returns an
   * empty list when the membership is null or holds no assigned role, never
   * an error.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?OrganizationMember $membership the caller's membership when known (null resolves to no roles)
   *
   * @return list<GetOrganizationCallerRoleResult> the caller's assigned roles
   */
  public function resolveRoles(OrganizationId $organizationId, ?OrganizationMember $membership): array;
  // #endregion
}
