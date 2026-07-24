<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresence;

use Messaging\Application\Contract\Presence\MemberPresenceView;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;

use function is_string;

/**
 * UseCase GetPresenceHandler.
 *
 * Resolves online presence (L2.7) for a CALLER-SUPPLIED, bounded list of
 * organization member ids — a multi-get over
 * `Shared\Application\Port\Outbound\CachePort`, never a "list all online
 * members" query (that is not expressible against a KV cache and is
 * deliberately NOT implemented — see `MODULE.md`). A member id absent from
 * the cache (never pinged, or its 90s TTL has expired) resolves to
 * `online: false, lastSeenAt: null`; no distinction is made between "never
 * seen" and "seen a while ago" — a KV cache with a TTL cannot tell the two
 * apart, and the product does not need to.
 *
 * Gated by `organization.messaging.read` alone
 * ({@see MessagingAccessPolicy::assertCanUseMessaging()}) — the same floor
 * permission as starting a direct conversation. There is no per-member-id
 * authorization check beyond that: presence is organization-wide metadata
 * with no subject/channel angle, so any member who may use messaging at all
 * may check any other member's presence within the SAME organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param CachePort $cache the shared cache port
   */
  public function __construct(
    private MessagingAccessPolicy $accessPolicy,
    private CachePort $cache,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetPresenceQuery $query the query value
   *
   * @return GetPresenceResult the use case result
   */
  public function __invoke(GetPresenceQuery $query): GetPresenceResult
  {
    $this->accessPolicy->assertCanUseMessaging($query->userId, $query->organizationId);

    $presences = [];
    foreach ($query->memberIds as $memberId) {
      $lastSeenAt = $this->cache->get(MessagingPresenceCacheKeys::key($query->organizationId, $memberId));
      $presences[] = new MemberPresenceView(
        $memberId,
        is_string($lastSeenAt),
        is_string($lastSeenAt) ? $lastSeenAt : null,
      );
    }

    return new GetPresenceResult($presences);
  }
  // #endregion
}
