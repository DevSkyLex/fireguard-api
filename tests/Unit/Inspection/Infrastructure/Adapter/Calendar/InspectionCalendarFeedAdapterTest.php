<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Calendar;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionEquipmentId, InspectionId, InspectionOrganizationId, InspectionResult, Inspector};
use Inspection\Infrastructure\Adapter\Calendar\InspectionCalendarFeedAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Sorting\Sorting;

/**
 * Test InspectionCalendarFeedAdapterTest.
 *
 * Mocks {@see InspectionRepositoryPort}, itself already covered by real DQL
 * tests elsewhere (`InspectionRepositoryTest` and siblings) — this adapter
 * writes no DQL of its own, so a mock-based unit test is sufficient here.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionCalendarFeedAdapter::class)]
final class InspectionCalendarFeedAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itMapsInspectionsIntoFeedItemsOrderedByPerformedAt(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a02'),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a03'),
      inspector: Inspector::forUser('user-1', 'Jane Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-08-10T09:00:00+00:00'),
      facilityId: null,
      notes: 'Routine check',
    );

    $from = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2026-08-31T23:59:59+00:00');

    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::equalTo(InspectionOrganizationId::fromString(self::ORGANIZATION_ID)),
        null,
        null,
        null,
        null,
        $from->format('c'),
        $to->format('c'),
        null,
        null,
        null,
        null,
        self::equalTo(new Sorting('performedAt')),
        500,
        0,
      )
      ->willReturn([$inspection]);

    $adapter = new InspectionCalendarFeedAdapter($repository);

    $items = $adapter->findBetween(self::ORGANIZATION_ID, $from, $to, 500);

    self::assertCount(1, $items);
    /** @var CalendarFeedItem $item */
    $item = $items[0];
    self::assertSame('inspection', $item->sourceKey);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a02', $item->id);
    self::assertSame('Inspection — Jane Doe', $item->title);
    self::assertEquals(new DateTimeImmutable('2026-08-10T09:00:00+00:00'), $item->startsAt);
    self::assertSame('draft', $item->status);
    self::assertSame('inspection', $item->targetType);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a02', $item->targetId);
  }
}
