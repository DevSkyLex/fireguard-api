<?php

declare(strict_types=1);

namespace Tests\Integration\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, SubjectType};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleAssignmentMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleAssignmentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test RoleAssignmentRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RoleAssignmentRepository::class)]
final class RoleAssignmentRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private RoleAssignmentRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new RoleAssignmentRepository(
      entityManager: $this->entityManager,
      mapper: new RoleAssignmentMapper(),
      roleMapper: new RoleMapper(new PermissionMapper()),
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
  public function testFindBySubjectExcludesExpiredAssignments(): void
  {
    $permission = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174000',
      name: 'users.read',
    );
    $activeRole = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174000',
      name: 'role_active',
      permission: $permission,
    );
    $expiredRole = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174001',
      name: 'role_expired',
      permission: $permission,
    );
    $this->entityManager->flush();

    $activeAssignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174000'),
      roleId: new RoleId($activeRole->id),
      userId: 'user-1',
    );
    $expiredAssignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174001'),
      roleId: new RoleId($expiredRole->id),
      userId: 'user-1',
      expiresAt: new DateTimeImmutable('-1 day'),
    );

    $this->repository->save($activeAssignment);
    $this->repository->save($expiredAssignment);

    $assignments = $this->repository->findBySubject(SubjectType::USER, 'user-1');

    self::assertCount(1, $assignments);
    self::assertSame($activeAssignment->id()->value, $assignments[0]->id()->value);
  }

  #[Test]
  public function testFindRolesForSubjectReturnsRolesWithPermissions(): void
  {
    $permission = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174010',
      name: 'users.read',
    );
    $role = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174010',
      name: 'role_admin',
      permission: $permission,
    );
    $this->entityManager->flush();

    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174010'),
      roleId: new RoleId($role->id),
      userId: 'user-2',
    );
    $this->repository->save($assignment);

    $roles = $this->repository->findRolesForSubject(SubjectType::USER, 'user-2');

    self::assertCount(1, $roles);
    self::assertSame($role->id, $roles[0]->id()->value);
    self::assertCount(1, $roles[0]->permissions());
    self::assertSame('users.read', $roles[0]->permissions()[0]->name()->value);
  }

  #[Test]
  public function testFindByRoleReturnsAssignments(): void
  {
    $permission = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174020',
      name: 'roles.read',
    );
    $role = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174020',
      name: 'role_reader',
      permission: $permission,
    );
    $this->entityManager->flush();

    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174020'),
      roleId: new RoleId($role->id),
      userId: 'user-3',
    );
    $this->repository->save($assignment);

    $assignments = $this->repository->findByRole(new RoleId($role->id));

    self::assertCount(1, $assignments);
    self::assertSame($assignment->id()->value, $assignments[0]->id()->value);
  }

  #[Test]
  public function testDeleteBySubjectRemovesAssignments(): void
  {
    $permission = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174030',
      name: 'roles.assign',
    );
    $role = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174030',
      name: 'role_assigner',
      permission: $permission,
    );
    $this->entityManager->flush();

    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174030'),
      roleId: new RoleId($role->id),
      userId: 'user-4',
    );
    $this->repository->save($assignment);

    $this->repository->deleteBySubject(SubjectType::USER, 'user-4');

    $assignments = $this->repository->findBySubject(SubjectType::USER, 'user-4');
    self::assertCount(0, $assignments);
  }

  #[Test]
  public function testSaveAndDeleteById(): void
  {
    $permission = $this->createPermissionRecord(
      id: '323e4567-e89b-12d3-a456-426614174040',
      name: 'roles.manage',
    );
    $role = $this->createRoleRecord(
      id: '223e4567-e89b-12d3-a456-426614174040',
      name: 'role_manager',
      permission: $permission,
    );
    $this->entityManager->flush();

    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174040'),
      roleId: new RoleId($role->id),
      userId: 'user-5',
    );
    $this->repository->save($assignment);

    $found = $this->repository->findById(new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174040'));
    self::assertNotNull($found);

    $this->repository->delete($assignment);

    $deleted = $this->repository->findById(new RoleAssignmentId('123e4567-e89b-12d3-a456-426614174040'));
    self::assertNull($deleted);
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

  private function createRoleRecord(string $id, string $name, PermissionRecord $permission): RoleRecord
  {
    $record = new RoleRecord();
    $record->id = $id;
    $record->name = $name;
    $record->description = "Role $name";
    $record->isSystem = false;
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->permissions->add($permission);

    $this->entityManager->persist($record);

    return $record;
  }
  // #endregion
}
