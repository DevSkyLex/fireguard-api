<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead;

use DateTimeImmutable;
use Notification\Application\Exception\NotificationNotFoundException;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead\{MarkNotificationAsReadCommand, MarkNotificationAsReadHandler, MarkNotificationAsReadResult};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

#[CoversClass(MarkNotificationAsReadHandler::class)]
final class MarkNotificationAsReadHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeMarksUnreadNotificationAsRead(): void
  {
    $notification = Notification::create(
      id: NotificationId::fromString('550e8400-e29b-41d4-a716-446655442300'),
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: ['email'],
      payload: ['organizationName' => 'Fireguard HQ'],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442301',
      recipientEmail: new Email('member@example.com'),
    );

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->willReturn($notification);
    $repository->expects(self::once())
      ->method('save')
      ->with($notification);

    $handler = new MarkNotificationAsReadHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new MarkNotificationAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442301',
      notificationId: '550e8400-e29b-41d4-a716-446655442300',
    ));

    self::assertInstanceOf(MarkNotificationAsReadResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655442300', $result->id);
    self::assertTrue($result->isRead);
    self::assertNotNull($result->readAt);
  }

  #[Test]
  public function testInvokeDoesNotPersistWhenAlreadyRead(): void
  {
    $readAt = new DateTimeImmutable('2026-02-11T08:00:00+00:00');

    $notification = Notification::create(
      id: NotificationId::fromString('550e8400-e29b-41d4-a716-446655442310'),
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: ['email'],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442311',
      recipientEmail: null,
    );
    $notification->markAsRead($readAt);

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->willReturn($notification);
    $repository->expects(self::never())
      ->method('save');

    $handler = new MarkNotificationAsReadHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new MarkNotificationAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442311',
      notificationId: '550e8400-e29b-41d4-a716-446655442310',
    ));

    self::assertTrue($result->isRead);
    self::assertEquals($readAt, $result->readAt);
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenIdIsInvalid(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::never())->method('findByIdForUser');
    $repository->expects(self::never())->method('save');

    $handler = new MarkNotificationAsReadHandler(
      notificationRepository: $repository,
    );

    $this->expectException(NotificationNotFoundException::class);

    $handler->__invoke(new MarkNotificationAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442320',
      notificationId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenNotificationDoesNotExistForUser(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    $handler = new MarkNotificationAsReadHandler(
      notificationRepository: $repository,
    );

    $this->expectException(NotificationNotFoundException::class);

    $handler->__invoke(new MarkNotificationAsReadCommand(
      userId: '550e8400-e29b-41d4-a716-446655442330',
      notificationId: '550e8400-e29b-41d4-a716-446655442331',
    ));
  }
}
