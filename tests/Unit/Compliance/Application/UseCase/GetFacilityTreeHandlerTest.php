<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase;

use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Application\UseCase\Query\GetFacilityTree\{GetFacilityTreeHandler, GetFacilityTreeQuery, GetFacilityTreeResult};
use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetFacilityTreeHandlerTest.
 *
 * Covers that the handler asserts the SAME permission set as the compliance
 * overview before touching any port, reuses
 * `ComplianceRegisterAggregator::buildFacilityViews()` (a single batched
 * fan-out, no per-node port calls), and nests the resulting flat views into
 * a tree.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityTreeHandler::class)]
final class GetFacilityTreeHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string BUILDING_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeAssertsCompliancePermissionsBeforeAggregating(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORG_ID, OrganizationPermissionCatalog::complianceReadDependencies());

    $handler = new GetFacilityTreeHandler($authorization, $this->makeAggregator([]));

    $result = $handler->__invoke(new GetFacilityTreeQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetFacilityTreeResult::class, $result);
    self::assertSame([], $result->tree);
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

    $handler = new GetFacilityTreeHandler($authorization, $aggregator);

    $this->expectException(ComplianceAccessDeniedException::class);

    $handler->__invoke(new GetFacilityTreeQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeNestsFacilitiesIntoATreeReusingTheComplianceVerdict(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
      ['id' => self::BUILDING_ID, 'name' => 'Building A', 'type' => 'building', 'status' => 'active', 'parentFacilityId' => self::SITE_ID],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      self::BUILDING_ID => ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 1, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::BUILDING_ID => ['total' => 4, 'active' => 4],
    ]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $handler = new GetFacilityTreeHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetFacilityTreeQuery(self::ORG_ID, self::USER_ID));

    self::assertCount(1, $result->tree);
    self::assertSame(self::SITE_ID, $result->tree[0]->id);
    self::assertCount(1, $result->tree[0]->children);

    $buildingNode = $result->tree[0]->children[0];
    self::assertSame(self::BUILDING_ID, $buildingNode->id);
    self::assertSame(4, $buildingNode->equipmentCount);
    self::assertSame(ComplianceStatus::NON_COMPLIANT, $buildingNode->status);
    self::assertSame([], $buildingNode->children);
  }

  #[Test]
  public function testInvokeExcludesUnassignedPseudoFacilityFromTheTree(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY => ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 1, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $handler = new GetFacilityTreeHandler($authorization, $aggregator);

    $result = $handler->__invoke(new GetFacilityTreeQuery(self::ORG_ID, self::USER_ID));

    self::assertCount(1, $result->tree);
    self::assertSame(self::SITE_ID, $result->tree[0]->id);
  }

  /**
   * @param list<array{id: string, name: string, type: string, status: string, parentFacilityId: ?string}> $facilities
   */
  private function makeAggregator(array $facilities): ComplianceRegisterAggregator
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn($facilities);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([]);

    return new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
  }
}
