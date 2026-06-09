<?php

declare(strict_types=1);

namespace Auth\Application\Service;

use Shared\Application\Port\Outbound\CachePort;
use Throwable;

final readonly class SecurityUserCacheInvalidator
{
  public function __construct(
    private CachePort $cache,
  ) {
  }

  public function invalidateUser(string $userId): void
  {
    try {
      $this->cache->delete(SecurityUserCacheKeys::user($userId));
    } catch (Throwable) {
      // Cache failures should not block security-sensitive mutations.
    }
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
