<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Shared\Application\Port\Outbound\CachePort;
use Throwable;

final readonly class OrganizationCacheInvalidator
{
  public function __construct(
    private CachePort $cache,
  ) {
  }

  public function invalidateCurrentMemberProfile(string $organizationId, string $userId): void
  {
    try {
      $this->cache->delete(OrganizationCacheKeys::currentMemberProfile($organizationId, $userId));
      $this->cache->delete(OrganizationCacheKeys::permissions($organizationId, $userId));
    } catch (Throwable) {
      // Cache failures should not block organization membership mutations.
    }
  }

  /**
   * @param iterable<array{organizationId: string, userId: string}> $profiles
   */
  public function invalidateCurrentMemberProfiles(iterable $profiles): void
  {
    foreach ($profiles as $profile) {
      $this->invalidateCurrentMemberProfile($profile['organizationId'], $profile['userId']);
    }
  }
}
