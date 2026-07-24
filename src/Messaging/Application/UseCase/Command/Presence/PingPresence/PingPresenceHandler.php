<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PingPresence;

use DateTimeImmutable;
use DateTimeInterface;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\CachePort;

/**
 * UseCase PingPresenceHandler.
 *
 * Records the acting member's online presence (L2.7) — **no database
 * table**. Writes the current timestamp to
 * `Shared\Application\Port\Outbound\CachePort` under
 * `messaging.presence.{organizationId}.{memberId}` with a **90 second
 * TTL**; the client is expected to call this roughly every 60 seconds, so
 * two consecutive pings always land well inside the same TTL window,
 * keeping the entry alive for as long as the client keeps pinging.
 * Presence is inherently ephemeral: losing it on a cache flush/restart is
 * CORRECT behaviour (the member simply reads as offline until their next
 * ping), never a data-loss concern — see `MODULE.md`'s "Online presence"
 * section.
 *
 * The member id is ALWAYS the CALLER's own, resolved server-side via
 * {@see MessagingAccessPolicy::resolveActiveMemberId()} — there is no
 * `memberId` input anywhere on this command, which is what structurally
 * prevents a member from ever pinging presence as someone else.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PingPresenceHandler implements CommandHandler
{
  // #region Constants
  private const int PRESENCE_TTL_SECONDS = 90;
  // #endregion

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
   * @param PingPresenceCommand $command the command value
   *
   * @return PingPresenceResult the use case result
   */
  public function __invoke(PingPresenceCommand $command): PingPresenceResult
  {
    $this->accessPolicy->assertCanUseMessaging($command->userId, $command->organizationId);
    $memberId = $this->accessPolicy->resolveActiveMemberId($command->organizationId, $command->userId);

    $now = new DateTimeImmutable();

    $this->cache->set(
      MessagingPresenceCacheKeys::key($command->organizationId, $memberId),
      $now->format(DateTimeInterface::ATOM),
      self::PRESENCE_TTL_SECONDS,
    );

    return new PingPresenceResult($memberId, $now);
  }
  // #endregion
}
