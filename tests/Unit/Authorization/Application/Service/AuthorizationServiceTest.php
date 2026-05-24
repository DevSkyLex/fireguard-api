<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\Service;

use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Application\Service\AuthorizationService;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName, SubjectType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

/**
 * Test AuthorizationServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthorizationService::class)]
final class AuthorizationServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testHasPermissionMatchesWildcard(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440000'),
      name: new PermissionName('users.*'),
      description: 'All user permissions',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440001'),
      name: new RoleName('admin'),
      description: 'Admin',
    );
    $role->addPermission($permission);

    /** @var RoleAssignmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findRolesForSubject')
      ->with(SubjectType::USER, 'user-123')
      ->willReturn([$role]);

    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturn(null);

    $service = new AuthorizationService($repository, $cache);

    self::assertTrue($service->hasPermission('user-123', 'users.create'));
  }

  #[Test]
  public function testHasPermissionReturnsFalseWhenMissing(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440010'),
      name: new PermissionName('users.read'),
      description: 'Read users',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440011'),
      name: new RoleName('viewer'),
      description: 'Viewer',
    );
    $role->addPermission($permission);

    /** @var RoleAssignmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findRolesForSubject')
      ->with(SubjectType::USER, 'user-456')
      ->willReturn([$role]);

    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturn(null);

    $service = new AuthorizationService($repository, $cache);

    self::assertFalse($service->hasPermission('user-456', 'users.create'));
  }

  #[Test]
  public function testRoleChecksAndNames(): void
  {
    $roleAdmin = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440100'),
      name: new RoleName('admin'),
      description: 'Admin',
    );
    $roleViewer = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440101'),
      name: new RoleName('viewer'),
      description: 'Viewer',
    );

    /** @var RoleAssignmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $repository->expects(self::exactly(5))
      ->method('findRolesForSubject')
      ->with(SubjectType::USER, 'user-789')
      ->willReturn([$roleAdmin, $roleViewer]);

    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturn(null);

    $service = new AuthorizationService($repository, $cache);

    self::assertTrue($service->hasRole('user-789', 'admin'));
    self::assertFalse($service->hasRole('user-789', 'missing'));
    self::assertTrue($service->hasAnyRole('user-789', ['missing', 'viewer']));
    self::assertTrue($service->hasAllRoles('user-789', ['admin', 'viewer']));

    $names = $service->getUserRoleNames('user-789');
    self::assertSame(['admin', 'viewer'], $names);
  }

  #[Test]
  public function testGetUserPermissionsDeduplicates(): void
  {
    $permissionId = new PermissionId('550e8400-e29b-41d4-a716-446655440200');
    $permissionName = new PermissionName('roles.assign');

    $permissionA = Permission::create(
      id: $permissionId,
      name: $permissionName,
      description: 'Assign roles',
    );
    $permissionB = Permission::create(
      id: $permissionId,
      name: $permissionName,
      description: 'Assign roles',
    );

    $roleOne = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440201'),
      name: new RoleName('role_a'),
      description: 'Role A',
    );
    $roleOne->addPermission($permissionA);

    $roleTwo = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440202'),
      name: new RoleName('role_b'),
      description: 'Role B',
    );
    $roleTwo->addPermission($permissionB);

    /** @var RoleAssignmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(RoleAssignmentRepositoryPort::class);
    $repository->expects(self::exactly(2))
      ->method('findRolesForSubject')
      ->with(SubjectType::USER, 'user-999')
      ->willReturn([$roleOne, $roleTwo]);

    $cache = $this->createStub(CachePort::class);
    $cache->method('get')->willReturn(null);

    $service = new AuthorizationService($repository, $cache);

    $permissions = $service->getUserPermissions('user-999');
    self::assertCount(1, $permissions);

    $names = $service->getUserPermissionNames('user-999');
    self::assertSame(['roles.assign'], $names);
  }
  // #endregion
}
