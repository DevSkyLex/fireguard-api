<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\Contract\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarFeedItem.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarFeedItem::class)]
final class CalendarFeedItemTest extends TestCase
{
  #[Test]
  public function itExposesAllProperties(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');
    $endsAt = new DateTimeImmutable('2026-08-01T11:00:00+02:00');

    $item = new CalendarFeedItem(
      sourceKey: 'calendar_event',
      id: 'item-1',
      title: 'Fire drill',
      description: 'Quarterly exercise',
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: false,
      facilityId: 'facility-1',
      status: 'draft',
      targetType: 'calendar_event',
      targetId: 'target-1',
    );

    self::assertSame('calendar_event', $item->sourceKey);
    self::assertSame('item-1', $item->id);
    self::assertSame('Fire drill', $item->title);
    self::assertSame('Quarterly exercise', $item->description);
    self::assertSame($startsAt, $item->startsAt);
    self::assertSame($endsAt, $item->endsAt);
    self::assertFalse($item->allDay);
    self::assertSame('facility-1', $item->facilityId);
    self::assertSame('draft', $item->status);
    self::assertSame('calendar_event', $item->targetType);
    self::assertSame('target-1', $item->targetId);
  }

  #[Test]
  public function itAllowsNullOptionalFields(): void
  {
    $item = new CalendarFeedItem(
      sourceKey: 'inspection',
      id: 'item-2',
      title: 'Inspection',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-02T09:00:00+02:00'),
      endsAt: null,
      allDay: true,
      facilityId: null,
      status: null,
      targetType: 'inspection',
      targetId: 'target-2',
    );

    self::assertNull($item->description);
    self::assertNull($item->endsAt);
    self::assertNull($item->facilityId);
    self::assertNull($item->status);
    self::assertTrue($item->allDay);
  }
}
