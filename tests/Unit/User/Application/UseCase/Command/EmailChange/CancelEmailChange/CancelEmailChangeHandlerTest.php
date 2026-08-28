<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\EmailChange\CancelEmailChange;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\EmailChangeRequestRepositoryPort;
use User\Application\Service\EmailChangeTokenHasher;
use User\Application\UseCase\Command\EmailChange\CancelEmailChange\{
  CancelEmailChangeCommand,
  CancelEmailChangeHandler
};
use User\Domain\Event\UserEmailChangeCancelledEvent;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\ValueObject\UserId;

#[CoversClass(CancelEmailChangeHandler::class)]
final class CancelEmailChangeHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  /**
   * Collected domain events.
   *
   * @var list<object>
   */
  private array $dispatchedEvents = [];

  // #region Tests
  #[Test]
  public function testCancelsPendingRequestAndDispatchesEvent(): void
  {
    $now = new DateTimeImmutable('2026-08-28 10:00:00');
    $pending = EmailChangeRequest::request(
      id: '00000000-0000-4000-a000-0000000000aa',
      userId: new UserId(self::USER_ID),
      currentEmail: new Email('jdoe@example.com'),
      newEmail: new Email('new-address@example.com'),
      tokenHash: new EmailChangeTokenHasher()->hash('raw-token'),
      requestedAt: $now->modify('-5 minutes'),
    );

    /** @var EmailChangeRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByUserId')->willReturn($pending);
    $requests->expects(self::once())->method('removePendingForUser')->willReturn(1);

    $result = ($this->makeHandler($requests, $now))(
      new CancelEmailChangeCommand(userId: self::USER_ID)
    );

    self::assertTrue($result->cancelled);
    self::assertCount(1, $this->dispatchedEvents);
    $event = $this->dispatchedEvents[0];
    self::assertInstanceOf(UserEmailChangeCancelledEvent::class, $event);
    self::assertSame(self::USER_ID, $event->userId);
    self::assertSame('jdoe@example.com', $event->currentEmail);
    self::assertSame('new-address@example.com', $event->newEmail);
  }

  #[Test]
  public function testIsIdempotentWhenNothingIsPending(): void
  {
    $requests = $this->createStub(EmailChangeRequestRepositoryPort::class);
    $requests->method('findActiveByUserId')->willReturn(null);
    $requests->method('removePendingForUser')->willReturn(0);

    $result = ($this->makeHandler($requests, new DateTimeImmutable()))(
      new CancelEmailChangeCommand(userId: self::USER_ID)
    );

    // Success without an event: the second cancel must not re-announce.
    self::assertFalse($result->cancelled);
    self::assertCount(0, $this->dispatchedEvents);
  }
  // #endregion

  // #region Helpers
  private function makeHandler(
    EmailChangeRequestRepositoryPort $requests,
    DateTimeImmutable $now,
  ): CancelEmailChangeHandler {
    $this->dispatchedEvents = [];

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatch')->willReturnCallback(function (object $event): void {
      $this->dispatchedEvents[] = $event;
    });

    return new CancelEmailChangeHandler(
      emailChangeRequests: $requests,
      clock: $clock,
      eventDispatcher: $dispatcher,
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
