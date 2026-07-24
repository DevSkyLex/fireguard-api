<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions\{
  GetOrganizationPermissionResult,
  ListOrganizationPermissionsHandler,
  ListOrganizationPermissionsQuery,
  ListOrganizationPermissionsResult
};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Test ListOrganizationPermissionsHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListOrganizationPermissionsHandlerTest extends TestCase
{
  #[Test]
  public function itReturnsEveryPermissionFromTheCatalog(): void
  {
    $handler = new ListOrganizationPermissionsHandler();

    $result = $handler(new ListOrganizationPermissionsQuery('org-1'));

    self::assertInstanceOf(ListOrganizationPermissionsResult::class, $result);

    $definitions = OrganizationPermissionCatalog::definitions();
    self::assertCount(count($definitions), $result->permissions);

    foreach ($definitions as $index => $definition) {
      self::assertInstanceOf(GetOrganizationPermissionResult::class, $result->permissions[$index]);
      self::assertSame($definition['name'], $result->permissions[$index]->name);
      self::assertSame($definition['description'], $result->permissions[$index]->description);
    }
  }

  #[Test]
  public function itMapsAKnownPermissionWithItsDescription(): void
  {
    $handler = new ListOrganizationPermissionsHandler();

    $result = $handler(new ListOrganizationPermissionsQuery('org-1'));

    $byName = [];
    foreach ($result->permissions as $permission) {
      $byName[$permission->name] = $permission->description;
    }

    self::assertArrayHasKey('organization.read', $byName);
    self::assertSame('View organization details', $byName['organization.read']);
    self::assertArrayHasKey('organization.*', $byName);
  }

  #[Test]
  public function itIgnoresTheOrganizationIdWhenBuildingTheCatalog(): void
  {
    $handler = new ListOrganizationPermissionsHandler();

    $first = $handler(new ListOrganizationPermissionsQuery('org-1'));
    $second = $handler(new ListOrganizationPermissionsQuery('org-2'));

    self::assertEquals($first->permissions, $second->permissions);
  }
}
