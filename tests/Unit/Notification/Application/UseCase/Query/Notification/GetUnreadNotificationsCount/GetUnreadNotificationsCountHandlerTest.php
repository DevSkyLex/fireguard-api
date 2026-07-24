<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount;

use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount\{GetUnreadNotificationsCountHandler, GetUnreadNotificationsCountQuery, GetUnreadNotificationsCountResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetUnreadNotificationsCountHandler::class)]
final class GetUnreadNotificationsCountHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsUnreadCountForUser(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countUnreadByUserId')
      ->with('550e8400-e29b-41d4-a716-446655442500', null)
      ->willReturn(7);

    $handler = new GetUnreadNotificationsCountHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new GetUnreadNotificationsCountQuery(
      userId: '550e8400-e29b-41d4-a716-446655442500',
    ));

    self::assertInstanceOf(GetUnreadNotificationsCountResult::class, $result);
    self::assertSame(7, $result->count);
  }

  #[Test]
  public function testInvokeScopesCountToOrganizationWhenProvided(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countUnreadByUserId')
      ->with('550e8400-e29b-41d4-a716-446655442501', '550e8400-e29b-41d4-a716-446655442599')
      ->willReturn(2);

    $handler = new GetUnreadNotificationsCountHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new GetUnreadNotificationsCountQuery(
      userId: '550e8400-e29b-41d4-a716-446655442501',
      organizationId: '550e8400-e29b-41d4-a716-446655442599',
    ));

    self::assertSame(2, $result->count);
  }
}
