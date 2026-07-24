<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Event\GetCalendarEvent;

use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\GetCalendarEventResult;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCalendarEventResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarEventResult::class)]
final class GetCalendarEventResultTest extends TestCase
{
  private const string EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itMapsFromDomain(): void
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

    $result = GetCalendarEventResult::fromDomain($event);

    self::assertSame(self::EVENT_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Fire drill', $result->title);
    self::assertSame('Quarterly exercise', $result->description);
    self::assertSame($startsAt, $result->startsAt);
    self::assertSame($endsAt, $result->endsAt);
    self::assertFalse($result->allDay);
    self::assertSame('facility-1', $result->facilityId);
    self::assertSame('member-1', $result->createdByMemberId);
    self::assertSame($event->createdAt(), $result->createdAt);
    self::assertSame($event->updatedAt(), $result->updatedAt);
  }
}
