<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Shared\Application\Port\Outbound\CachePort;
use Throwable;

final class OrganizationCacheInvalidator
{
  private int $revision = 0;

  public function __construct(
    private readonly CachePort $cache,
  ) {
  }

  public function revision(): int
  {
    return $this->revision;
  }

  public function invalidateOrganization(): void
  {
    ++$this->revision;
  }

  public function invalidateCurrentMemberProfile(string $organizationId, string $userId): void
  {
    $this->invalidateOrganization();
    foreach ([
      OrganizationCacheKeys::currentMemberProfile($organizationId, $userId),
      OrganizationCacheKeys::permissions($organizationId, $userId),
    ] as $key) {
      try {
        $this->cache->delete($key);
      } catch (Throwable) {
        // Legacy shared entries are best effort; new authorization reads never trust them.
      }
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
