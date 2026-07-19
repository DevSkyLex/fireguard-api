<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use User\Domain\Event\UserDeactivatedEvent;

/**
 * Test UserDeactivatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserDeactivatedEvent::class)]
final class UserDeactivatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000003');
    $occurredAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $event = new UserDeactivatedEvent(
      eventId: $eventId,
      userId: 'user-3',
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('user-3', $event->aggregateId());
    self::assertSame('user', $event->aggregateType());
    self::assertSame(['user_id' => 'user-3'], $event->payload());
  }
  // #endregion
}
