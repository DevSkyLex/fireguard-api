<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\EmailChange\ConfirmEmailChange;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\{EmailChangeRequestRepositoryPort, UserRepositoryPort};
use User\Application\Service\{EmailChangeNotifier, EmailChangeTokenHasher};
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\{
  ConfirmEmailChangeCommand,
  ConfirmEmailChangeHandler,
  ConfirmEmailChangeResult
};
use User\Domain\Event\UserEmailChangeConfirmedEvent;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

#[CoversClass(ConfirmEmailChangeHandler::class)]
final class ConfirmEmailChangeHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string REQUEST_ID = '00000000-0000-4000-a000-0000000000aa';

  private const string RAW_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

  private const string NEW_EMAIL = 'new-address@example.com';

  /**
   * Collected domain events.
   *
   * @var array<int, object>
   */
  private array $dispatchedEvents = [];

  // #region Tests
  #[Test]
  public function testFailsNeutrallyWhenTokenIsUnknown(): void
  {
    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn(null);
    $requests->expects(self::never())->method('save');

    /** @var SessionRepositoryPort&MockObject $sessions */
    $sessions = $this->createMock(SessionRepositoryPort::class);
    $sessions->expects(self::never())->method('revokeAllForUser');

    $result = ($this->makeHandler(requests: $requests, sessions: $sessions))(
      new ConfirmEmailChangeCommand(token: 'unknown-token')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertSame('Invalid or expired email change token.', $result->message);
  }

  #[Test]
  public function testExpiredTokenIsNeverReturnedByTheActiveLookup(): void
  {
    // The repository's "active" lookup excludes expired rows, so the
    // handler sees null: same neutral refusal as an unknown token.
    // The domain guard is exercised separately below.
    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn(null);

    $result = ($this->makeHandler(requests: $requests))(
      new ConfirmEmailChangeCommand(token: self::RAW_TOKEN)
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testReusedTokenIsRefusedByTheDomainGuard(): void
  {
    // Defence in depth: even if a confirmed request leaked through the
    // lookup, the aggregate refuses to confirm twice — same message.
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $request->confirm($now->modify('-5 minutes'));

    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);

    $user = $this->makeActiveUser();
    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);

    $result = ($this->makeHandler(requests: $requests, userRepo: $userRepo, now: $now))(
      new ConfirmEmailChangeCommand(token: self::RAW_TOKEN)
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertSame('Invalid or expired email change token.', $result->message);
  }

  #[Test]
  public function testExpiredRequestIsRefusedByTheDomainGuard(): void
  {
    $now = new DateTimeImmutable('2026-08-28 12:00:01');
    $request = $this->makeRequest($now->modify('-2 hours'));

    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);

    $user = $this->makeActiveUser();
    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);

    $result = ($this->makeHandler(requests: $requests, userRepo: $userRepo, now: $now))(
      new ConfirmEmailChangeCommand(token: self::RAW_TOKEN)
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenAddressWasRegisteredMeanwhile(): void
  {
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));

    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);

    $user = $this->makeActiveUser();

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(true);
    $userRepo->expects(self::never())->method('save');

    $result = ($this->makeHandler(requests: $requests, userRepo: $userRepo, now: $now))(
      new ConfirmEmailChangeCommand(token: self::RAW_TOKEN)
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE, $result->errorCode);
  }

  #[Test]
  public function testAppliesChangeRevokesEverythingAndNotifiesOldAddress(): void
  {
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $user = $this->makeActiveUser();

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')
      ->with(new EmailChangeTokenHasher()->hash(self::RAW_TOKEN), $now)
      ->willReturn($request);
    // The confirmed (single-use) state is persisted through the atomic
    // conditional update — never through a plain save.
    $requests->expects(self::once())->method('confirmIfPending')
      ->with(self::REQUEST_ID, $now)
      ->willReturn(true);
    $requests->expects(self::never())->method('save');

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);
    $userRepo->expects(self::once())->method('save')->with($user);

    /** @var SessionRepositoryPort&MockObject $sessions */
    $sessions = $this->createMock(SessionRepositoryPort::class);
    $sessions->expects(self::once())->method('revokeAllForUser')->with(self::USER_ID);

    /** @var TokenRevocationPort&MockObject $tokens */
    $tokens = $this->createMock(TokenRevocationPort::class);
    $tokens->expects(self::once())->method('revokeAllUserTokens')->with(self::USER_ID);

    $sent = [];
    /** @var NotificationPort&MockObject $notifications */
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())->method('send')
      ->willReturnCallback(function (SendNotificationRequest $request) use (&$sent): SentNotification {
        $sent[] = $request;

        return $this->sentNotification($request);
      });

    $handler = $this->makeHandler(
      requests: $requests,
      userRepo: $userRepo,
      sessions: $sessions,
      tokens: $tokens,
      notifications: $notifications,
      now: $now,
    );

    $result = $handler(new ConfirmEmailChangeCommand(token: self::RAW_TOKEN));

    self::assertTrue($result->success);
    // The user now signs in with the new address, already verified.
    self::assertSame(self::NEW_EMAIL, $user->email()->value);
    self::assertTrue($user->isEmailVerified());
    self::assertTrue($request->isConfirmed());

    // The notice goes to the OLD address.
    self::assertCount(1, $sent);
    self::assertSame('jdoe@example.com', $sent[0]->recipientEmail);

    // The confirmed event carries both addresses.
    self::assertCount(1, $this->dispatchedEvents);
    $event = $this->dispatchedEvents[0];
    self::assertInstanceOf(UserEmailChangeConfirmedEvent::class, $event);
    self::assertSame('jdoe@example.com', $event->currentEmail);
    self::assertSame(self::NEW_EMAIL, $event->newEmail);
  }

  #[Test]
  public function testUniqueConstraintViolationOnUserSaveIsNeutral409AndDoesNotBurnToken(): void
  {
    // TOCTOU on the availability pre-check: the address is registered by
    // someone else between `existsByEmail` and the flush. The unique
    // constraint fires on the user save — the handler must answer the
    // same neutral refusal as the pre-check and must NOT consume the
    // request (no confirm, no revocation, no event).
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $user = $this->makeActiveUser();

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);
    $requests->expects(self::never())->method('confirmIfPending');
    $requests->expects(self::never())->method('save');

    $driverException = new class ('SQLSTATE[23505]: duplicate key value violates unique constraint "uniq_users_email"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);
    $userRepo->expects(self::once())->method('save')
      ->willThrowException(new UniqueConstraintViolationException($driverException, null));

    /** @var SessionRepositoryPort&MockObject $sessions */
    $sessions = $this->createMock(SessionRepositoryPort::class);
    $sessions->expects(self::never())->method('revokeAllForUser');

    /** @var TokenRevocationPort&MockObject $tokens */
    $tokens = $this->createMock(TokenRevocationPort::class);
    $tokens->expects(self::never())->method('revokeAllUserTokens');

    $result = ($this->makeHandler(
      requests: $requests,
      userRepo: $userRepo,
      sessions: $sessions,
      tokens: $tokens,
      now: $now,
    ))(new ConfirmEmailChangeCommand(token: self::RAW_TOKEN));

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE, $result->errorCode);
    self::assertSame('This email address cannot be used.', $result->message);
    // The token stays usable: the request was never confirmed.
    self::assertFalse($request->isConfirmed());
    self::assertCount(0, $this->dispatchedEvents);
  }

  #[Test]
  public function testNonConstraintSaveFailureIsRethrownWithoutBurningToken(): void
  {
    // Any other persistence failure must propagate (500), not be
    // silently mapped to the neutral 409 — and still not burn the token.
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $user = $this->makeActiveUser();

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);
    $requests->expects(self::never())->method('confirmIfPending');

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);
    $userRepo->method('save')->willThrowException(new RuntimeException('connection lost'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('connection lost');

    ($this->makeHandler(requests: $requests, userRepo: $userRepo, now: $now))(
      new ConfirmEmailChangeCommand(token: self::RAW_TOKEN)
    );
  }

  #[Test]
  public function testConcurrentConfirmLoserGetsNeutralRefusalAndRevokesNothing(): void
  {
    // Two concurrent confirmations of the same token: the conditional
    // update consumed zero rows for this caller, so it must answer the
    // same neutral refusal as an unknown token and side-effect nothing.
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $user = $this->makeActiveUser();

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);
    $requests->expects(self::once())->method('confirmIfPending')
      ->with(self::REQUEST_ID, $now)
      ->willReturn(false);

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);

    /** @var SessionRepositoryPort&MockObject $sessions */
    $sessions = $this->createMock(SessionRepositoryPort::class);
    $sessions->expects(self::never())->method('revokeAllForUser');

    /** @var TokenRevocationPort&MockObject $tokens */
    $tokens = $this->createMock(TokenRevocationPort::class);
    $tokens->expects(self::never())->method('revokeAllUserTokens');

    /** @var NotificationPort&MockObject $notifications */
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $result = ($this->makeHandler(
      requests: $requests,
      userRepo: $userRepo,
      sessions: $sessions,
      tokens: $tokens,
      notifications: $notifications,
      now: $now,
    ))(new ConfirmEmailChangeCommand(token: self::RAW_TOKEN));

    self::assertFalse($result->success);
    self::assertSame(ConfirmEmailChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertSame('Invalid or expired email change token.', $result->message);
    self::assertCount(0, $this->dispatchedEvents);
  }

  #[Test]
  public function testRevocationFailureIsBestEffortLoggedAndKeepsSuccess(): void
  {
    // Documented decision: the change is durable before revocation runs,
    // so a revocation backend failure keeps the 200 — surfaced through
    // an explicit warning — and does not stop the second revocation call.
    $now = new DateTimeImmutable('2026-08-28 10:30:00');
    $request = $this->makeRequest($now->modify('-10 minutes'));
    $user = $this->makeActiveUser();

    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByTokenHash')->willReturn($request);
    $requests->method('confirmIfPending')->willReturn(true);

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->method('existsByEmail')->willReturn(false);

    /** @var SessionRepositoryPort&MockObject $sessions */
    $sessions = $this->createMock(SessionRepositoryPort::class);
    $sessions->expects(self::once())->method('revokeAllForUser')
      ->willThrowException(new RuntimeException('session backend down'));

    /** @var TokenRevocationPort&MockObject $tokens */
    $tokens = $this->createMock(TokenRevocationPort::class);
    $tokens->expects(self::once())->method('revokeAllUserTokens')
      ->willThrowException(new RuntimeException('oauth backend down'));

    $warnings = [];
    $logger = $this->createStub(LoggerPort::class);
    $logger->method('warning')->willReturnCallback(
      /** @param array<string, mixed> $context */
      static function (string $message, array $context = []) use (&$warnings): void {
        $warnings[] = $message;
      },
    );

    $result = ($this->makeHandler(
      requests: $requests,
      userRepo: $userRepo,
      sessions: $sessions,
      tokens: $tokens,
      now: $now,
      logger: $logger,
    ))(new ConfirmEmailChangeCommand(token: self::RAW_TOKEN));

    self::assertTrue($result->success);
    self::assertSame(self::NEW_EMAIL, $user->email()->value);
    // Both failures were surfaced, each with its own warning.
    self::assertContains('Email change confirmed but session revocation failed — sessions remain valid until expiry.', $warnings);
    self::assertContains('Email change confirmed but OAuth token revocation failed — tokens remain valid until expiry.', $warnings);
    // The confirmed event still announces the durable change.
    self::assertCount(1, $this->dispatchedEvents);
  }
  // #endregion

  // #region Helpers
  private function makeRequest(DateTimeImmutable $requestedAt): EmailChangeRequest
  {
    return EmailChangeRequest::request(
      id: self::REQUEST_ID,
      userId: new UserId(self::USER_ID),
      currentEmail: new Email('jdoe@example.com'),
      newEmail: new Email(self::NEW_EMAIL),
      tokenHash: new EmailChangeTokenHasher()->hash(self::RAW_TOKEN),
      requestedAt: $requestedAt,
    );
  }

  private function makeHandler(
    EmailChangeRequestRepositoryPort $requests,
    ?UserRepositoryPort $userRepo = null,
    ?SessionRepositoryPort $sessions = null,
    ?TokenRevocationPort $tokens = null,
    ?NotificationPort $notifications = null,
    ?DateTimeImmutable $now = null,
    ?LoggerPort $logger = null,
  ): ConfirmEmailChangeHandler {
    $this->dispatchedEvents = [];

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now ?? new DateTimeImmutable());

    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatchAll')->willReturnCallback(
      /** @param array<int, object> $events */
      function (array $events): void {
        foreach ($events as $event) {
          /** @var object $event */
          $this->dispatchedEvents[] = $event;
        }
      },
    );

    if (null === $notifications) {
      $notifications = $this->createStub(NotificationPort::class);
      $notifications->method('send')->willReturnCallback(
        fn (SendNotificationRequest $request): SentNotification => $this->sentNotification($request),
      );
    }

    return new ConfirmEmailChangeHandler(
      emailChangeRequests: $requests,
      userRepository: $userRepo ?? $this->createStub(UserRepositoryPort::class),
      tokenHasher: new EmailChangeTokenHasher(),
      sessionRepository: $sessions ?? $this->createStub(SessionRepositoryPort::class),
      tokenRevocation: $tokens ?? $this->createStub(TokenRevocationPort::class),
      notifier: new EmailChangeNotifier(
        notificationPort: $notifications,
        frontendUrl: 'https://app.fireguard.test',
        translator: EmailTranslatorTestFactory::create(),
      ),
      clock: $clock,
      eventDispatcher: $dispatcher,
      eventIdProvider: new TestEventIdProvider(),
      logger: $logger ?? $this->createStub(LoggerPort::class),
    );
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
      password: HashedPassword::fromPlain('CurrentP@ssw0rd!'),
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
