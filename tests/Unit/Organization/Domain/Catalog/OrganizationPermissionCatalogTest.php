<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Catalog;

use InvalidArgumentException;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test OrganizationPermissionCatalog.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationPermissionCatalog::class)]
final class OrganizationPermissionCatalogTest extends TestCase
{
  #[Test]
  public function testDashboardReadDependenciesIncludeDashboardPermission(): void
  {
    self::assertContains('organization.dashboard.read', OrganizationPermissionCatalog::dashboardReadDependencies());
    self::assertContains('organization.members.read', OrganizationPermissionCatalog::dashboardReadDependencies());
  }

  #[Test]
  public function testDashboardTrendReadDependenciesForInspectionMetric(): void
  {
    self::assertSame(
      ['organization.inspection.read'],
      OrganizationPermissionCatalog::dashboardTrendReadDependencies('inspections_performed'),
    );
  }

  #[Test]
  public function testDashboardTrendReadDependenciesForEquipmentMetric(): void
  {
    self::assertSame(
      ['organization.equipment.read'],
      OrganizationPermissionCatalog::dashboardTrendReadDependencies('equipment_created'),
    );
  }

  #[Test]
  public function testDashboardTrendReadDependenciesRejectsUnknownMetric(): void
  {
    $this->expectException(InvalidArgumentException::class);

    OrganizationPermissionCatalog::dashboardTrendReadDependencies('unknown_metric');
  }

  #[Test]
  public function testComplianceReadDependenciesIncludeCompliancePermission(): void
  {
    self::assertContains('organization.compliance.read', OrganizationPermissionCatalog::complianceReadDependencies());
  }

  #[Test]
  public function testDescriptionForReturnsEmptyForUnknownPermission(): void
  {
    self::assertNotSame('', OrganizationPermissionCatalog::descriptionFor('organization.read'));
    self::assertSame('', OrganizationPermissionCatalog::descriptionFor('unknown.permission'));
  }

  #[Test]
  public function testDefinitionsIncludeWildcard(): void
  {
    $names = array_column(OrganizationPermissionCatalog::definitions(), 'name');

    self::assertContains('organization.*', $names);
  }
}
