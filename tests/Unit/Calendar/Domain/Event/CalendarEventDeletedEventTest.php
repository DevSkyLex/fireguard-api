<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Event;

use Calendar\Domain\Event\CalendarEventDeletedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarEventDeletedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventDeletedEvent::class)]
final class CalendarEventDeletedEventTest extends TestCase
{
  #[Test]
  public function itExposesPayload(): void
  {
    $event = new CalendarEventDeletedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
      actorUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('event-1', $event->eventId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }

  #[Test]
  public function itDefaultsActorToNull(): void
  {
    $event = new CalendarEventDeletedEvent(
      organizationId: 'org-1',
      eventId: 'event-1',
    );

    self::assertNull($event->actorUserId);
  }
}
