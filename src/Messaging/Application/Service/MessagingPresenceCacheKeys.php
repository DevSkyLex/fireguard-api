<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

/**
 * Service MessagingPresenceCacheKeys.
 *
 * Builds the `Shared\Application\Port\Outbound\CachePort` key for one
 * organization member's presence entry (L2.7): `messaging.presence.
 * {organizationId}.{memberId}`. Mirrors `Organization\Application\Service\
 * OrganizationCacheKeys`'s bare static-method shape.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingPresenceCacheKeys
{
  /**
   * Method key.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the organization member identifier
   *
   * @return string the cache key for this member's presence entry
   */
  public static function key(string $organizationId, string $memberId): string
  {
    return 'messaging.presence.' . $organizationId . '.' . $memberId;
  }
}
