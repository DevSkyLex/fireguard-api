<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

use Auth\Application\Service\SecurityUserCacheInvalidator;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

final readonly class AuthorizationCacheInvalidator
{
  public function __construct(
    private CachePort $cache,
    private ?SecurityUserCacheInvalidator $securityUserCacheInvalidator = null,
  ) {
  }

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
}
