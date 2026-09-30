<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

/**
 * Class AuthorizationCacheKeys
 *
 * Coordinates AuthorizationCacheKeys application behavior.
 *
 * @category Service
 */
final readonly class AuthorizationCacheKeys
{
  // #region Methods
  /**
   * Method permissions
   *
   * Builds the cache key for a user’s effective permissions.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return string
   */
  public static function permissions(string $userId): string
  {
    return 'authz.permissions.' . $userId;
  }

  /**
   * Method roles
   *
   * Builds the cache key for a user’s effective roles.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return string
   */
  public static function roles(string $userId): string
  {
    return 'authz.roles.' . $userId;
  }
  // #endregion
}
