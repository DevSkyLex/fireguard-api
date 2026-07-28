<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
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

use function iterator_to_array;

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

    $queryResult = new ListUserNotificationsResult(
      notifications: [
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
          organizationId: '550e8400-e29b-41d4-a716-446655442299',
        ),
      ],
      total: 1,
      limit: 20,
      offset: 0,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUserNotificationsQuery $query): bool {
        return '550e8400-e29b-41d4-a716-446655442200' === $query->userId
          && true === $query->onlyUnread
          && '550e8400-e29b-41d4-a716-446655442299' === $query->organizationId;
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
          'organization' => '550e8400-e29b-41d4-a716-446655442299',
        ],
      ],
    );

    $items = iterator_to_array($outputs);
    self::assertCount(1, $items);
    self::assertInstanceOf(NotificationOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655442201', $items[0]->id);
    self::assertFalse($items[0]->isRead);
    self::assertSame(['email', 'mercure'], $items[0]->channels);
    self::assertSame('550e8400-e29b-41d4-a716-446655442299', $items[0]->organizationId);
  }

  /**
   * A client predating the paginated collection still sends a bare `limit`.
   * Without the alias it would silently receive the DEFAULT page size while
   * its own paginator computes offsets for the size it asked for — skipping
   * and duplicating rows instead of failing visibly.
   */
  #[Test]
  public function testProvideHonoursTheLegacyLimitParameterAsItemsPerPage(): void
  {
    $captured = null;

    $provider = new ListNotificationsProvider(
      queryBus: $this->queryBusCapturing($captured),
      security: $this->securityForUser('550e8400-e29b-41d4-a716-446655442200'),
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: ['filters' => ['limit' => '48']],
    );

    self::assertInstanceOf(ListUserNotificationsQuery::class, $captured);
    self::assertSame(48, $captured->pagination->limit);
  }

  /**
   * `itemsPerPage` is the canonical parameter and must win, so a client that
   * migrates to it is never second-guessed by a stale `limit` left in place.
   */
  #[Test]
  public function testProvidePrefersItemsPerPageOverTheLegacyLimit(): void
  {
    $captured = null;

    $provider = new ListNotificationsProvider(
      queryBus: $this->queryBusCapturing($captured),
      security: $this->securityForUser('550e8400-e29b-41d4-a716-446655442200'),
    );

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: ['filters' => ['limit' => '48', 'itemsPerPage' => '12']],
    );

    self::assertInstanceOf(ListUserNotificationsQuery::class, $captured);
    self::assertSame(12, $captured->pagination->limit);
  }

  #[Test]
  public function testProvideReturnsAllNotificationsWhenNoOrganizationFilterIsGiven(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442210'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListUserNotificationsQuery $query): bool => null === $query->organizationId))
      ->willReturn(new ListUserNotificationsResult(notifications: [], total: 0, limit: 20, offset: 0));

    $provider = new ListNotificationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $outputs = $provider->provide(operation: new GetCollection());

    self::assertCount(0, iterator_to_array($outputs));
  }

  #[Test]
  public function testProvideDerivesPaginationFromPageAndItemsPerPageFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442220'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListUserNotificationsQuery $query): bool {
        return 10 === $query->pagination->limit
          && 10 === $query->pagination->offset;
      }))
      ->willReturn(new ListUserNotificationsResult(notifications: [], total: 25, limit: 10, offset: 10));

    $provider = new ListNotificationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      context: [
        'filters' => [
          'page' => '2',
          'itemsPerPage' => '10',
        ],
      ],
    );

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(25.0, $output->getTotalItems());
    self::assertSame(2.0, $output->getCurrentPage());
    self::assertSame(10.0, $output->getItemsPerPage());
  }

  #[Test]
  public function testProvideTreatsANonStringUnreadOnlyFilterAsFalse(): void
  {
    $captured = null;
    $provider = new ListNotificationsProvider(
      queryBus: $this->queryBusCapturing($captured),
      security: $this->securityForUser('550e8400-e29b-41d4-a716-446655442210'),
    );

    $provider->provide(
      operation: new GetCollection(),
      context: ['filters' => ['unreadOnly' => 1]],
    );

    self::assertInstanceOf(ListUserNotificationsQuery::class, $captured);
    self::assertFalse($captured->onlyUnread);
  }

  #[Test]
  public function testProvideHonoursAStringUnreadOnlyFilter(): void
  {
    $captured = null;
    $provider = new ListNotificationsProvider(
      queryBus: $this->queryBusCapturing($captured),
      security: $this->securityForUser('550e8400-e29b-41d4-a716-446655442211'),
    );

    $provider->provide(
      operation: new GetCollection(),
      context: ['filters' => ['unreadOnly' => 'TRUE']],
    );

    self::assertInstanceOf(ListUserNotificationsQuery::class, $captured);
    self::assertTrue($captured->onlyUnread);
  }

  /**
   * Method securityForUser.
   *
   * @param string $userId the authenticated user identifier
   *
   * @return Security a security stub returning that user
   */
  private function securityForUser(string $userId): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser($userId));

    return $security;
  }

  /**
   * Method queryBusCapturing.
   *
   * @param ?ListUserNotificationsQuery $captured receives the dispatched query
   *
   * @return QueryBusPort a query-bus stub returning an empty result
   */
  private function queryBusCapturing(?ListUserNotificationsQuery &$captured): QueryBusPort
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (ListUserNotificationsQuery $query) use (&$captured): ListUserNotificationsResult {
        $captured = $query;

        return new ListUserNotificationsResult(notifications: [], total: 0, limit: $query->pagination->limit, offset: 0);
      },
    );

    return $queryBus;
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
