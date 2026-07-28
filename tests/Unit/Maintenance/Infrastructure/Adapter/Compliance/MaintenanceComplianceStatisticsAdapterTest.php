<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Adapter\Compliance;

use Doctrine\DBAL\{Connection, Result};
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Adapter\Compliance\MaintenanceComplianceStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceComplianceStatisticsAdapterTest.
 *
 * The adapter reads `maintenance_schedules` through raw SQL, so nothing
 * converts the stored timestamps for it: an unparseable value must be
 * dropped rather than surface as a fatal in the compliance register, and a
 * facility with no rows for a given due status must still report a zero
 * rather than a missing key.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceComplianceStatisticsAdapter::class)]
final class MaintenanceComplianceStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testDueStatusCountsByFacilityZeroFillsEveryDueStatusAndIgnoresUnknownOnes(): void
  {
    $adapter = new MaintenanceComplianceStatisticsAdapter($this->entityManager([
      ['facility_key' => 'facility-1', 'due_status' => 'overdue', 'due_status_count' => '3'],
      ['facility_key' => 'facility-1', 'due_status' => 'due_soon', 'due_status_count' => 2],
      ['facility_key' => 'facility-1', 'due_status' => 'not_a_status', 'due_status_count' => 9],
      ['facility_key' => 'unassigned', 'due_status' => 'unscheduled', 'due_status_count' => 1],
    ]));

    $counts = $adapter->dueStatusCountsByFacility(self::ORG_ID);

    self::assertSame(
      ['up_to_date' => 0, 'due_soon' => 2, 'overdue' => 3, 'unscheduled' => 0],
      $counts['facility-1'],
    );
    self::assertSame(
      ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 0, 'unscheduled' => 1],
      $counts['unassigned'],
    );
  }

  #[Test]
  public function testDueStatusCountsByFacilityReturnsAnEmptyMapWhenThereAreNoSchedules(): void
  {
    $adapter = new MaintenanceComplianceStatisticsAdapter($this->entityManager([]));

    self::assertSame([], $adapter->dueStatusCountsByFacility(self::ORG_ID));
  }

  #[Test]
  public function testLastInspectionClosedAtByFacilityReinterpretsRawTimestampsAsUtc(): void
  {
    $adapter = new MaintenanceComplianceStatisticsAdapter($this->entityManager([
      ['facility_key' => 'facility-1', 'last_inspection_closed_at' => '2026-06-01 09:00:00'],
      ['facility_key' => 'facility-2', 'last_inspection_closed_at' => '2026-06-02 10:30:00.123456'],
    ]));

    self::assertSame(
      [
        'facility-1' => '2026-06-01T09:00:00+00:00',
        'facility-2' => '2026-06-02T10:30:00.123456+00:00',
      ],
      $adapter->lastInspectionClosedAtByFacility(self::ORG_ID),
    );
  }

  #[Test]
  public function testLastInspectionClosedAtByFacilityDropsAnUnparseableTimestamp(): void
  {
    $adapter = new MaintenanceComplianceStatisticsAdapter($this->entityManager([
      ['facility_key' => 'facility-1', 'last_inspection_closed_at' => 'not-a-timestamp'],
      ['facility_key' => 'facility-2', 'last_inspection_closed_at' => '2026-06-02 10:30:00'],
    ]));

    self::assertSame(
      ['facility-2' => '2026-06-02T10:30:00+00:00'],
      $adapter->lastInspectionClosedAtByFacility(self::ORG_ID),
    );
  }

  /**
   * @param list<array<string, mixed>> $rows
   */
  private function entityManager(array $rows): EntityManagerInterface
  {
    $result = $this->createStub(Result::class);
    $result->method('fetchAllAssociative')->willReturn($rows);

    $connection = $this->createStub(Connection::class);
    $connection->method('executeQuery')->willReturn($result);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);

    return $entityManager;
  }
}
