<?php

declare(strict_types=1);

namespace Auth\Application\Service;

/**
 * Class SecurityUserCacheKeys
 *
 * Builds cache keys for security user lookups.
 *
 * @category Utility
 */
final readonly class SecurityUserCacheKeys
{
  /**
   * Method user.
   *
   * Builds the cache key for a user identifier.
   *
   * @access public
   *
   * @static
   *
   * @param string $userId the user identifier
   *
   * @return string the cache key
   */
  public static function user(string $userId): string
  {
    return 'auth.security_user.' . $userId;
  }
}
