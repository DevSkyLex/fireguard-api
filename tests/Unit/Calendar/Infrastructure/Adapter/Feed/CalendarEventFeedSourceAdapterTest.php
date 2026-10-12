<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Infrastructure\Adapter\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Internal\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Model\Event\{CalendarEvent, CalendarEventContent, CalendarEventIdentity};
use Calendar\Domain\ValueObject\CalendarEventId;
use Calendar\Infrastructure\Adapter\Feed\CalendarEventFeedSourceAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class CalendarEventFeedSourceAdapterTest
 *
 * Pins the standalone-event projection and forwards the exact organization, range and query cap.
 *
 * @category Adapter Tests
 */
#[CoversClass(CalendarEventFeedSourceAdapter::class)]
final class CalendarEventFeedSourceAdapterTest extends TestCase
{
  #[Test]
  public function itPreservesEveryEventFieldAndTheBoundedOverlapRead(): void
  {
    $organizationId = 'organization-1';
    $id = '018f0b68-6758-7a12-8a1d-3f0d97f64a10';
    $from = new DateTimeImmutable('2026-08-01T00:00:00Z');
    $to = new DateTimeImmutable('2026-08-31T23:59:59Z');
    $startsAt = new DateTimeImmutable('2026-08-15T00:00:00Z');
    $endsAt = new DateTimeImmutable('2026-08-16T00:00:00Z');
    $event = CalendarEvent::create(
      new CalendarEventIdentity(CalendarEventId::fromString($id), $organizationId, 'member-1'),
      new CalendarEventContent('Safety briefing', 'Bring the checklist', $startsAt, $endsAt, true, 'facility-1'),
    );
    $events = $this->createMock(CalendarEventRepositoryPort::class);
    $events->expects(self::once())->method('listBetween')->with($organizationId, $from, $to, 501)->willReturn([$event]);
    $adapter = new CalendarEventFeedSourceAdapter($events);

    self::assertSame('calendar_event', $adapter->sourceKey());
    self::assertEquals([
      new CalendarFeedItem('calendar_event', $id, 'Safety briefing', 'Bring the checklist', $startsAt, $endsAt, true, 'facility-1', null, 'calendar_event', $id),
    ], $adapter->findBetween($organizationId, $from, $to, 501));
  }
}
