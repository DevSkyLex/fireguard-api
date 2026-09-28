<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Presence\PublishPresencePreferenceChange;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, PresenceRealtimePort};
use Messaging\Application\UseCase\Command\Presence\PublishPresencePreferenceChange\{PublishPresencePreferenceChangeCommand, PublishPresencePreferenceChangeHandler};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{CachePort, LoggerPort};

use function str_ends_with;

/**
 * Test PublishPresencePreferenceChangeHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PublishPresencePreferenceChangeHandlerTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function onlyInvalidatesLiveMembershipsAndIsolatesPublicationFailures(): void
  {
    $members = $this->createMock(MessagingMemberDirectoryPort::class);
    $members->expects(self::once())->method('activeMembershipsForUser')->with('user')->willReturn([
      ['organizationId' => 'one', 'memberId' => 'one'], ['organizationId' => 'two', 'memberId' => 'two'], ['organizationId' => 'three', 'memberId' => 'three'],
    ]);
    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturnCallback(static fn (string $key): ?string => str_ends_with($key, '.three') ? null : 'live');
    $seen = [];
    $realtime = $this->createMock(PresenceRealtimePort::class);
    $realtime->expects(self::exactly(2))->method('publish')->willReturnCallback(static function (string $org) use (&$seen): void {
      $seen[] = $org;
      if ('one' === $org) {
        throw new RuntimeException('Unavailable topic');
      }
    });
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');
    new PublishPresencePreferenceChangeHandler($members, $cache, $realtime, $logger)(new PublishPresencePreferenceChangeCommand('user'));
    self::assertSame(['one', 'two'], $seen);
  }
}
