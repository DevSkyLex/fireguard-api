<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Presence\PublishPresencePreferenceChange;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, PresenceRealtimePort};
use Messaging\Application\Service\MessagingPresenceCacheKeys;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{CachePort, LoggerPort};
use Throwable;

use function is_string;

/**
 * UseCase PublishPresencePreferenceChangeHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PublishPresencePreferenceChangeHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private MessagingMemberDirectoryPort $members, private CachePort $cache, private PresenceRealtimePort $realtime, private LoggerPort $logger)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(PublishPresencePreferenceChangeCommand $command): void
  {
    foreach ($this->members->activeMembershipsForUser($command->userId) as $membership) {
      try {
        if (is_string($this->cache->get(MessagingPresenceCacheKeys::key($membership['organizationId'], $membership['memberId'])))) {
          $this->realtime->publish($membership['organizationId'], $membership['memberId']);
        }
      } catch (Throwable) {
        $this->logger->warning('Presence organization invalidation failed.', $membership);
      }
    }
  }
}
