<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead;

use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead\{MarkAllNotificationsAsReadCommand, MarkAllNotificationsAsReadHandler, MarkAllNotificationsAsReadResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkAllNotificationsAsReadHandler::class)]
final class MarkAllNotificationsAsReadHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeDelegatesToBulkUpdateAndReturnsAffectedCount(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('markAllAsReadForUser')
      ->with('550e8400-e29b-41d4-a716-446655442600', null)
      ->willReturn(4);

    $handler = new MarkAllNotificationsAsReadHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new MarkAllNotificationsAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442600',
    ));

    self::assertInstanceOf(MarkAllNotificationsAsReadResult::class, $result);
    self::assertSame(4, $result->count);
  }

  #[Test]
  public function testInvokeScopesUpdateToOrganizationWhenProvided(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('markAllAsReadForUser')
      ->with('550e8400-e29b-41d4-a716-446655442601', '550e8400-e29b-41d4-a716-446655442699')
      ->willReturn(1);

    $handler = new MarkAllNotificationsAsReadHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new MarkAllNotificationsAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442601',
      organizationId: '550e8400-e29b-41d4-a716-446655442699',
    ));

    self::assertSame(1, $result->count);
  }

  #[Test]
  public function testInvokeIsIdempotentWhenNothingIsUnread(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('markAllAsReadForUser')
      ->willReturn(0);

    $handler = new MarkAllNotificationsAsReadHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new MarkAllNotificationsAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442602',
    ));

    self::assertSame(0, $result->count);
  }
}
