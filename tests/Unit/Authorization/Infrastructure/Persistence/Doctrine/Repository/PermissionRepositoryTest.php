<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\PermissionMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;
use Authorization\Infrastructure\Persistence\Doctrine\Repository\PermissionRepository;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PermissionRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: PermissionRepository::class)]
final class PermissionRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $missingId = '123e4567-e89b-12d3-a456-426614174111';

    $entityManager->expects(self::once())
      ->method('find')
      ->with(PermissionRecord::class, $missingId)
      ->willReturn(null);

    $repository = new PermissionRepository(
      entityManager: $entityManager,
      mapper: new PermissionMapper(),
    );

    self::assertNull($repository->findById(new PermissionId($missingId)));
  }

  #[Test]
  public function testFindByNameReturnsPermission(): void
  {
    $record = $this->createRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->with(['name' => 'users.read'])
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(PermissionRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new PermissionRepository(
      entityManager: $entityManager,
      mapper: new PermissionMapper(),
    );

    $result = $repository->findByName(new PermissionName('users.read'));

    self::assertInstanceOf(Permission::class, $result);
    self::assertSame('users.read', $result->name()->value);
  }

  #[Test]
  public function testFindAllReturnsPermissions(): void
  {
    $record = $this->createRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findAll')
      ->willReturn([$record]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(PermissionRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new PermissionRepository(
      entityManager: $entityManager,
      mapper: new PermissionMapper(),
    );

    $result = $repository->findAll();

    self::assertCount(1, $result);
    self::assertInstanceOf(Permission::class, $result[0]);
  }

  #[Test]
  public function testSavePersistsRecord(): void
  {
    $permission = new Permission(
      id: new PermissionId('123e4567-e89b-12d3-a456-426614174000'),
      name: new PermissionName('users.read'),
      description: 'Read users',
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
    );

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(PermissionRecord::class, $permission->id()->value)
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(PermissionRecord::class));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new PermissionRepository(
      entityManager: $entityManager,
      mapper: new PermissionMapper(),
    );

    $repository->save($permission);
  }

  #[Test]
  public function testDeleteRemovesRecord(): void
  {
    $record = $this->createRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(PermissionRecord::class, '123e4567-e89b-12d3-a456-426614174000')
      ->willReturn($record);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with($record);
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new PermissionRepository(
      entityManager: $entityManager,
      mapper: new PermissionMapper(),
    );

    $permission = new Permission(
      id: new PermissionId('123e4567-e89b-12d3-a456-426614174000'),
      name: new PermissionName('users.read'),
      description: 'Read users',
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
    );

    $repository->delete($permission);
  }

  private function createRecord(): PermissionRecord
  {
    $record = new PermissionRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->name = 'users.read';
    $record->description = 'Read users';
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    return $record;
  }
  // #endregion
}
