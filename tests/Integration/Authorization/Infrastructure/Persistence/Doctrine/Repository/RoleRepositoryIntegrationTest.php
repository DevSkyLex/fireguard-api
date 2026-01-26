<?php

declare(strict_types=1);

namespace Tests\Integration\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test RoleRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RoleRepository::class)]
final class RoleRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private RoleRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new RoleRepository(
      entityManager: $this->entityManager,
      mapper: new RoleMapper(new PermissionMapper()),
    );
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testFindByIdAndNameReturnNullWhenMissing(): void
  {
    $missingById = $this->repository->findById(new RoleId('223e4567-e89b-12d3-a456-426614174000'));
    $missingByName = $this->repository->findByName(new RoleName('missing_role'));

    self::assertNull($missingById);
    self::assertNull($missingByName);
  }

  #[Test]
  public function testSaveFindAllAndDeleteRole(): void
  {
    $permissionRecord = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174000',
      name: 'users.read',
    );
    $this->entityManager->flush();

    $permission = new Permission(
      id: new PermissionId($permissionRecord->id),
      name: new PermissionName($permissionRecord->name),
      description: $permissionRecord->description,
      createdAt: $permissionRecord->createdAt,
    );

    $roleId = new RoleId('223e4567-e89b-12d3-a456-426614174000');
    $role = Role::create(
      id: $roleId,
      name: new RoleName('admin_role'),
      description: 'Admin role',
      isSystem: false,
      tenantId: TenantId::fromString('423e4567-e89b-12d3-a456-426614174000'),
    );
    $role->addPermission($permission);

    $this->repository->save($role);

    $foundById = $this->repository->findById($roleId);
    $foundByName = $this->repository->findByName(new RoleName('admin_role'));

    self::assertNotNull($foundById);
    self::assertNotNull($foundByName);
    self::assertSame('admin_role', $foundById->name()->value);
    self::assertCount(1, $foundById->permissions());
    self::assertSame('users.read', $foundById->permissions()[0]->name()->value);

    self::assertCount(1, $this->repository->findAll());
    self::assertCount(
      1,
      $this->repository->findAll(TenantId::fromString('423e4567-e89b-12d3-a456-426614174000')),
    );
    self::assertCount(
      0,
      $this->repository->findAll(TenantId::fromString('523e4567-e89b-12d3-a456-426614174000')),
    );

    $this->repository->delete($role);
    self::assertNull($this->repository->findById($roleId));
  }
  // #endregion

  // #region Helpers
  private function createPermissionRecord(string $id, string $name): PermissionRecord
  {
    $record = new PermissionRecord();
    $record->id = $id;
    $record->name = $name;
    $record->description = "Permission $name";
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $this->entityManager->persist($record);

    return $record;
  }
  // #endregion
}
