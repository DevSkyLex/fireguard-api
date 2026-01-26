<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\Event\SessionCreatedEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test SessionCreatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SessionCreatedEvent::class)]
final class SessionCreatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000001');
    $occurredAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    $event = new SessionCreatedEvent(
      eventId: $eventId,
      sessionId: 'session-1',
      userId: 'user-1',
      ipAddress: '127.0.0.1',
      userAgent: 'Mozilla',
      occurredAt: $occurredAt,
    );

    self::assertSame('session-1', $event->aggregateId());
    self::assertSame('Session', $event->aggregateType());
    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('session-1', $event->sessionId());
    self::assertSame('user-1', $event->userId());
    self::assertSame('127.0.0.1', $event->ipAddress());
    self::assertSame('Mozilla', $event->userAgent());

    $payload = $event->payload();
    self::assertSame('user-1', $payload['user_id']);
  }
  // #endregion
}
