<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Service\AuthorizationCacheInvalidator;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName, SubjectType};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleAssignmentRecord, RoleRecord};
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleRepository;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;
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
  public function testFindAllFiltersBySystemFlag(): void
  {
    $record = $this->createRoleRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findBy')
      ->with(['isSystem' => true])
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

    $roles = $repository->findAll(isSystem: true);

    self::assertCount(1, $roles);
    self::assertTrue($roles[0]->isSystem());
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

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->willReturn(null);

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    self::assertNull($repository->findById(new RoleId('223e4567-e89b-12d3-a456-426614174000')));
  }

  #[Test]
  public function testFindByNameReturnsRole(): void
  {
    $record = $this->createRoleRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'admin'])
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(RoleRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    $role = $repository->findByName(new RoleName('admin'));

    self::assertInstanceOf(Role::class, $role);
    self::assertSame('admin', $role->name()->value);
  }

  #[Test]
  public function testFindByNameReturnsNullWhenMissing(): void
  {
    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(RoleRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );

    self::assertNull($repository->findByName(new RoleName('ghost')));
  }

  #[Test]
  public function testDeleteInvalidatesTheCacheOfEveryAssignedUser(): void
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

    $assignment = new RoleAssignmentRecord();
    $assignment->id = '423e4567-e89b-12d3-a456-426614174000';
    $assignment->roleId = $record->id;
    $assignment->subjectType = SubjectType::USER->value;
    $assignment->subjectId = 'user-1';

    $assignmentRepository = $this->createMock(EntityRepository::class);
    $assignmentRepository->expects(self::once())
      ->method('findBy')
      ->with(['roleId' => $record->id, 'subjectType' => SubjectType::USER->value])
      ->willReturn([$assignment]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(RoleAssignmentRecord::class)
      ->willReturn($assignmentRepository);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RoleRecord::class, $record->id)
      ->willReturn($record);
    $entityManager->expects(self::once())->method('remove')->with($record);
    $entityManager->expects(self::once())->method('flush');

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(2))->method('delete');

    $repository = new RoleRepository(
      entityManager: $entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
      cacheInvalidator: new AuthorizationCacheInvalidator($cache),
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
