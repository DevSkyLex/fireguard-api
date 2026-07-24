<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Service;

use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ComplianceRegisterAggregatorTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceRegisterAggregator::class)]
final class ComplianceRegisterAggregatorTest extends TestCase
{
  private const string SITE_ID = 'site-1';

  private const string BUILDING_ID = 'building-1';

  #[Test]
  public function testBuildFacilityViewsAssemblesOneViewPerFacilityWithDriverCounts(): void
  {
    $aggregator = new ComplianceRegisterAggregator(
      $this->facilityDirectory([
        ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ]),
      $this->maintenanceStatistics(
        [self::SITE_ID => ['up_to_date' => 3, 'due_soon' => 1, 'overdue' => 0, 'unscheduled' => 2]],
        [self::SITE_ID => '2026-06-01T09:00:00+00:00'],
      ),
      $this->inspectionStatistics(
        [self::SITE_ID => ['low' => 1, 'medium' => 2, 'high' => 0, 'critical' => 0]],
      ),
      $this->equipmentStatistics(
        [self::SITE_ID => ['total' => 8, 'active' => 6]],
      ),
    );

    $views = $aggregator->buildFacilityViews('org-1');

    self::assertCount(1, $views);
    $view = $views[0];
    self::assertSame(self::SITE_ID, $view->facilityId);
    self::assertSame('Site A', $view->name);
    self::assertSame('site', $view->type);
    self::assertSame('Site A', $view->path);
    self::assertSame(8, $view->totalEquipmentCount);
    self::assertSame(6, $view->activeEquipmentCount);
    self::assertSame(3, $view->upToDateEquipmentCount);
    self::assertSame(1, $view->dueSoonEquipmentCount);
    self::assertSame(0, $view->overdueEquipmentCount);
    self::assertSame(2, $view->unscheduledEquipmentCount);
    self::assertSame(1, $view->openLowNonConformityCount);
    self::assertSame(2, $view->openMediumNonConformityCount);
    self::assertSame('2026-06-01T09:00:00+00:00', $view->lastInspectionAt);
    // due_soon>0, no hard breach => AT_RISK.
    self::assertSame(ComplianceStatus::AT_RISK, $view->status);
  }

  #[Test]
  public function testBuildFacilityViewsDefaultsMissingStatisticsToZero(): void
  {
    $aggregator = new ComplianceRegisterAggregator(
      $this->facilityDirectory([
        ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ]),
      $this->maintenanceStatistics([], []),
      $this->inspectionStatistics([]),
      $this->equipmentStatistics([]),
    );

    $views = $aggregator->buildFacilityViews('org-1');

    self::assertCount(1, $views);
    $view = $views[0];
    self::assertSame(0, $view->totalEquipmentCount);
    self::assertSame(0, $view->upToDateEquipmentCount);
    self::assertSame(0, $view->overdueEquipmentCount);
    self::assertNull($view->lastInspectionAt);
    // No tracked equipment and no high/critical NC => NOT_APPLICABLE.
    self::assertSame(ComplianceStatus::NOT_APPLICABLE, $view->status);
  }

  #[Test]
  public function testBuildFacilityViewsBuildsBreadcrumbPathFromParentChain(): void
  {
    $aggregator = new ComplianceRegisterAggregator(
      $this->facilityDirectory([
        ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
        ['id' => self::BUILDING_ID, 'name' => 'Building A', 'type' => 'building', 'status' => 'active', 'parentFacilityId' => self::SITE_ID],
      ]),
      $this->maintenanceStatistics([], []),
      $this->inspectionStatistics([]),
      $this->equipmentStatistics([]),
    );

    $views = $aggregator->buildFacilityViews('org-1');

    self::assertCount(2, $views);
    self::assertSame('Site A', $views[0]->path);
    self::assertSame('Site A / Building A', $views[1]->path);
  }

  #[Test]
  public function testBuildFacilityViewsAppendsUnassignedBucketWhenUnattributedDataExists(): void
  {
    $aggregator = new ComplianceRegisterAggregator(
      $this->facilityDirectory([
        ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ]),
      $this->maintenanceStatistics(
        [ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY => ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 1, 'unscheduled' => 0]],
        [],
      ),
      $this->inspectionStatistics([]),
      $this->equipmentStatistics([]),
    );

    $views = $aggregator->buildFacilityViews('org-1');

    self::assertCount(2, $views);
    $unassigned = $views[1];
    self::assertSame(ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY, $unassigned->facilityId);
    self::assertSame('Unassigned', $unassigned->name);
    self::assertSame('Unassigned', $unassigned->path);
    self::assertSame(ComplianceStatus::NON_COMPLIANT, $unassigned->status);
  }

  #[Test]
  public function testBuildFacilityViewsOmitsUnassignedBucketWhenNoUnattributedData(): void
  {
    $aggregator = new ComplianceRegisterAggregator(
      $this->facilityDirectory([
        ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ]),
      $this->maintenanceStatistics([], []),
      $this->inspectionStatistics([]),
      $this->equipmentStatistics([]),
    );

    $views = $aggregator->buildFacilityViews('org-1');

    self::assertCount(1, $views);
    self::assertSame(self::SITE_ID, $views[0]->facilityId);
  }

  /**
   * @param list<array{id: string, name: string, type: string, status: string, parentFacilityId: ?string}> $facilities
   */
  private function facilityDirectory(array $facilities): ComplianceFacilityDirectoryPort
  {
    $stub = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $stub->method('listFacilities')->willReturn($facilities);

    return $stub;
  }

  /**
   * @param array<string, array{up_to_date: int, due_soon: int, overdue: int, unscheduled: int}> $dueStatusCounts
   * @param array<string, string> $lastInspectionDates
   */
  private function maintenanceStatistics(array $dueStatusCounts, array $lastInspectionDates): MaintenanceComplianceStatisticsPort
  {
    $stub = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $stub->method('dueStatusCountsByFacility')->willReturn($dueStatusCounts);
    $stub->method('lastInspectionClosedAtByFacility')->willReturn($lastInspectionDates);

    return $stub;
  }

  /**
   * @param array<string, array{low: int, medium: int, high: int, critical: int}> $nonConformities
   */
  private function inspectionStatistics(array $nonConformities): InspectionComplianceStatisticsPort
  {
    $stub = $this->createStub(InspectionComplianceStatisticsPort::class);
    $stub->method('openNonConformitiesBySeverityByFacility')->willReturn($nonConformities);

    return $stub;
  }

  /**
   * @param array<string, array{total: int, active: int}> $inventory
   */
  private function equipmentStatistics(array $inventory): EquipmentComplianceStatisticsPort
  {
    $stub = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $stub->method('equipmentInventoryByFacility')->willReturn($inventory);

    return $stub;
  }
}
