<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Presence\PingPresence;

use DateTimeImmutable;
use DateTimeInterface;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Messaging\Application\UseCase\Command\Presence\PingPresence\{PingPresenceCommand, PingPresenceHandler};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test PingPresenceHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PingPresenceHandler::class)]
final class PingPresenceHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeWritesThePresenceCacheEntryWithA90SecondTtl(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('set')
      ->with(
        MessagingPresenceCacheKeys::key(self::ORG_ID, self::MEMBER_ID),
        self::isString(),
        90,
      );

    $handler = $this->handler($members, $cache);

    $result = $handler->__invoke(new PingPresenceCommand(self::USER_ID, self::ORG_ID));

    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertInstanceOf(DateTimeImmutable::class, $result->lastSeenAt);
  }

  #[Test]
  public function testInvokeStoresAnIso8601FormattedTimestamp(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $storedValue = null;
    $cache = $this->createStub(CachePort::class);
    $cache->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$storedValue): void {
      $storedValue = $value;
    });

    $handler = $this->handler($members, $cache);
    $result = $handler->__invoke(new PingPresenceCommand(self::USER_ID, self::ORG_ID));

    self::assertSame($result->lastSeenAt->format(DateTimeInterface::ATOM), $storedValue);
  }

  #[Test]
  public function testInvokeThrowsWhenTheMessagingReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $handler = new PingPresenceHandler($accessPolicy, $this->createStub(CachePort::class));

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new PingPresenceCommand(self::USER_ID, self::ORG_ID));
  }

  private function handler(MessagingMemberDirectoryPort $members, CachePort $cache): PingPresenceHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions');
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new PingPresenceHandler($accessPolicy, $cache);
  }
}
