<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PingPresence;

use DateTimeImmutable;
use DateTimeInterface;
use Messaging\Application\Port\Outbound\PresenceRealtimePort;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{CachePort, LoggerPort};
use Throwable;
use User\Application\Port\Inbound\PresencePreferenceReaderPort;

use function is_string;

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
    private PresenceRealtimePort $realtime,
    private LoggerPort $logger,
    private PresencePreferenceReaderPort $preferences,
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
    $memberId = $this->accessPolicy->resolveActiveMemberId($command->organizationId, $command->userId);

    $wasOnline = is_string($this->cache->get(MessagingPresenceCacheKeys::key($command->organizationId, $memberId)));
    $now = new DateTimeImmutable();

    $this->cache->set(
      MessagingPresenceCacheKeys::key($command->organizationId, $memberId),
      $now->format(DateTimeInterface::ATOM),
      self::PRESENCE_TTL_SECONDS,
    );

    $invisible = $this->preferences->readMany([$command->userId])[$command->userId]->invisible ?? false;
    if (!$wasOnline && !$invisible) {
      try {
        $this->realtime->publish($command->organizationId, $memberId);
      } catch (Throwable) {
        $this->logger->warning('Presence Mercure publication failed.', ['organizationId' => $command->organizationId, 'memberId' => $memberId]);
      }
    }

    return new PingPresenceResult($memberId, $now);
  }
  // #endregion
}
