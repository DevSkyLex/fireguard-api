<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Model\Event;

use Calendar\Domain\Exception\CalendarEventValidationException;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEvent::class)]
final class CalendarEventTest extends TestCase
{
  private const string EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itCreatesWithAllAccessors(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');
    $endsAt = new DateTimeImmutable('2026-08-01T11:00:00+02:00');

    $event = CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: 'Quarterly exercise',
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: false,
      facilityId: 'facility-1',
      createdByMemberId: 'member-1',
    );

    self::assertSame(self::EVENT_ID, (string) $event->id());
    self::assertSame(self::ORGANIZATION_ID, $event->organizationId());
    self::assertSame('Fire drill', $event->title());
    self::assertSame('Quarterly exercise', $event->description());
    self::assertSame($startsAt, $event->startsAt());
    self::assertSame($endsAt, $event->endsAt());
    self::assertFalse($event->allDay());
    self::assertSame('facility-1', $event->facilityId());
    self::assertSame('member-1', $event->createdByMemberId());
    self::assertEquals($event->createdAt(), $event->updatedAt());
  }

  #[Test]
  public function itAllowsNullEndsAtOnCreate(): void
  {
    $event = $this->event(endsAt: null);

    self::assertNull($event->endsAt());
    self::assertNull($event->description());
    self::assertNull($event->facilityId());
  }

  #[Test]
  public function itRejectsEndBeforeStartOnCreate(): void
  {
    $this->expectException(CalendarEventValidationException::class);

    CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T11:00:00+02:00'),
      endsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      allDay: false,
      facilityId: null,
      createdByMemberId: 'member-1',
    );
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $event = CalendarEvent::reconstitute(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: null,
      allDay: true,
      facilityId: null,
      createdByMemberId: 'member-1',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertSame($createdAt, $event->createdAt());
    self::assertSame($updatedAt, $event->updatedAt());
    self::assertTrue($event->allDay());
  }

  #[Test]
  public function itUpdatesMutableFields(): void
  {
    $event = $this->event();
    $newStart = new DateTimeImmutable('2026-09-01T08:00:00+02:00');
    $newEnd = new DateTimeImmutable('2026-09-01T10:00:00+02:00');

    $event->update(
      title: 'Updated drill',
      description: 'New description',
      startsAt: $newStart,
      endsAt: $newEnd,
      allDay: true,
      facilityId: 'facility-2',
    );

    self::assertSame('Updated drill', $event->title());
    self::assertSame('New description', $event->description());
    self::assertSame($newStart, $event->startsAt());
    self::assertSame($newEnd, $event->endsAt());
    self::assertTrue($event->allDay());
    self::assertSame('facility-2', $event->facilityId());
  }

  #[Test]
  public function itRejectsEndBeforeStartOnUpdate(): void
  {
    $event = $this->event();

    $this->expectException(CalendarEventValidationException::class);

    $event->update(
      title: 'Updated drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-09-01T10:00:00+02:00'),
      endsAt: new DateTimeImmutable('2026-09-01T08:00:00+02:00'),
      allDay: false,
      facilityId: null,
    );
  }

  private function event(?DateTimeImmutable $endsAt = new DateTimeImmutable('2026-08-01T11:00:00+02:00')): CalendarEvent
  {
    return CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: $endsAt,
      allDay: false,
      facilityId: null,
      createdByMemberId: 'member-1',
    );
  }
}
