<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextScope};
use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Infrastructure\Adapter\Assistant\ComplianceAssistantContextProviderAdapter;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_values;

/**
 * Test ComplianceAssistantContextProviderAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceAssistantContextProviderAdapter::class)]
final class ComplianceAssistantContextProviderAdapterTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64f01';

  private const string SITE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64f02';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testSupportsReturnsTrueOnlyWhenEveryComplianceReadDependencyIsGranted(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(OrganizationPermissionCatalog::complianceReadDependencies());

    $adapter = new ComplianceAssistantContextProviderAdapter($authorization, $this->makeAggregator([]));

    self::assertTrue($adapter->supports(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1')));
  }

  #[Test]
  public function testSupportsReturnsFalseWhenAnyComplianceReadDependencyIsMissing(): void
  {
    $granted = OrganizationPermissionCatalog::complianceReadDependencies();
    // Drop one required dependency.
    unset($granted[0]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(array_values($granted));

    $adapter = new ComplianceAssistantContextProviderAdapter($authorization, $this->makeAggregator([]));

    self::assertFalse($adapter->supports(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1')));
  }

  #[Test]
  public function testProvideReturnsAnEmptyFragmentWhenThereAreNoFacilities(): void
  {
    $adapter = new ComplianceAssistantContextProviderAdapter(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->makeAggregator([]),
    );

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertTrue($fragment->isEmpty());
  }

  #[Test]
  public function testProvideRendersTheOrganizationRollupAndCounts(): void
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      self::SITE_ID => ['up_to_date' => 2, 'due_soon' => 1, 'overdue' => 1, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([
      self::SITE_ID => ['low' => 1, 'medium' => 0, 'high' => 0, 'critical' => 1],
    ]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::SITE_ID => ['total' => 4, 'active' => 4],
    ]);

    $aggregator = new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
    $adapter = new ComplianceAssistantContextProviderAdapter($this->createStub(OrganizationAuthorizationPort::class), $aggregator);

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertFalse($fragment->isEmpty());
    self::assertSame('compliance.summary', $fragment->sourceKey);
    self::assertStringContainsString('non_compliant', $fragment->text);
    self::assertStringContainsString('1 critical', $fragment->text);
    self::assertStringContainsString('1 low', $fragment->text);
    self::assertStringContainsString('1 overdue', $fragment->text);
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
