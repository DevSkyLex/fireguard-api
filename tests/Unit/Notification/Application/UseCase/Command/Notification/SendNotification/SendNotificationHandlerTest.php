<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\UseCase\Command\Notification\SendNotification;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\NotificationChannel;
use Notification\Application\Port\Outbound\{
  EmailNotificationChannelPort,
  MercureNotificationChannelPort,
  NotificationPreferenceRepositoryPort,
  NotificationRepositoryPort,
  RecipientDirectoryPort
};
use Notification\Application\UseCase\Command\Notification\SendNotification\{SendNotificationCommand, SendNotificationHandler, SendNotificationResult};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Domain\ValueObject\NotificationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
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
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
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
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
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
  public function testInvokeResolvesRecipientEmailFromUserIdThroughTheDirectory(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Notification $notification): bool => 'resolved@example.com' === (string) $notification->recipientEmail()
        && ['mercure', 'email'] === $notification->channels()));

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::once())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::once())->method('publish');

    $directory = $this->createMock(RecipientDirectoryPort::class);
    $directory->expects(self::once())
      ->method('emailForUserId')
      ->with('550e8400-e29b-41d4-a716-446655440001')
      ->willReturn('resolved@example.com');

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442020'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $directory,
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'intervention.comment_mention',
      subject: 'Mentioned in a comment',
      body: 'A teammate mentioned you.',
      channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655440001',
      recipientEmail: null,
    ));

    self::assertSame('resolved@example.com', $result->recipientEmail);
    self::assertSame(['mercure' => true, 'email' => true], $result->channelDelivery);
  }

  #[Test]
  public function testInvokeDropsTheEmailChannelWhenTheAddressCannotBeResolved(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Notification $notification): bool => null === $notification->recipientEmail()
        && ['mercure'] === $notification->channels()));

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::never())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::once())->method('publish');

    $directory = $this->createStub(RecipientDirectoryPort::class);
    $directory->method('emailForUserId')->willReturn(null);

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442030'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $directory,
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'maintenance.inspection_due',
      subject: 'Inspection due',
      body: 'An inspection is due soon.',
      channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655440002',
      recipientEmail: null,
    ));

    self::assertSame(['mercure' => true], $result->channelDelivery, 'the email channel is dropped, the rest still delivers');
  }

  #[Test]
  public function testInvokeStillThrowsForAnEmailOnlyRequestWithNoResolvableAddress(): void
  {
    $directory = $this->createStub(RecipientDirectoryPort::class);
    $directory->method('emailForUserId')->willReturn(null);

    $handler = new SendNotificationHandler(
      notificationRepository: $this->createStub(NotificationRepositoryPort::class),
      emailChannel: $this->createStub(EmailNotificationChannelPort::class),
      mercureChannel: $this->createStub(MercureNotificationChannelPort::class),
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $directory,
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Recipient email is required for email notifications.');

    $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: [NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655440003',
      recipientEmail: null,
    ));
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
      preferenceRepository: $this->createStub(NotificationPreferenceRepositoryPort::class),
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
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

  #[Test]
  public function testInvokeDeliversOnEveryChannelWhenTheUserHasNoPreferenceRows(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::once())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::once())->method('publish');

    /** @var NotificationPreferenceRepositoryPort&MockObject $preferenceRepository */
    $preferenceRepository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $preferenceRepository->expects(self::once())
      ->method('findByUserIdAndCategory')
      ->with('550e8400-e29b-41d4-a716-446655442900', 'organization')
      ->willReturn(null);

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442901'));

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $preferenceRepository,
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442900',
      recipientEmail: 'member@example.com',
    ));

    self::assertSame(['mercure' => true, 'email' => true], $result->channelDelivery, 'an absent preference row means every channel stays enabled');
  }

  #[Test]
  public function testInvokeDeliversWhenTheCategoryIsUnknown(): void
  {
    // NotificationType::isValid() is advisory on purpose: an unknown
    // category must default to enabled, not silently dropped.
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::once())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createStub(MercureNotificationChannelPort::class);

    /** @var NotificationPreferenceRepositoryPort&MockObject $preferenceRepository */
    $preferenceRepository = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $preferenceRepository->expects(self::once())
      ->method('findByUserIdAndCategory')
      ->with('550e8400-e29b-41d4-a716-446655442910', 'brandNewFeature')
      ->willReturn(null);

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442911'));

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $preferenceRepository,
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'brandNewFeature.rolled_out',
      subject: 'New feature',
      body: '<p>Body</p>',
      channels: [NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442910',
      recipientEmail: 'member@example.com',
    ));

    self::assertSame(['email' => true], $result->channelDelivery, 'an unknown category still defaults to enabled');
  }

  #[Test]
  public function testInvokeSkipsEmailButStillPersistsWhenEmailIsDisabledForTheCategory(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Notification $notification): bool => ['mercure', 'email'] === $notification->channels()));

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::never())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::once())->method('publish');

    $preference = NotificationPreference::reconstitute(
      userId: '550e8400-e29b-41d4-a716-446655442920',
      category: 'organization',
      emailEnabled: false,
      mercureEnabled: true,
      updatedAt: new DateTimeImmutable(),
    );

    /** @var NotificationPreferenceRepositoryPort&Stub $preferenceRepository */
    $preferenceRepository = $this->createStub(NotificationPreferenceRepositoryPort::class);
    $preferenceRepository->method('findByUserIdAndCategory')->willReturn($preference);

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('info');
    $logger->expects(self::never())->method('warning');

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442922'));

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $preferenceRepository,
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
      logger: $logger,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442920',
      recipientEmail: 'member@example.com',
    ));

    self::assertSame(['mercure' => true, 'email' => false], $result->channelDelivery, 'email delivery is suppressed but the row is still saved above');
  }

  #[Test]
  public function testInvokeDoesNotThrowWhenEveryChannelIsSuppressedByPreference(): void
  {
    /** @var NotificationRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var EmailNotificationChannelPort&MockObject $emailChannel */
    $emailChannel = $this->createMock(EmailNotificationChannelPort::class);
    $emailChannel->expects(self::never())->method('send');

    /** @var MercureNotificationChannelPort&MockObject $mercureChannel */
    $mercureChannel = $this->createMock(MercureNotificationChannelPort::class);
    $mercureChannel->expects(self::never())->method('publish');

    $preference = NotificationPreference::reconstitute(
      userId: '550e8400-e29b-41d4-a716-446655442930',
      category: 'organization',
      emailEnabled: false,
      mercureEnabled: false,
      updatedAt: new DateTimeImmutable(),
    );

    /** @var NotificationPreferenceRepositoryPort&Stub $preferenceRepository */
    $preferenceRepository = $this->createStub(NotificationPreferenceRepositoryPort::class);
    $preferenceRepository->method('findByUserIdAndCategory')->willReturn($preference);

    /** @var UuidFactory&Stub $uuidFactory */
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new NotificationId('550e8400-e29b-41d4-a716-446655442931'));

    $handler = new SendNotificationHandler(
      notificationRepository: $repository,
      emailChannel: $emailChannel,
      mercureChannel: $mercureChannel,
      preferenceRepository: $preferenceRepository,
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new SendNotificationCommand(
      type: 'organization.invitation',
      subject: 'Invitation',
      body: '<p>Body</p>',
      channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL],
      payload: [],
      recipientUserId: '550e8400-e29b-41d4-a716-446655442930',
      recipientEmail: 'member@example.com',
    ));

    self::assertInstanceOf(SendNotificationResult::class, $result, 'opting out of every channel is a legitimate outcome, not an error');
    self::assertSame(['mercure' => false, 'email' => false], $result->channelDelivery);
  }
}
