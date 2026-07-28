<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Catalog;

use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationSystemRoleCatalog::class)]
final class OrganizationSystemRoleCatalogTest extends TestCase
{
  #[Test]
  public function testPermissionsForAdminIsTheWildcard(): void
  {
    self::assertSame(['organization.*'], OrganizationSystemRoleCatalog::permissionsFor(
      OrganizationSystemRoleCatalog::ADMIN,
    ));
  }

  #[Test]
  public function testPermissionsForAnUnknownRoleIsEmpty(): void
  {
    self::assertSame([], OrganizationSystemRoleCatalog::permissionsFor('inspector'));
  }

  #[Test]
  public function testMergePermissionsLeavesACustomRoleUntouched(): void
  {
    self::assertSame(['organization.read'], OrganizationSystemRoleCatalog::mergePermissions(
      roleName: OrganizationSystemRoleCatalog::MEMBER,
      permissions: ['organization.read'],
      isSystem: false,
    ));
  }

  #[Test]
  public function testMergePermissionsLeavesAnUncatalogedSystemRoleUntouched(): void
  {
    self::assertSame(['organization.read'], OrganizationSystemRoleCatalog::mergePermissions(
      roleName: 'inspector',
      permissions: ['organization.read'],
      isSystem: true,
    ));
  }

  #[Test]
  public function testPermissionsForMemberIncludesDashboardReadPermissions(): void
  {
    self::assertSame([
      'organization.read',
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
      'organization.interventions.read',
      'organization.maintenance.read',
      'organization.teams.read',
      'organization.messaging.read',
      'organization.compliance.read',
      'organization.approvals.read',
      'organization.approvals.request',
      'organization.events.read',
    ], OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER));
  }

  #[Test]
  public function testMergePermissionsKeepsCanonicalSystemPermissions(): void
  {
    self::assertSame([
      'organization.read',
      'organization.dashboard.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
      'organization.interventions.read',
      'organization.maintenance.read',
      'organization.teams.read',
      'organization.messaging.read',
      'organization.compliance.read',
      'organization.approvals.read',
      'organization.approvals.request',
      'organization.events.read',
    ], OrganizationSystemRoleCatalog::mergePermissions(
      roleName: OrganizationSystemRoleCatalog::MEMBER,
      permissions: [
        'organization.read',
        'organization.dashboard.read',
        'organization.members.read',
        'organization.roles.read',
        'organization.facilities.read',
      ],
      isSystem: true,
    ));
  }
}
