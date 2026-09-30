<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

use Auth\Application\Service\SecurityUserCacheInvalidator;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

/**
 * Class AuthorizationCacheInvalidator
 *
 * Invalidates cached roles and permissions after authorization changes.
 *
 * @category Service
 */
final readonly class AuthorizationCacheInvalidator
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Configures authorization cache eviction and optional security-user cache invalidation.
   *
   * @access public
   *
   * @param CachePort $cache deletes authorization cache entries
   * @param SecurityUserCacheInvalidator|null $securityUserCacheInvalidator optionally invalidates the security user cache
   *
   * @return void
   */
  public function __construct(
    private CachePort $cache,
    private ?SecurityUserCacheInvalidator $securityUserCacheInvalidator = null,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method invalidateUser
   *
   * Deletes the user's role and permission cache entries, then invalidates security user data.
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
      $this->cache->delete(AuthorizationCacheKeys::roles($userId));
      $this->cache->delete(AuthorizationCacheKeys::permissions($userId));
    } catch (Throwable) {
      // Cache failures should not block role or permission mutations.
    }

    $this->securityUserCacheInvalidator?->invalidateUser($userId);
  }

  /**
   * @param iterable<string> $userIds
   */
  public function invalidateUsers(iterable $userIds): void
  {
    foreach ($userIds as $userId) {
      $this->invalidateUser($userId);
    }
  }
  // #endregion
}
