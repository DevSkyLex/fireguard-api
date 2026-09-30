<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Shared\Application\Port\Outbound\CachePort;
use Throwable;

/**
 * Class OrganizationCacheInvalidator
 *
 * Invalidates legacy organization cache entries and advances the authorization revision.
 *
 * @category Service
 */
final class OrganizationCacheInvalidator
{
  // #region Properties
  /**
   * Property revision
   *
   * Monotonic revision incremented when organization-scoped cached state is invalidated.
   *
   * @access private
   */
  private int $revision = 0;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Provides cache access for removing legacy permission and member profile entries.
   *
   * @access public
   *
   * @param CachePort $cache removes legacy shared cache entries
   *
   * @return void
   */
  public function __construct(
    private readonly CachePort $cache,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method revision
   *
   * Returns the current invalidation revision.
   *
   * @access public
   *
   * @return int current revision value
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method invalidateOrganization
   *
   * Advances the revision so consumers can recognize organization-wide cache changes.
   *
   * @access public
   *
   * @return void
   */
  public function invalidateOrganization(): void
  {
    ++$this->revision;
  }

  /**
   * Method invalidateCurrentMemberProfile
   *
   * Advances the revision and best-effort deletes this member's legacy profile and permission keys.
   *
   * @access public
   *
   * @param string $organizationId organization identifier
   * @param string $userId user identifier
   *
   * @return void
   */
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
   * Method invalidateCurrentMemberProfiles
   *
   * Invalidates each organization and user pair in the supplied collection.
   *
   * @access public
   *
   * @param iterable<array{organizationId: string, userId: string}> $profiles
   *
   * @return void
   */
  public function invalidateCurrentMemberProfiles(iterable $profiles): void
  {
    foreach ($profiles as $profile) {
      $this->invalidateCurrentMemberProfile($profile['organizationId'], $profile['userId']);
    }
  }
  // #endregion
}
