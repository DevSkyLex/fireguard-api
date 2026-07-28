<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceHandler, GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test GetFacilityComplianceHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityComplianceHandler::class)]
final class GetFacilityComplianceHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string SITE_ID = 'site-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeAssertsCompliancePermissionsBeforeReading(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORG_ID, OrganizationPermissionCatalog::complianceReadDependencies());

    $handler = new GetFacilityComplianceHandler($authorization, $this->makeAggregator());

    $result = $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, self::SITE_ID, self::USER_ID));

    self::assertInstanceOf(GetFacilityComplianceResult::class, $result);
    self::assertSame(self::SITE_ID, $result->facility->facilityId);
  }

  #[Test]
  public function testInvokeReturnsTheRequestedFacilityView(): void
  {
    $handler = new GetFacilityComplianceHandler($this->createStub(OrganizationAuthorizationPort::class), $this->makeAggregator());

    $result = $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, self::SITE_ID, self::USER_ID));

    self::assertSame(self::SITE_ID, $result->facility->facilityId);
    self::assertSame('Site A', $result->facility->name);
    self::assertNotSame('', $result->generatedAt);
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenFacilityAbsentFromRegister(): void
  {
    $handler = new GetFacilityComplianceHandler($this->createStub(OrganizationAuthorizationPort::class), $this->makeAggregator());

    $this->expectException(ComplianceNotFoundException::class);

    $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, 'missing-facility', self::USER_ID));
  }

  #[Test]
  public function testInvokeTranslatesOrganizationAccessDeniedToComplianceAccessDenied(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.compliance.read'),
    );

    $handler = new GetFacilityComplianceHandler($authorization, $this->makeAggregator());

    $this->expectException(ComplianceAccessDeniedException::class);

    $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, self::SITE_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeReturnsCachedResultWithoutRebuilding(): void
  {
    $cachedFacility = $this->makeAggregator()->buildFacilityViews(self::ORG_ID)[0];
    $cachedResult = new GetFacilityComplianceResult('2020-01-01T00:00:00+00:00', $cachedFacility);

    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturn($cachedResult);

    // A facility directory that would fail the test if consulted.
    $facilityDirectory = $this->createMock(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->expects(self::never())->method('listFacilities');
    $aggregator = new ComplianceRegisterAggregator(
      $facilityDirectory,
      $this->createStub(MaintenanceComplianceStatisticsPort::class),
      $this->createStub(InspectionComplianceStatisticsPort::class),
      $this->createStub(EquipmentComplianceStatisticsPort::class),
    );

    $handler = new GetFacilityComplianceHandler($this->createStub(OrganizationAuthorizationPort::class), $aggregator, $cache);

    $result = $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, self::SITE_ID, self::USER_ID));

    self::assertSame($cachedResult, $result);
  }

  #[Test]
  public function testInvokeStillServesAFreshReadWhenTheCacheIsUnavailable(): void
  {
    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willThrowException(new RuntimeException('cache read failed'));
    $cache->method('set')->willThrowException(new RuntimeException('cache write failed'));

    $handler = new GetFacilityComplianceHandler(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->makeAggregator(),
      $cache,
    );

    $result = $handler->__invoke(new GetFacilityComplianceQuery(self::ORG_ID, self::SITE_ID, self::USER_ID));

    self::assertSame(self::SITE_ID, $result->facility->facilityId);
  }

  private function makeAggregator(): ComplianceRegisterAggregator
  {
    $facilityDirectory = $this->createStub(ComplianceFacilityDirectoryPort::class);
    $facilityDirectory->method('listFacilities')->willReturn([
      ['id' => self::SITE_ID, 'name' => 'Site A', 'type' => 'site', 'status' => 'active', 'parentFacilityId' => null],
    ]);

    $maintenanceStatistics = $this->createStub(MaintenanceComplianceStatisticsPort::class);
    $maintenanceStatistics->method('dueStatusCountsByFacility')->willReturn([
      self::SITE_ID => ['up_to_date' => 2, 'due_soon' => 0, 'overdue' => 0, 'unscheduled' => 0],
    ]);
    $maintenanceStatistics->method('lastInspectionClosedAtByFacility')->willReturn([]);

    $inspectionStatistics = $this->createStub(InspectionComplianceStatisticsPort::class);
    $inspectionStatistics->method('openNonConformitiesBySeverityByFacility')->willReturn([]);

    $equipmentStatistics = $this->createStub(EquipmentComplianceStatisticsPort::class);
    $equipmentStatistics->method('equipmentInventoryByFacility')->willReturn([
      self::SITE_ID => ['total' => 2, 'active' => 2],
    ]);

    return new ComplianceRegisterAggregator($facilityDirectory, $maintenanceStatistics, $inspectionStatistics, $equipmentStatistics);
  }
}
