<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\EventHandler;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\SentNotification;
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\ValueObject\Uuid;
use User\Application\EventHandler\UserEmailVerifiedEventHandler;
use User\Domain\Event\UserEmailVerifiedEvent;

/**
 * Test UserEmailVerifiedEventHandlerTest.
 *
 * @category EventHandler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserEmailVerifiedEventHandler::class)]
final class UserEmailVerifiedEventHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeSendsMercureNotificationAndLogsUserEmailVerified(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return NotificationType::USER_EMAIL_VERIFIED === $request->type
          && 'Your email has been verified' === $request->subject
          && 'Your email address has been verified successfully.' === $request->body
          && [NotificationChannel::MERCURE] === $request->channels
          && '2024-01-01T00:00:00+00:00' === ($request->payload['verifiedAt'] ?? null)
          && 'user-1' === $request->recipientUserId
          && null === $request->recipientEmail;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449001',
        type: NotificationType::USER_EMAIL_VERIFIED,
        subject: 'Your email has been verified',
        body: 'Your email address has been verified successfully.',
        channels: [NotificationChannel::MERCURE->value],
        payload: ['verifiedAt' => '2024-01-01T00:00:00+00:00'],
        channelDelivery: [NotificationChannel::MERCURE->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: 'user-1',
      ));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'User email verified',
        [
          'user_id' => 'user-1',
          'email' => 'jdoe@example.com',
        ],
      );
    $logger->expects(self::never())
      ->method('warning');

    $handler = new UserEmailVerifiedEventHandler($notificationPort, $logger);

    $event = new UserEmailVerifiedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000002'),
      userId: 'user-1',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    $handler($event);
  }

  #[Test]
  public function testInvokeLogsWarningWhenNotificationDispatchFails(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Mercure hub unavailable.'));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Failed to send user email verified notification.',
        [
          'user_id' => 'user-1',
          'error' => 'Mercure hub unavailable.',
        ],
      );
    $logger->expects(self::once())
      ->method('info');

    $handler = new UserEmailVerifiedEventHandler($notificationPort, $logger);

    $event = new UserEmailVerifiedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000003'),
      userId: 'user-1',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    $handler($event);
  }
  // #endregion
}
