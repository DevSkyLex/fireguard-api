<?php

declare(strict_types=1);

namespace Organization\Application\Service;

/**
 * Class OrganizationCacheKeys
 *
 * Builds cache identifiers for organization permissions and member profiles.
 *
 * @category Service
 */
final readonly class OrganizationCacheKeys
{
  // #region Methods
  /**
   * Method permissions
   *
   * Creates the key for one user's permissions in an organization.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $userId user identifier
   *
   * @return string organization-scoped permissions cache key
   */
  public static function permissions(string $organizationId, string $userId): string
  {
    return 'organization.permissions.' . $organizationId . '.' . $userId;
  }

  /**
   * Method currentMemberProfile
   *
   * Creates the key for one user's current member profile in an organization.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $userId user identifier
   *
   * @return string organization-scoped member profile cache key
   */
  public static function currentMemberProfile(string $organizationId, string $userId): string
  {
    return 'organization.member_profile.' . $organizationId . '.' . $userId;
  }
  // #endregion
}
