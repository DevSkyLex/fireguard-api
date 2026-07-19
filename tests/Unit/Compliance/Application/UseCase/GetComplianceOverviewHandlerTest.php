<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase;

use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewHandler, GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(GetComplianceOverviewHandler::class)]
final class GetComplianceOverviewHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeAssertsCompliancePermissionsBeforeAggregating(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORG_ID, OrganizationPermissionCatalog::complianceReadDependencies());

    $handler = new GetComplianceOverviewHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetComplianceOverviewQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetComplianceOverviewResult::class, $result);
    self::assertSame(ComplianceStatus::NOT_APPLICABLE, $result->organizationStatus);
    self::assertSame([], $result->facilities);
  }

  #[Test]
  public function testInvokeThrowsComplianceAccessDeniedExceptionWhenPermissionMissing(): void
  {
    $facilityDirectory = $this->createMock(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->expects(self::never())->method('listFacilities');

    $aggregator = new ComplianceRegisterAggregator(
      $facilityDirectory,
      $this->createStub(MaintenanceComplianceStatisticsPort::class),
      $this->createStub(InspectionComplianceStatisticsPort::class),
      $this->createStub(EquipmentComplianceStatisticsPort::class),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.compliance.read'),
    );

    $handler = new GetComplianceOverviewHandler($authorization, $aggregator);

    $this->expectException(ComplianceAccessDeniedException::class);

    $handler->__invoke(new GetComplianceOverviewQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeGradesFacilitiesAndComputesOrganizationRollup(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::FACILITY_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      self::FACILITY_ID => ['up_to_date' => 1, 'due_soon' => 0, 'overdue' => 2, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([
      self::FACILITY_ID => '2026-01-01T00:00:00+00:00',
    ]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([
      self::FACILITY_ID => ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 1],
    ]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::FACILITY_ID => ['total' => 3, 'active' => 3],
    ]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new GetComplianceOverviewHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetComplianceOverviewQuery(self::ORG_ID, self::USER_ID));

    self::assertSame(ComplianceStatus::NON_COMPLIANT, $result->organizationStatus);
    self::assertCount(1, $result->facilities);
    self::assertSame(ComplianceStatus::NON_COMPLIANT, $result->facilities[0]->status);
    self::assertSame(2, $result->totals['overdueEquipmentCount']);
    self::assertSame(1, $result->totals['openCriticalNonConformityCount']);
    self::assertSame(3, $result->totals['totalEquipmentCount']);
    // trackedEquipmentCount = up_to_date(1) + due_soon(0) + overdue(2) = 3; rate = 1/3*100 = 33.3
    self::assertSame(3, $result->totals['trackedEquipmentCount']);
    self::assertSame(33.3, $result->totals['complianceRate']);
  }

  #[Test]
  public function testInvokeComputesOrganizationRollupRateFromSummedTotalsNotAverageOfFacilityRates(): void
  {
    $facilityB = '550e8400-e29b-41d4-a716-446655440003';

    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::FACILITY_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ['id' => $facilityB, 'name' => 'Site B', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      // Site A: 9 tracked, all up to date (rate 100%).
      self::FACILITY_ID => ['up_to_date' => 9, 'due_soon' => 0, 'overdue' => 0, 'unscheduled' => 0],
      // Site B: 1 tracked, none up to date (rate 0%).
      $facilityB => ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 1, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::FACILITY_ID => ['total' => 9, 'active' => 9],
      $facilityB => ['total' => 1, 'active' => 1],
    ]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $handler = new GetComplianceOverviewHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetComplianceOverviewQuery(self::ORG_ID, self::USER_ID));

    // Naive average of per-facility rates would be (100 + 0) / 2 = 50.0.
    // The org-wide rate is derived from summed totals instead: 9 up-to-date
    // out of 10 tracked = 90.0, correctly weighting the larger facility.
    self::assertSame(10, $result->totals['trackedEquipmentCount']);
    self::assertSame(90.0, $result->totals['complianceRate']);
  }

  #[Test]
  public function testInvokeReturnsNullOrganizationRateWhenNoEquipmentIsTrackedAnywhere(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::FACILITY_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      self::FACILITY_ID => ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 0, 'unscheduled' => 4],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::FACILITY_ID => ['total' => 4, 'active' => 4],
    ]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $handler = new GetComplianceOverviewHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetComplianceOverviewQuery(self::ORG_ID, self::USER_ID));

    self::assertSame(0, $result->totals['trackedEquipmentCount']);
    self::assertNull($result->totals['complianceRate']);
  }
}
