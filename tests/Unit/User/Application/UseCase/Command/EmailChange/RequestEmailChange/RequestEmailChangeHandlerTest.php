<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\EmailChange\RequestEmailChange;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\GeoIp\IpLocation;
use Shared\Application\Contract\Http\RequestOrigin;
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort, UuidGeneratorPort};
use Shared\Application\Port\Outbound\{GeoIpLookupPort, RequestOriginPort};
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\{EmailChangeRequestRepositoryPort, UserRepositoryPort};
use User\Application\Service\{EmailChangeNotifier, EmailChangeTokenHasher};
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\{
  RequestEmailChangeCommand,
  RequestEmailChangeHandler,
  RequestEmailChangeResult
};
use User\Domain\Event\UserEmailChangeRequestedEvent;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(RequestEmailChangeHandler::class)]
final class RequestEmailChangeHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string REQUEST_ID = '00000000-0000-4000-a000-0000000000aa';

  private const string CURRENT_PASSWORD = 'CurrentP@ssw0rd!';

  private const string NEW_EMAIL = 'new-address@example.com';

  /**
   * Collected domain events (by reference through the dispatcher stub).
   *
   * @var list<object>
   */
  private array $dispatchedEvents = [];

  // #region Tests
  /**
   * @return iterable<string, array{bool, bool}>
   */
  public static function enrichmentFailures(): iterable
  {
    yield 'origin only' => [true, false];
    yield 'geography only' => [false, true];
    yield 'both unavailable' => [true, true];
  }

  #[Test]
  #[DataProvider('enrichmentFailures')]
  public function testOptionalEnrichmentFailuresDoNotSuppressEmails(bool $originFails, bool $geoFails): void
  {
    $user = $this->makeActiveUser();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn($user);
    $users->method('existsByEmail')->willReturn(false);
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->expects(self::once())->method('save');
    $origin = $this->createMock(RequestOriginPort::class);
    $originCall = $origin->expects(self::once())->method('current');
    if ($originFails) {
      $originCall->willThrowException(new RuntimeException('origin unavailable'));
    } else {
      $originCall->willReturn(new RequestOrigin('9.9.9.9', 'Firefox', 'Linux', 'fr'));
    }
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geoCall = $geo->expects(self::once())->method('locate')->with('8.8.8.8');
    if ($geoFails) {
      $geoCall->willThrowException(new RuntimeException('lookup unavailable'));
    } else {
      $geoCall->willReturn(new IpLocation('FR', 'Paris'));
    }
    $sent = [];
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')->willReturnCallback(
      function (SendNotificationRequest $request) use (&$sent): SentNotification {
        $sent[] = $request;

        return $this->sentNotification($request);
      },
    );
    $handler = $this->makeHandler(userRepo: $users, requests: $requests, notifications: $notifications, geoIp: $geo, requestOrigin: $origin);
    $result = $handler(new RequestEmailChangeCommand(self::USER_ID, self::NEW_EMAIL, self::CURRENT_PASSWORD, ipAddress: '8.8.8.8'));
    self::assertTrue($result->success);
    self::assertSame(self::NEW_EMAIL, $sent[0]->recipientEmail);
    self::assertSame('jdoe@example.com', $sent[1]->recipientEmail);
    foreach ($sent as $notification) {
      $delivery = $notification->deliveryPayload[NotificationChannel::EMAIL->value];
      self::assertIsArray($delivery);
      self::assertIsArray($delivery['context']);
      $details = $delivery['context']['requestDetails'];
      self::assertInstanceOf(EmailRequestDetails::class, $details);
      self::assertSame($originFails ? null : 'Firefox', $details->browser);
      self::assertSame($originFails ? null : 'Linux', $details->operatingSystem);
      self::assertSame($geoFails ? null : 'Paris', $details->location?->city);
    }
  }

  #[Test]
  public function testConfirmationDeliveryFailureStillFailsTheUseCase(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn($this->makeActiveUser());
    $users->method('existsByEmail')->willReturn(false);
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())->method('send')->willThrowException(new RuntimeException('delivery failed'));
    $handler = $this->makeHandler(userRepo: $users, notifications: $notifications);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('delivery failed');
    $handler($this->command());
  }

  #[Test]
  public function testPendingNoticeDeliveryFailureKeepsSuccessAndIsLogged(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn($this->makeActiveUser());
    $users->method('existsByEmail')->willReturn(false);
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')->willReturnCallback(function (SendNotificationRequest $request): SentNotification {
      if ('jdoe@example.com' === $request->recipientEmail) {
        throw new RuntimeException('delivery failed');
      }

      return $this->sentNotification($request);
    });
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning')->with('Email change pending notice could not be sent.', ['user_id' => self::USER_ID, 'error' => RuntimeException::class]);
    $result = ($this->makeHandler(userRepo: $users, notifications: $notifications, logger: $logger))($this->command());
    self::assertTrue($result->success);
  }

  #[Test]
  public function testFailsWhenUserNotFound(): void
  {
    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn(null);

    $result = ($this->makeHandler(userRepo: $userRepo))(
      $this->command()
    );

    self::assertFalse($result->success);
    self::assertSame(RequestEmailChangeResult::ERROR_USER_NOT_FOUND, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenCurrentPasswordIsIncorrect(): void
  {
    $user = $this->makeActiveUser();

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    // The failed attempt must be persisted (lockout counter)
    $userRepo->expects(self::once())->method('save')->with($user);

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->expects(self::never())->method('save');

    /** @var NotificationPort&MockObject $notifications */
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = ($this->makeHandler(userRepo: $userRepo, requests: $requests, notifications: $notifications))(
      $this->command(currentPassword: 'WrongP@ssw0rd!')
    );

    self::assertFalse($result->success);
    self::assertSame(RequestEmailChangeResult::ERROR_INVALID_PASSWORD, $result->errorCode);
    self::assertCount(0, $this->dispatchedEvents);
  }

  #[Test]
  public function testFailsNeutrallyWhenEmailIsAlreadyTaken(): void
  {
    $user = $this->makeActiveUser();

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(true);

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->expects(self::never())->method('save');

    /** @var NotificationPort&MockObject $notifications */
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = ($this->makeHandler(userRepo: $userRepo, requests: $requests, notifications: $notifications))(
      $this->command()
    );

    self::assertFalse($result->success);
    self::assertSame(RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE, $result->errorCode);
    self::assertSame('This email address cannot be used.', $result->message);
  }

  #[Test]
  public function testFailsWithTheSameNeutralAnswerWhenNewEmailEqualsCurrent(): void
  {
    $user = $this->makeActiveUser();

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->expects(self::never())->method('save');

    $result = ($this->makeHandler(userRepo: $userRepo, requests: $requests))(
      $this->command(newEmail: 'jdoe@example.com')
    );

    self::assertFalse($result->success);
    self::assertSame(RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE, $result->errorCode);
    // Byte-identical message to the "taken" case: no oracle.
    self::assertSame('This email address cannot be used.', $result->message);
  }

  #[Test]
  public function testFailsNeutrallyWhenNewEmailIsMalformed(): void
  {
    $user = $this->makeActiveUser();

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);

    $result = ($this->makeHandler(userRepo: $userRepo))(
      $this->command(newEmail: 'not-an-email')
    );

    self::assertFalse($result->success);
    self::assertSame(RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE, $result->errorCode);
  }

  #[Test]
  public function testCreatesRequestReplacingPendingOneAndSendsBothEmails(): void
  {
    $user = $this->makeActiveUser();
    $now = new DateTimeImmutable('2026-08-28 10:00:00');

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);
    $userRepo->expects(self::once())->method('save')->with($user);

    $savedRequest = null;
    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    // The previous pending request is swept before the new one is saved.
    $requests->expects(self::once())->method('removePendingForUser')->with($user->id());
    $requests->expects(self::once())->method('save')
      ->willReturnCallback(static function (EmailChangeRequest $request) use (&$savedRequest): void {
        $savedRequest = $request;
      });

    $sent = [];
    /** @var NotificationPort&MockObject $notifications */
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))->method('send')
      ->willReturnCallback(function (SendNotificationRequest $request) use (&$sent): SentNotification {
        $sent[] = $request;

        return $this->sentNotification($request);
      });

    $handler = $this->makeHandler(
      userRepo: $userRepo,
      requests: $requests,
      notifications: $notifications,
      now: $now,
      geoIp: $this->geoIp(),
    );

    $result = $handler(new RequestEmailChangeCommand(userId: self::USER_ID, newEmail: self::NEW_EMAIL, currentPassword: self::CURRENT_PASSWORD, ipAddress: '8.8.8.8'));

    self::assertTrue($result->success);
    self::assertInstanceOf(EmailChangeRequest::class, $savedRequest);
    self::assertSame(self::NEW_EMAIL, $savedRequest->newEmail()->value);
    self::assertSame('jdoe@example.com', $savedRequest->currentEmail()->value);
    self::assertFalse($savedRequest->isConfirmed());
    // TTL is exactly one hour.
    self::assertEquals($now->modify('+3600 seconds'), $savedRequest->expiresAt());
    self::assertEquals($savedRequest->expiresAt(), $result->expiresAt);
    // The stored value is a SHA-256 hex digest, never the raw token.
    self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $savedRequest->tokenHash());

    // Two emails: confirmation to the NEW address, alert to the OLD one.
    self::assertSame(2, count($sent));
    self::assertSame(self::NEW_EMAIL, $sent[0]->recipientEmail);
    self::assertSame('jdoe@example.com', $sent[1]->recipientEmail);
    // The confirmation email must carry the raw token; the alert must not.
    /** @var array{template: string, context: array{confirmUrl: string}} $confirmDelivery */
    $confirmDelivery = $sent[0]->deliveryPayload[NotificationChannel::EMAIL->value];
    self::assertStringContainsString('token=', $confirmDelivery['context']['confirmUrl']);
    self::assertStringNotContainsString($savedRequest->tokenHash(), $confirmDelivery['context']['confirmUrl']);

    foreach ($sent as $notification) {
      $delivery = $notification->deliveryPayload[NotificationChannel::EMAIL->value];
      self::assertIsArray($delivery);
      self::assertIsArray($delivery['context']);
      $context = $delivery['context']['requestDetails'];
      self::assertInstanceOf(EmailRequestDetails::class, $context);
      self::assertSame('Paris', $context->location?->city);
      self::assertArrayNotHasKey('requestDetails', $notification->payload);
      self::assertStringNotContainsString('Paris', json_encode($notification->payload, JSON_THROW_ON_ERROR));
      self::assertStringNotContainsString('Paris', $notification->body);
    }

    // The requested event is dispatched after the save.
    self::assertCount(1, $this->dispatchedEvents);
    $event = $this->dispatchedEvents[0];
    self::assertInstanceOf(UserEmailChangeRequestedEvent::class, $event);
    self::assertSame(self::USER_ID, $event->userId);
    self::assertSame('jdoe@example.com', $event->currentEmail);
    self::assertSame(self::NEW_EMAIL, $event->newEmail);
  }
  // #endregion

  // #region Helpers
  private function command(
    string $newEmail = self::NEW_EMAIL,
    string $currentPassword = self::CURRENT_PASSWORD,
  ): RequestEmailChangeCommand {
    return new RequestEmailChangeCommand(
      userId: self::USER_ID,
      newEmail: $newEmail,
      currentPassword: $currentPassword,
    );
  }

  private function makeHandler(
    UserRepositoryPort $userRepo,
    ?EmailChangeRequestRepositoryPort $requests = null,
    ?NotificationPort $notifications = null,
    ?DateTimeImmutable $now = null,
    ?GeoIpLookupPort $geoIp = null,
    ?RequestOriginPort $requestOrigin = null,
    ?LoggerPort $logger = null,
  ): RequestEmailChangeHandler {
    $this->dispatchedEvents = [];

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now ?? new DateTimeImmutable());

    $uuid = $this->createStub(UuidGeneratorPort::class);
    $uuid->method('generate')->willReturn(self::REQUEST_ID);

    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatch')->willReturnCallback(function (object $event): void {
      $this->dispatchedEvents[] = $event;
    });

    if (null === $notifications) {
      $notifications = $this->createStub(NotificationPort::class);
      $notifications->method('send')->willReturnCallback(
        fn (SendNotificationRequest $request): SentNotification => $this->sentNotification($request),
      );
    }

    return new RequestEmailChangeHandler(
      userRepository: $userRepo,
      emailChangeRequests: $requests ?? $this->createStub(EmailChangeRequestRepositoryPort::class),
      tokenHasher: new EmailChangeTokenHasher(),
      notifier: new EmailChangeNotifier(
        notificationPort: $notifications,
        frontendUrl: 'https://app.fireguard.test',
        translator: new \Shared\Infrastructure\Symfony\Adapter\Outbound\TranslatorAdapter(EmailTranslatorTestFactory::create()),
      ),
      uuidGenerator: $uuid,
      clock: $clock,
      eventDispatcher: $dispatcher,
      eventIdProvider: new TestEventIdProvider(),
      geoIp: $geoIp,
      requestOrigin: $requestOrigin,
      logger: $logger ?? $this->createStub(LoggerPort::class),
    );
  }

  private function geoIp(): GeoIpLookupPort
  {
    $geo = $this->createMock(GeoIpLookupPort::class);
    $geo->expects(self::once())->method('locate')->with('8.8.8.8')->willReturn(new IpLocation('FR', 'Paris'));

    return $geo;
  }

  private function sentNotification(SendNotificationRequest $request): SentNotification
  {
    return new SentNotification(
      id: '00000000-0000-4000-a000-0000000000ff',
      type: $request->type,
      subject: $request->subject,
      body: $request->body,
      channels: [NotificationChannel::EMAIL->value],
      payload: $request->payload,
      channelDelivery: [NotificationChannel::EMAIL->value => true],
      createdAt: new DateTimeImmutable(),
      recipientEmail: $request->recipientEmail,
    );
  }

  private function makeActiveUser(): User
  {
    $user = User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain(self::CURRENT_PASSWORD),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
    $user->verifyEmail(new TestEventIdProvider());
    $user->activate();
    $user->releaseEvents();

    return $user;
  }
  // #endregion
}
