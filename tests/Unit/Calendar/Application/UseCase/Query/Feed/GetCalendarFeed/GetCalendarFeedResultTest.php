<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCalendarFeedResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarFeedResult::class)]
final class GetCalendarFeedResultTest extends TestCase
{
  #[Test]
  public function itExposesItemsAndRange(): void
  {
    $from = new DateTimeImmutable('2026-08-01T00:00:00+02:00');
    $to = new DateTimeImmutable('2026-08-31T23:59:59+02:00');
    $item = new CalendarFeedItem(
      sourceKey: 'calendar_event',
      id: 'item-1',
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-10T09:00:00+02:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
      status: null,
      targetType: 'calendar_event',
      targetId: 'target-1',
    );

    $result = new GetCalendarFeedResult(
      items: [$item],
      from: $from,
      to: $to,
    );

    self::assertCount(1, $result->items);
    self::assertSame($item, $result->items[0]);
    self::assertSame($from, $result->from);
    self::assertSame($to, $result->to);
  }

  #[Test]
  public function itAcceptsAnEmptyFeed(): void
  {
    $result = new GetCalendarFeedResult(
      items: [],
      from: new DateTimeImmutable('2026-08-01T00:00:00+02:00'),
      to: new DateTimeImmutable('2026-08-31T23:59:59+02:00'),
    );

    self::assertSame([], $result->items);
  }
}
