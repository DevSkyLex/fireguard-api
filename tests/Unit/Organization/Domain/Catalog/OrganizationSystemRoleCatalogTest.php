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
  public function testPermissionsForMemberIncludesDashboardReadPermissions(): void
  {
    self::assertSame([
      'organization.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
    ], OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER));
  }

  #[Test]
  public function testMergePermissionsKeepsCanonicalSystemPermissions(): void
  {
    self::assertSame([
      'organization.read',
      'organization.members.read',
      'organization.roles.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
    ], OrganizationSystemRoleCatalog::mergePermissions(
      roleName: OrganizationSystemRoleCatalog::MEMBER,
      permissions: [
        'organization.read',
        'organization.members.read',
        'organization.roles.read',
        'organization.facilities.read',
      ],
      isSystem: true,
    ));
  }
}
