<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use User\Domain\Event\UserEmailVerifiedEvent;

/**
 * Test UserEmailVerifiedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserEmailVerifiedEvent::class)]
final class UserEmailVerifiedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $event = new UserEmailVerifiedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000002'),
      userId: 'user-2',
      email: 'user@example.com',
      occurredAt: new DateTimeImmutable('2024-01-02T00:00:00+00:00'),
    );

    self::assertSame('user-2', $event->aggregateId());
    self::assertSame('user', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('user@example.com', $payload['email']);
  }

  #[Test]
  public function testItExposesItsLedgerIdentityAndOccurrenceTimestamp(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000003');
    $occurredAt = new DateTimeImmutable('2024-01-03T10:15:00+00:00');

    $event = new UserEmailVerifiedEvent(
      eventId: $eventId,
      userId: 'user-3',
      email: 'verified@example.com',
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
  }
  // #endregion
}
