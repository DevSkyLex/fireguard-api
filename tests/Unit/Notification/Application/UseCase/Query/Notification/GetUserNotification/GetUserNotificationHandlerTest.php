<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Query\Notification\GetUserNotification;

use DateTimeImmutable;
use Notification\Application\Exception\NotificationNotFoundException;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\{GetUserNotificationHandler, GetUserNotificationQuery, GetUserNotificationResult};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

#[CoversClass(GetUserNotificationHandler::class)]
final class GetUserNotificationHandlerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655442301';

  private const string NOTIFICATION_ID = '550e8400-e29b-41d4-a716-446655442300';

  #[Test]
  public function testInvokeReturnsMappedReadNotification(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-10T09:00:00+00:00');
    $readAt = new DateTimeImmutable('2026-02-11T10:00:00+00:00');

    $notification = Notification::reconstitute(
      id: NotificationId::fromString(self::NOTIFICATION_ID),
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: ['email', 'in_app'],
      payload: ['organizationName' => 'Fireguard HQ'],
      createdAt: $createdAt,
      updatedAt: $readAt,
      recipientUserId: self::USER_ID,
      recipientEmail: new Email('member@example.com'),
      isRead: true,
      readAt: $readAt,
      organizationId: 'org-42',
    );

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->with(NotificationId::fromString(self::NOTIFICATION_ID), self::USER_ID)
      ->willReturn($notification);

    $handler = new GetUserNotificationHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new GetUserNotificationQuery(
      userId: self::USER_ID,
      notificationId: self::NOTIFICATION_ID,
    ));

    self::assertInstanceOf(GetUserNotificationResult::class, $result);
    self::assertSame(self::NOTIFICATION_ID, $result->id);
    self::assertSame('organization.invitation', $result->type);
    self::assertSame('Invitation', $result->subject);
    self::assertSame('<p>Body</p>', $result->body);
    self::assertSame(['email', 'in_app'], $result->channels);
    self::assertSame(['organizationName' => 'Fireguard HQ'], $result->payload);
    self::assertTrue($result->isRead);
    self::assertSame($createdAt, $result->createdAt);
    self::assertSame($readAt, $result->readAt);
    self::assertSame('org-42', $result->organizationId);
  }

  #[Test]
  public function testInvokeMapsUnreadNotificationWithoutOrganization(): void
  {
    $createdAt = new DateTimeImmutable('2026-03-01T12:00:00+00:00');

    $notification = Notification::reconstitute(
      id: NotificationId::fromString(self::NOTIFICATION_ID),
      type: 'billing.invoice',
      subject: 'Invoice available',
      body: 'Your invoice is ready.',
      channels: ['in_app'],
      payload: [],
      createdAt: $createdAt,
      updatedAt: $createdAt,
      recipientUserId: self::USER_ID,
      recipientEmail: null,
      isRead: false,
      readAt: null,
      organizationId: null,
    );

    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->with(NotificationId::fromString(self::NOTIFICATION_ID), self::USER_ID)
      ->willReturn($notification);

    $handler = new GetUserNotificationHandler(
      notificationRepository: $repository,
    );

    $result = $handler->__invoke(new GetUserNotificationQuery(
      userId: self::USER_ID,
      notificationId: self::NOTIFICATION_ID,
    ));

    self::assertFalse($result->isRead);
    self::assertNull($result->readAt);
    self::assertNull($result->organizationId);
    self::assertSame([], $result->payload);
    self::assertSame($createdAt, $result->createdAt);
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenNotificationDoesNotExistForUser(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByIdForUser')
      ->with(NotificationId::fromString(self::NOTIFICATION_ID), self::USER_ID)
      ->willReturn(null);

    $handler = new GetUserNotificationHandler(
      notificationRepository: $repository,
    );

    $this->expectException(NotificationNotFoundException::class);
    $this->expectExceptionMessage('Notification "' . self::NOTIFICATION_ID . '" not found.');

    $handler->__invoke(new GetUserNotificationQuery(
      userId: self::USER_ID,
      notificationId: self::NOTIFICATION_ID,
    ));
  }
}
