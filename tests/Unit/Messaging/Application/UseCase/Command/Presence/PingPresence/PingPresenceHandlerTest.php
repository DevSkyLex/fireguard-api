<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Presence\PingPresence;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, PresenceRealtimePort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Presence\PingPresence\{PingPresenceCommand, PingPresenceHandler};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{CachePort, LoggerPort};
use User\Application\Contract\Presence\PresencePreference;
use User\Application\Port\Inbound\PresencePreferenceReaderPort;

final class PingPresenceHandlerTest extends TestCase
{
  /**
   * @return iterable<string, array{?string, int, bool}>
   */
  public static function heartbeats(): iterable
  {
    yield 'first heartbeat' => [null, 1, false];
    yield 'renewal from any device' => ['2026-09-26T00:00:00+00:00', 0, false];
    yield 'invisible heartbeat has no public activity event' => [null, 0, true];
  }

  #[Test]
  #[DataProvider('heartbeats')]
  public function recordsActiveMembershipWithoutMessagingPermission(?string $previous, int $publications, bool $invisible): void
  {
    $preferences = $this->createStub(PresencePreferenceReaderPort::class);
    $preferences->method('readMany')->willReturn(['user' => new PresencePreference(false, 1, $invisible)]);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('assertGrantedPermissions');
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member');
    $cache = $this->createMock(CachePort::class);
    $cache->method('get')->willReturn($previous);
    $cache->expects(self::once())->method('set')->with('messaging.presence.org.member', self::isString(), 90);
    $realtime = $this->createMock(PresenceRealtimePort::class);
    $realtime->expects(self::exactly($publications))->method('publish')->with('org', 'member');
    $handler = new PingPresenceHandler(new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)), $cache, $realtime, $this->createStub(LoggerPort::class), $preferences);
    self::assertSame('member', $handler(new PingPresenceCommand('user', 'org'))->memberId);
  }

  #[Test]
  public function rejectsInactiveOrOutsideMembership(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())->method('set');
    $handler = new PingPresenceHandler(new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)), $cache, $this->createStub(PresenceRealtimePort::class), $this->createStub(LoggerPort::class), $this->createStub(PresencePreferenceReaderPort::class));
    $this->expectException(MessagingNotFoundException::class);
    $handler(new PingPresenceCommand('user', 'org'));
  }

  #[Test]
  public function publicationFailureDoesNotFailTheHeartbeat(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member');
    $realtime = $this->createStub(PresenceRealtimePort::class);
    $realtime->method('publish')->willThrowException(new RuntimeException('Hub unavailable'));
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');
    $handler = new PingPresenceHandler(new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)), $this->createStub(CachePort::class), $realtime, $logger, $this->createStub(PresencePreferenceReaderPort::class));
    self::assertSame('member', $handler(new PingPresenceCommand('user', 'org'))->memberId);
  }
}
