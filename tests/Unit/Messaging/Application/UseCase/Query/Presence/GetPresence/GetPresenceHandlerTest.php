<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Presence\GetPresence;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Presence\GetPresence\{GetPresenceHandler, GetPresenceQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;
use User\Application\Contract\Presence\PresencePreference;
use User\Application\Port\Inbound\PresencePreferenceReaderPort;

use function array_column;
use function str_ends_with;

final class GetPresenceHandlerTest extends TestCase
{
  /**
   * @return iterable<string, array{string}>
   */
  public static function readPermissions(): iterable
  {
    yield 'directory only' => ['organization.members.read'];
    yield 'messaging only' => ['organization.messaging.read'];
  }

  #[Test]
  #[DataProvider('readPermissions')]
  public function resolvesThreeStatesInBatchesAndNeverReadsOutsideOrInactiveMembers(string $permission): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $requested): bool => $permission === $requested);
    $members = $this->createMock(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('caller');
    $members->expects(self::once())->method('activeUserIdsForMembers')->with('org', ['active', 'busy', 'expired', 'outside', 'inactive', 'invisible'])
      ->willReturn(['active' => 'u1', 'busy' => 'u2', 'expired' => 'u3', 'invisible' => 'u4']);
    $preferences = $this->createMock(PresencePreferenceReaderPort::class);
    $preferences->expects(self::once())->method('readMany')->with(['u1', 'u2', 'u3', 'u4'])
      ->willReturn(['u2' => new PresencePreference(true, 1), 'u3' => new PresencePreference(true, 5), 'u4' => new PresencePreference(true, 6, true)]);
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(4))->method('get')->willReturnCallback(static fn (string $key): ?string => str_ends_with($key, '.expired') ? null : '2026-09-26T00:00:00+00:00');
    $handler = new GetPresenceHandler(new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)), $cache, $members, $preferences);
    $rows = $handler(new GetPresenceQuery('user', 'org', ['active', 'busy', 'expired', 'outside', 'inactive', 'invisible', 'busy']))->presences;
    self::assertSame(['active', 'do_not_disturb', 'offline', 'offline', 'offline', 'offline'], array_column($rows, 'status'));
    self::assertSame([true, true, false, false, false, false], array_column($rows, 'online'));
    self::assertNull($rows[2]->lastSeenAt);
    self::assertNull($rows[3]->lastSeenAt);
    self::assertNull($rows[5]->lastSeenAt);
  }

  #[Test]
  public function rejectsActiveMemberWithoutEitherReadPermission(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member');
    $this->expectException(MessagingAccessDeniedException::class);
    $this->deniedHandler($members)(new GetPresenceQuery('user', 'org', ['member']));
  }

  #[Test]
  public function hidesUnknownOrganizationFromOutsider(): void
  {
    $this->expectException(MessagingNotFoundException::class);
    $this->deniedHandler($this->createStub(MessagingMemberDirectoryPort::class))(new GetPresenceQuery('user', 'org', ['member']));
  }

  private function deniedHandler(MessagingMemberDirectoryPort $members): GetPresenceHandler
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())->method('get');
    $preferences = $this->createMock(PresencePreferenceReaderPort::class);
    $preferences->expects(self::never())->method('readMany');

    return new GetPresenceHandler(new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)), $cache, $members, $preferences);
  }
}
