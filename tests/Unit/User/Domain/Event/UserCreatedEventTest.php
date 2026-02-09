<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use User\Domain\Event\UserCreatedEvent;

/**
 * Test UserCreatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserCreatedEvent::class)]
final class UserCreatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $event = new UserCreatedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000001'),
      userId: 'user-1',
      username: 'jdoe',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertSame('user-1', $event->aggregateId());
    self::assertSame('user', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('jdoe', $payload['username']);
    self::assertSame('jdoe@example.com', $payload['email']);
    self::assertSame('2024-01-01T00:00:00+00:00', $event->occurredAt()->format('c'));
  }
  // #endregion
}
