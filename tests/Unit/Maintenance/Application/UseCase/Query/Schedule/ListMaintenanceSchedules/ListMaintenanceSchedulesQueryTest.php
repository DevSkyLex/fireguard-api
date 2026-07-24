<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use DateTimeImmutable;
use Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\ListMaintenanceSchedulesQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\QueryMessage;

/**
 * Test ListMaintenanceSchedulesQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListMaintenanceSchedulesQuery::class)]
final class ListMaintenanceSchedulesQueryTest extends TestCase
{
  #[Test]
  public function testRoundTripsAllProperties(): void
  {
    $dueBefore = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $query = new ListMaintenanceSchedulesQuery(
      'user-1',
      'org-1',
      'facility-1',
      'fire_extinguisher',
      'overdue',
      $dueBefore,
      3,
      50,
    );

    self::assertInstanceOf(QueryMessage::class, $query);
    self::assertSame('user-1', $query->userId);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('facility-1', $query->facilityId);
    self::assertSame('fire_extinguisher', $query->equipmentType);
    self::assertSame('overdue', $query->dueStatus);
    self::assertSame($dueBefore, $query->dueBefore);
    self::assertSame(3, $query->page);
    self::assertSame(50, $query->itemsPerPage);
  }

  #[Test]
  public function testAppliesDefaultsForOptionalArguments(): void
  {
    $query = new ListMaintenanceSchedulesQuery('user-1', 'org-1');

    self::assertNull($query->facilityId);
    self::assertNull($query->equipmentType);
    self::assertNull($query->dueStatus);
    self::assertNull($query->dueBefore);
    self::assertSame(1, $query->page);
    self::assertSame(30, $query->itemsPerPage);
  }
}
