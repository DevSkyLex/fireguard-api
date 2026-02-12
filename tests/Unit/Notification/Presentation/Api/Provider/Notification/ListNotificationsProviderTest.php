<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Notification\Application\UseCase\Query\Notification\ListUserNotifications\{ListUserNotificationsQuery, ListUserNotificationsResult};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Notification\Presentation\Api\Provider\Notification\ListNotificationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListNotificationsProvider::class)]
final class ListNotificationsProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListNotificationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsNotificationsAndAppliesFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442200'));

    $queryResult = new ListUserNotificationsResult([
      new GetUserNotificationResult(
        id: '550e8400-e29b-41d4-a716-446655442201',
        type: 'organization.invitation',
        subject: 'Invitation',
        body: '<p>Body</p>',
        channels: ['email', 'mercure'],
        payload: ['organizationName' => 'Fireguard HQ'],
        isRead: false,
        createdAt: new DateTimeImmutable('2026-02-11T10:00:00+00:00'),
        readAt: null,
      ),
    ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUserNotificationsQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655442200' === $query->userId
          && true === $query->onlyUnread
          && 100 === $query->limit;
      }))
      ->willReturn($queryResult);

    $provider = new ListNotificationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $outputs = $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: [
        'filters' => [
          'unreadOnly' => 'yes',
          'limit' => '999',
        ],
      ],
    );

    self::assertCount(1, $outputs);
    self::assertInstanceOf(NotificationOutput::class, $outputs[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655442201', $outputs[0]->id);
    self::assertFalse($outputs[0]->isRead);
    self::assertSame(['email', 'mercure'], $outputs[0]->channels);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
