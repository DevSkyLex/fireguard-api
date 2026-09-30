<?php

declare(strict_types=1);

namespace Auth\Application\Service;

use Shared\Application\Port\Outbound\CachePort;
use Throwable;

/**
 * Class SecurityUserCacheInvalidator
 *
 * Invalidates cached security user data after account state changes.
 *
 * @category Service
 */
final readonly class SecurityUserCacheInvalidator
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the cache port used to invalidate cached authentication projections after user changes.
   *
   * @access public
   *
   * @param CachePort $cache deletes the user cache entry
   *
   * @return void
   */
  public function __construct(
    private CachePort $cache,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method invalidateUser
   *
   * Deletes the cached security user entry without blocking a security mutation on cache failure.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return void no return value
   */
  public function invalidateUser(string $userId): void
  {
    try {
      $this->cache->delete(SecurityUserCacheKeys::user($userId));
    } catch (Throwable) {
      // Cache failures should not block security-sensitive mutations.
    }
  }

  /**
   * Method invalidateUsers
   *
   * Invalidates each supplied user's authentication projection using the failure-tolerant single-user operation.
   *
   * @access public
   *
   * @param iterable<string> $userIds user identifiers whose cached projections must be removed
   *
   * @return void
   */
  public function invalidateUsers(iterable $userIds): void
  {
    foreach ($userIds as $userId) {
      $this->invalidateUser($userId);
    }
  }
  // #endregion
}
