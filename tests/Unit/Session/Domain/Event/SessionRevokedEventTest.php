<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\Event\SessionRevokedEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test SessionRevokedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SessionRevokedEvent::class)]
final class SessionRevokedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000002');
    $occurredAt = new DateTimeImmutable('2024-01-02T00:00:00+00:00');
    $event = new SessionRevokedEvent(
      eventId: $eventId,
      sessionId: 'session-2',
      userId: 'user-2',
      reason: 'logout',
      occurredAt: $occurredAt,
    );

    self::assertSame('session-2', $event->aggregateId());
    self::assertSame('Session', $event->aggregateType());
    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('session-2', $event->sessionId());
    self::assertSame('user-2', $event->userId());
    self::assertSame('logout', $event->reason());

    $payload = $event->payload();
    self::assertSame('logout', $payload['reason']);
  }
  // #endregion
}
