<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Event;

use Calendar\Domain\Event\CalendarEventUpdatedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarEventUpdatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventUpdatedEvent::class)]
final class CalendarEventUpdatedEventTest extends TestCase
{
  #[Test]
  public function itExposesPayload(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T10:00:00+02:00');

    $event = new CalendarEventUpdatedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      title: 'Updated drill',
      startsAt: $startsAt,
      actorUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('event-1', $event->eventId);
    self::assertSame('Updated drill', $event->title);
    self::assertSame($startsAt, $event->startsAt);
    self::assertSame('user-1', $event->actorUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }

  #[Test]
  public function itDefaultsActorToNull(): void
  {
    $event = new CalendarEventUpdatedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      title: 'Updated drill',
      startsAt: new DateTimeImmutable('2026-08-01T10:00:00+02:00'),
    );

    self::assertNull($event->actorUserId);
  }
}
