<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Presence\GetPresence;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Messaging\Application\UseCase\Query\Presence\GetPresence\{GetPresenceHandler, GetPresenceQuery};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test GetPresenceHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPresenceHandler::class)]
final class GetPresenceHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string ONLINE_MEMBER_ID = 'member-online';

  private const string OFFLINE_MEMBER_ID = 'member-offline';

  #[Test]
  public function testInvokeResolvesOnlineAndOfflinePresenceFromTheCache(): void
  {
    $onlineKey = MessagingPresenceCacheKeys::key(self::ORG_ID, self::ONLINE_MEMBER_ID);
    $offlineKey = MessagingPresenceCacheKeys::key(self::ORG_ID, self::OFFLINE_MEMBER_ID);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(2))
      ->method('get')
      ->willReturnCallback(static fn (string $key): ?string => match ($key) {
        $onlineKey => '2026-01-01T00:00:00+00:00',
        $offlineKey => null,
        default => null,
      });

    $handler = $this->handler($cache);

    $result = $handler->__invoke(new GetPresenceQuery(self::USER_ID, self::ORG_ID, [self::ONLINE_MEMBER_ID, self::OFFLINE_MEMBER_ID]));

    self::assertCount(2, $result->presences);

    self::assertSame(self::ONLINE_MEMBER_ID, $result->presences[0]->memberId);
    self::assertTrue($result->presences[0]->online);
    self::assertSame('2026-01-01T00:00:00+00:00', $result->presences[0]->lastSeenAt);

    self::assertSame(self::OFFLINE_MEMBER_ID, $result->presences[1]->memberId);
    self::assertFalse($result->presences[1]->online);
    self::assertNull($result->presences[1]->lastSeenAt);
  }

  #[Test]
  public function testInvokeReturnsAnEmptyListForAnEmptyMemberIdList(): void
  {
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())->method('get');

    $handler = $this->handler($cache);

    $result = $handler->__invoke(new GetPresenceQuery(self::USER_ID, self::ORG_ID, []));

    self::assertSame([], $result->presences);
  }

  #[Test]
  public function testInvokeThrowsWhenTheMessagingReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $handler = new GetPresenceHandler($accessPolicy, $this->createStub(CachePort::class));

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetPresenceQuery(self::USER_ID, self::ORG_ID, [self::ONLINE_MEMBER_ID]));
  }

  private function handler(CachePort $cache): GetPresenceHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions');
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new GetPresenceHandler($accessPolicy, $cache);
  }
}
