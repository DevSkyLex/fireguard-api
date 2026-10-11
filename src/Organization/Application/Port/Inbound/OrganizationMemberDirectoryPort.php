<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Member\OrganizationMemberView;

/**
 * Port OrganizationMemberDirectoryPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationMemberDirectoryPort
{
  // #region Methods
  /**
   * Reads membership identity for a caller that must compare its organization scope.
   *
   * @since 1.0.0
   *
   * @param string $memberId the membership identifier
   *
   * @return ?OrganizationMemberView the scalar membership facts
   */
  public function findById(string $memberId): ?OrganizationMemberView;

  /**
   * Reads one account's membership in the requested organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization scope
   * @param string $userId the account identifier
   *
   * @return ?OrganizationMemberView the scoped membership facts
   */
  public function findByOrganizationAndUser(string $organizationId, string $userId): ?OrganizationMemberView;

  /**
   * Resolves user identities in one organization-scoped batch.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization scope
   * @param list<string> $memberIds the membership identifiers
   * @param bool $activeOnly whether inactive memberships are excluded
   *
   * @return array<string, string> account identifiers keyed by membership
   */
  public function findUserIdsByMemberIds(string $organizationId, array $memberIds, bool $activeOnly = false): array;
  // #endregion
}
