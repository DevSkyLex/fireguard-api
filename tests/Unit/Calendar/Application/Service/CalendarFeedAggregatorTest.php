<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\Service;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Feed\{InspectionCalendarFeedPort, InterventionCalendarFeedPort, MaintenanceCalendarFeedPort};
use Calendar\Application\Service\CalendarFeedAggregator;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

use function array_map;

/**
 * Test CalendarFeedAggregatorTest.
 *
 * Exercises the merge/sort and the resilience contract: one failing source
 * degrades to an empty contribution (logged), never taking the other
 * sources' contributions down with it.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarFeedAggregator::class)]
final class CalendarFeedAggregatorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string CALENDAR_EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a10';

  #[Test]
  public function itMergesAndSortsEveryContributingSourceByStartsAtAscending(): void
  {
    $events = $this->createStub(CalendarEventRepositoryPort::class);
    $events->method('listBetween')->willReturn([
      $this->calendarEvent(self::CALENDAR_EVENT_ID, '2026-08-15T10:00:00+00:00', 'Standalone'),
    ]);

    $inspections = $this->createStub(InspectionCalendarFeedPort::class);
    $inspections->method('findBetween')->willReturn([
      $this->feedItem('inspection', 'i-1', '2026-08-10T10:00:00+00:00'),
    ]);

    $interventions = $this->createStub(InterventionCalendarFeedPort::class);
    $interventions->method('findBetween')->willReturn([
      $this->feedItem('intervention', 'v-1', '2026-08-20T10:00:00+00:00'),
    ]);

    $maintenance = $this->createStub(MaintenanceCalendarFeedPort::class);
    $maintenance->method('findBetween')->willReturn([
      $this->feedItem('maintenance', 'm-1', '2026-08-05T10:00:00+00:00'),
    ]);

    $aggregator = new CalendarFeedAggregator(
      $events,
      $inspections,
      $interventions,
      $maintenance,
      $this->createStub(LoggerPort::class),
    );

    $items = $aggregator->aggregate(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
    );

    self::assertCount(4, $items);
    self::assertSame(
      ['m-1', 'i-1', self::CALENDAR_EVENT_ID, 'v-1'],
      array_map(static fn (CalendarFeedItem $item): string => $item->id, $items),
    );
  }

  #[Test]
  public function itDegradesAFailingSourceToAnEmptyContributionAndLogsIt(): void
  {
    $events = $this->createStub(CalendarEventRepositoryPort::class);
    $events->method('listBetween')->willReturn([]);

    $inspections = $this->createStub(InspectionCalendarFeedPort::class);
    $inspections->method('findBetween')->willThrowException(new RuntimeException('boom'));

    $interventions = $this->createStub(InterventionCalendarFeedPort::class);
    $interventions->method('findBetween')->willReturn([
      $this->feedItem('intervention', 'v-1', '2026-08-20T10:00:00+00:00'),
    ]);

    $maintenance = $this->createStub(MaintenanceCalendarFeedPort::class);
    $maintenance->method('findBetween')->willReturn([]);

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(self::stringContains('Calendar feed source failed'), self::callback(static function (array $context): bool {
        self::assertSame('inspection', $context['sourceKey']);

        return true;
      }));

    $aggregator = new CalendarFeedAggregator($events, $inspections, $interventions, $maintenance, $logger);

    $items = $aggregator->aggregate(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
    );

    self::assertCount(1, $items);
    self::assertSame('v-1', $items[0]->id);
  }

  #[Test]
  public function itBreaksTiesDeterministicallyBySourceKeyThenId(): void
  {
    $sameInstant = '2026-08-15T10:00:00+00:00';

    $events = $this->createStub(CalendarEventRepositoryPort::class);
    $events->method('listBetween')->willReturn([]);

    $inspections = $this->createStub(InspectionCalendarFeedPort::class);
    $inspections->method('findBetween')->willReturn([
      $this->feedItem('inspection', 'i-2', $sameInstant),
      $this->feedItem('inspection', 'i-1', $sameInstant),
    ]);

    $interventions = $this->createStub(InterventionCalendarFeedPort::class);
    $interventions->method('findBetween')->willReturn([
      $this->feedItem('intervention', 'v-1', $sameInstant),
    ]);

    $maintenance = $this->createStub(MaintenanceCalendarFeedPort::class);
    $maintenance->method('findBetween')->willReturn([]);

    $aggregator = new CalendarFeedAggregator(
      $events,
      $inspections,
      $interventions,
      $maintenance,
      $this->createStub(LoggerPort::class),
    );

    $items = $aggregator->aggregate(
      self::ORGANIZATION_ID,
      new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-08-31T23:59:59+00:00'),
    );

    // Same instant for all three: ordered by sourceKey ascending
    // ('inspection' < 'intervention'), then id ascending within 'inspection'.
    self::assertSame(['i-1', 'i-2', 'v-1'], array_map(static fn (CalendarFeedItem $item): string => $item->id, $items));
  }

  private function feedItem(string $sourceKey, string $id, string $startsAt): CalendarFeedItem
  {
    return new CalendarFeedItem(
      sourceKey: $sourceKey,
      id: $id,
      title: 'Title ' . $id,
      description: null,
      startsAt: new DateTimeImmutable($startsAt),
      endsAt: null,
      allDay: false,
      facilityId: null,
      status: null,
      targetType: $sourceKey,
      targetId: $id,
    );
  }

  private function calendarEvent(string $id, string $startsAt, string $title): CalendarEvent
  {
    return CalendarEvent::create(
      id: CalendarEventId::fromString($id),
      organizationId: self::ORGANIZATION_ID,
      title: $title,
      description: null,
      startsAt: new DateTimeImmutable($startsAt),
      endsAt: null,
      allDay: false,
      facilityId: null,
      createdByMemberId: '018f0b68-6758-7a12-8a1d-3f0d97f64a99',
    );
  }
}
