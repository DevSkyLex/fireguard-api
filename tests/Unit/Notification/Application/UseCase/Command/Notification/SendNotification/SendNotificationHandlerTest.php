<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\SendNotification;

use InvalidArgumentException;
use Notification\Application\Contract\Notification\NotificationChannel;
use Notification\Application\Port\Outbound\{EmailNotificationChannelPort, MercureNotificationChannelPort, NotificationRepositoryPort};
use Notification\Application\UseCase\Command\Notification\SendNotification\{SendNotificationCommand, SendNotificationHandler, SendNotificationResult};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\LoggerPort;

#[CoversClass(SendNotificationHandler::class)]
final class SendNotificationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeSendsEmailNotificationWithEphemeralDeliveryPayload(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Notification $notification): bool {
        return 'organization.invitation' === $notification->type()
          && 'Invitation to join Fireguard HQ' === $notification->subject()
          && 'member@example.com' === (string) $notification->recipientEmail()
          && '<p>Open invitation details.</p>' === $notification->body()
          && ['email'] === $notification->channels();
      }));

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::once())
      ->method('send')
      ->with(
        self::callback(static fn (Notification $notification): bool => '<p>Open invitation details.</p>' === $notification->body()),
        self::callback(static fn (array $channelPayload): bool => '<p>Use token ABC123.</p>' === ($channelPayload['body'] ?? null)),
      );

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::never())->method('publish');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(NotificationId::class)
      ->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442000'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation to join Fireguard HQ',
      body: '<p>Open invitation details.</p>',
      channels: [NotificationChannel::EMAIL],
      payload: ['organizationId' => '550e8400-e29b-41d4-a716-446655441900'],
      deliveryPayload: [
        'email' => [
          'body' => '<p>Use token ABC123.</p>',
        ],
      ],
      recipientUserId: null,
      recipientEmail: 'member@example.com',
    ));

    self::assertInstanceOf(SendNotificationResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655442000', $result->id);
    self::assertSame('organization.invitation', $result->type);
    self::assertSame(['email'], $result->channels);
    self::assertSame(['email' => true], $result->channelDelivery);
    self::assertSame('member@example.com', $result->recipientEmail);
    self::assertNull($result->recipientUserId);
  }

  #[Test]
  public function testInvokeDoesNotFailWhenEmailChannelThrowsException(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save');

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('SMTP unavailable'));

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::never())->method('publish');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(NotificationId::class)
      ->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442010'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation to join Fireguard HQ',
      body: '<p>Open invitation details.</p>',
      channels: [NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: null,
      recipientEmail: 'member@example.com',
    ));

    self::assertSame('550e8400-e29b-41d4-a716-446655442010', $result->id);
    self::assertSame(['email' => false], $result->channelDelivery);
  }

  #[Test]
  public function testInvokeThrowsWhenMercureChannelHasNoUserId(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::never())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::never())->method('publish');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Recipient userId is required for Mercure notifications.');

    $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: [NotificationChannel::MERCURE],
      payload: [],
      recipientUserId: null,
      recipientEmail: 'member@example.com',
    ));
  }
}
