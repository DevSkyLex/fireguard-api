<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleRepository;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TenantId;

/**
 * Test RoleRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: RoleRepository::class)]
final class RoleRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFindByIdReturnsRole(): void
  {
    $record = $this->createRoleRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RoleRecord::class, $record->id)
      ->willReturn($record);

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    $role = $repository->findById(new RoleId($record->id));

    self::assertInstanceOf(Role::class, $role);
    self::assertSame($record->name, $role->name()->value);
  }

  #[Test]
  public function testFindAllFiltersByTenant(): void
  {
    $record = $this->createRoleRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findBy')
      ->with(['tenantId' => '323e4567-e89b-12d3-a456-426614174000'])
      ->willReturn([$record]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(RoleRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    $roles = $repository->findAll(TenantId::fromString('323e4567-e89b-12d3-a456-426614174000'));

    self::assertCount(1, $roles);
    self::assertInstanceOf(Role::class, $roles[0]);
  }

  #[Test]
  public function testSavePersistsRoleAndPermissions(): void
  {
    $permission = new Permission(
      id: new PermissionId('123e4567-e89b-12d3-a456-426614174000'),
      name: new PermissionName('users.read'),
      description: 'Read users',
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
    );

    $role = Role::reconstitute(
      id: new RoleId('223e4567-e89b-12d3-a456-426614174000'),
      name: new RoleName('admin'),
      description: 'Admin role',
      isSystem: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
      updatedAt: null,
      permissions: [$permission],
    );

    $permissionRecord = new PermissionRecord();
    $permissionRecord->id = $permission->id()->value;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RoleRecord::class, $role->id()->value)
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(PermissionRecord::class, $permission->id()->value)
      ->willReturn($permissionRecord);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(RoleRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    $repository->save($role);
  }

  #[Test]
  public function testDeleteRemovesRole(): void
  {
    $record = $this->createRoleRecord();
    $role = Role::reconstitute(
      id: new RoleId($record->id),
      name: new RoleName($record->name),
      description: $record->description,
      isSystem: $record->isSystem,
      tenantId: null,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      permissions: [],
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RoleRecord::class, $record->id)
      ->willReturn($record);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with($record);
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    $repository->delete($role);
  }

  private function createRoleRecord(): RoleRecord
  {
    $permission = new PermissionRecord();
    $permission->id = '123e4567-e89b-12d3-a456-426614174000';
    $permission->name = 'users.read';
    $permission->description = 'Read users';
    $permission->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $record = new RoleRecord();
    $record->id = '223e4567-e89b-12d3-a456-426614174000';
    $record->name = 'admin';
    $record->description = 'Admin role';
    $record->isSystem = true;
    $record->tenantId = '323e4567-e89b-12d3-a456-426614174000';
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->updatedAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $record->permissions->add($permission);

    return $record;
  }
  // #endregion
}
